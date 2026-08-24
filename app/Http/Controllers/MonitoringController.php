<?php
namespace App\Http\Controllers;

use App\Models\SensorData;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MonitoringController extends Controller
{
    /**
     * Helper to fetch history data with custom range support.
     */
    private function fetchHistory(Request $request, $metrics = [])
    {
        $range = $request->get('range', '1h');
        $page = (int)$request->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $now = Carbon::now();
        
        if ($range === 'custom') {
            $start = $request->get('start_date');
            $end = $request->get('end_date');
            $startTime = $start ? Carbon::parse($start)->startOfDay() : $now->copy()->subDay();
            $endTime = $end ? Carbon::parse($end)->endOfDay() : $now;
        } else {
            $durationInMinutes = match($range) {
                '5m' => 5,
                '1h' => 60,
                '12h' => 12 * 60,
                '1d' => 24 * 60,
                '1w' => 7 * 24 * 60,
                '1m' => 30 * 24 * 60,
                default => 60,
            };
            
            $offsetMinutes = ($page - 1) * $durationInMinutes;
            $endTime = $now->copy()->subMinutes($offsetMinutes);
            $startTime = $endTime->copy()->subMinutes($durationInMinutes);
        }

        $query = SensorData::where(function($q) use ($startTime, $endTime) {
            $q->whereBetween('timertc', [$startTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s')])
              ->orWhereBetween('created_at', [$startTime, $endTime]);
        });

        $totalCount = (clone $query)->count();
        $step = (int)ceil($totalCount / 2000);
        if ($step > 1) {
            $query->whereRaw("id % {$step} = 0");
        }

        $history = $query
            ->orderBy('timertc', 'desc')
            ->take(2000)
            ->get()
            ->reverse()
            ->values();

        $latestRecord = SensorData::orderBy('timertc', 'desc')->first();
        $maxTime = $latestRecord ? $latestRecord->timertc : $now->toIso8601String();

        $isRainfall = in_array('rainfall', $metrics);
        $stats = [];
        if ($isRainfall) {
            $rainfallValues = $history->map(fn($r) => $r->rainfall);
            $stats['val_max'] = (float)$rainfallValues->max() ?? 0.0;
            $stats['val_avg'] = (float)$rainfallValues->avg() ?? 0.0;
            $stats['total'] = (int)$history->count();
        } else {
            $selects = ['COUNT(*) as total'];
            foreach ($metrics as $key => $column) {
                if (str_contains($key, 'max')) {
                    $selects[] = "MAX({$column}) as `{$key}`";
                } elseif (str_contains($key, 'avg')) {
                    $selects[] = "AVG({$column}) as `{$key}`";
                } else {
                    $selects[] = "MAX({$column}) as `{$key}_max`";
                    $selects[] = "AVG({$column}) as `{$key}_avg`";
                }
            }
            
            $aggregateResult = (clone $query)->reorder()->selectRaw(implode(', ', $selects))->first();
            
            $stats['total'] = (int)($aggregateResult->total ?? 0);
            foreach ($metrics as $key => $column) {
                if (str_contains($key, 'max') || str_contains($key, 'avg')) {
                    $stats[$key] = (float)($aggregateResult->$key ?? 0.0);
                } else {
                    $stats[$key . '_max'] = (float)($aggregateResult->{$key . '_max'} ?? 0.0);
                    $stats[$key . '_avg'] = (float)($aggregateResult->{$key . '_avg'} ?? 0.0);
                }
            }
        }
        
        // Compatibility for simple metrics (rainfall, temp, etc)
        if (isset($stats['val_max'])) {
            $stats['max'] = $stats['val_max'];
            $stats['avg'] = $stats['val_avg'];
        }

        return [
            'success' => true,
            'data' => $history,
            'range' => $range,
            'startTime' => $startTime->toIso8601String(),
            'endTime' => $endTime->toIso8601String(),
            'maxTime' => $maxTime,
            'global' => $stats
        ];
    }

    public function rainfall()
    {
        $now = Carbon::now();
        $startTime = $now->copy()->subDay();
        $latest = SensorData::latest()->first();
        
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $startTime)
            ->orderBy('timertc', 'desc')->take(1100)->get()->reverse()->values();

        $rainfallValues = $history->map(fn($r) => $r->rainfall);

        $aggregates = SensorData::selectRaw('COUNT(*) as total')->first();
        $globalStats = [
            'max' => (float)$rainfallValues->max() ?? 0.0,
            'avg' => (float)$rainfallValues->avg() ?? 0.0,
            'total' => (int)($aggregates->total ?? 0)
        ];
        return view('monitoring.rainfall', compact('history', 'latest', 'globalStats'));
    }

    public function getHistory(Request $request)
    {
        return response()->json($this->fetchHistory($request, ['val' => 'rainfall']));
    }

    public function temperature()
    {
        $now = Carbon::now();
        $startTime = $now->copy()->subDay();
        $latest = SensorData::latest()->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $startTime)
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        
        $aggregates = SensorData::selectRaw('MAX(temperature) as max_val, AVG(temperature) as avg_val, COUNT(*) as total')->first();
        $globalStats = [
            'max' => (float)($aggregates->max_val ?? 0),
            'avg' => (float)($aggregates->avg_val ?? 0),
            'total' => (int)($aggregates->total ?? 0)
        ];
        return view('monitoring.temperature', compact('history', 'latest', 'globalStats'));
    }

    public function getTemperatureHistory(Request $request)
    {
        return response()->json($this->fetchHistory($request, ['val' => 'temperature']));
    }

    public function humidity()
    {
        $now = Carbon::now();
        $startTime = $now->copy()->subDay();
        $latest = SensorData::latest()->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $startTime)
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        
        $aggregates = SensorData::selectRaw('MAX(humidity) as max_val, AVG(humidity) as avg_val, COUNT(*) as total')->first();
        $globalStats = [
            'max' => (float)($aggregates->max_val ?? 0),
            'avg' => (float)($aggregates->avg_val ?? 0),
            'total' => (int)($aggregates->total ?? 0)
        ];
        return view('monitoring.humidity', compact('history', 'latest', 'globalStats'));
    }

    public function getHumidityHistory(Request $request)
    {
        return response()->json($this->fetchHistory($request, ['val' => 'humidity']));
    }

    public function waterLevel()
    {
        $now = Carbon::now();
        $startTime = $now->copy()->subDay();
        $latest = SensorData::latest()->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $startTime)
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        
        $aggregates = SensorData::selectRaw('MAX(water_level) as max_val, AVG(water_level) as avg_val, COUNT(*) as total')->first();
        $globalStats = [
            'max' => (float)($aggregates->max_val ?? 0),
            'avg' => (float)($aggregates->avg_val ?? 0),
            'total' => (int)($aggregates->total ?? 0)
        ];
        return view('monitoring.water_level', compact('history', 'latest', 'globalStats'));
    }

    public function getWaterLevelHistory(Request $request)
    {
        return response()->json($this->fetchHistory($request, ['val' => 'water_level']));
    }

    public function lux()
    {
        $now = Carbon::now();
        $startTime = $now->copy()->subDay();
        $latest = SensorData::latest()->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $startTime)
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        
        $aggregates = SensorData::selectRaw('MAX(lux) as max_val, AVG(lux) as avg_val, COUNT(*) as total')->first();
        $globalStats = [
            'max' => (float)($aggregates->max_val ?? 0),
            'avg' => (float)($aggregates->avg_val ?? 0),
            'total' => (int)($aggregates->total ?? 0)
        ];
        return view('monitoring.lux', compact('history', 'latest', 'globalStats'));
    }

    public function getLuxHistory(Request $request)
    {
        return response()->json($this->fetchHistory($request, ['val' => 'lux']));
    }

    public function solarPanel()
    {
        $now = Carbon::now();
        $startTime = $now->copy()->subDay();
        $latest = SensorData::latest()->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $startTime)
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        
        $aggregates = SensorData::selectRaw('
            MAX(voltage_panel) as max_voltage,
            MAX(current_panel) as max_current,
            AVG(voltage_panel) as avg_voltage,
            AVG(current_panel) as avg_current,
            COUNT(*) as total
        ')->first();
        
        $globalStats = [
            'max_voltage' => (float)($aggregates->max_voltage ?? 0),
            'max_current' => (float)($aggregates->max_current ?? 0),
            'avg_voltage' => (float)($aggregates->avg_voltage ?? 0),
            'avg_current' => (float)($aggregates->avg_current ?? 0),
            'total' => (int)($aggregates->total ?? 0)
        ];
        return view('monitoring.solar_panel', compact('history', 'latest', 'globalStats'));
    }

    public function getSolarPanelHistory(Request $request)
    {
        return response()->json($this->fetchHistory($request, [
            'max_v' => 'voltage_panel',
            'max_a' => 'current_panel',
            'avg_v' => 'voltage_panel',
            'avg_a' => 'current_panel'
        ]));
    }

    public function battery()
    {
        $now = Carbon::now();
        $startTime = $now->copy()->subDay();
        $latest = SensorData::latest()->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $startTime)
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        
        $aggregates = SensorData::selectRaw('
            MAX(voltage_baterai) as max_voltage,
            MAX(current_baterai) as max_current,
            AVG(voltage_baterai) as avg_voltage,
            AVG(current_baterai) as avg_current,
            COUNT(*) as total
        ')->first();

        $globalStats = [
            'max_voltage' => (float)($aggregates->max_voltage ?? 0),
            'max_current' => (float)($aggregates->max_current ?? 0),
            'avg_voltage' => (float)($aggregates->avg_voltage ?? 0),
            'avg_current' => (float)($aggregates->avg_current ?? 0),
            'total' => (int)($aggregates->total ?? 0)
        ];
        return view('monitoring.battery', compact('history', 'latest', 'globalStats'));
    }

    public function getBatteryHistory(Request $request)
    {
        return response()->json($this->fetchHistory($request, [
            'max_v' => 'voltage_baterai',
            'max_a' => 'current_baterai',
            'avg_v' => 'voltage_baterai',
            'avg_a' => 'current_baterai'
        ]));
    }
}
