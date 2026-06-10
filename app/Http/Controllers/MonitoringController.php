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

        $isRainfall = in_array('rainfall', $metrics);
        
        if ($isRainfall) {
            $queryStartTime = $startTime->copy()->subHour();
        } else {
            $queryStartTime = $startTime;
        }

        $query = SensorData::where(function($q) use ($queryStartTime, $endTime) {
            $q->whereBetween('timertc', [$queryStartTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s')])
              ->orWhereBetween('created_at', [$queryStartTime, $endTime]);
        });

        $historyRaw = (clone $query)
            ->orderBy('timertc', 'desc')
            ->take(2000)
            ->get()
            ->reverse()
            ->values();

        if ($isRainfall) {
            SensorData::calculateHourlyRainfall($historyRaw);
            
            // Filter back to the original startTime range
            $history = $historyRaw->filter(function($record) use ($startTime) {
                $time = $record->created_at;
                if (!empty($record->timertc)) {
                    try {
                        $time = Carbon::parse($record->timertc);
                    } catch (\Exception $e) {}
                }
                return $time->greaterThanOrEqualTo($startTime);
            })->values();
        } else {
            $history = $historyRaw;
        }

        $latestRecord = SensorData::orderBy('timertc', 'desc')->first();
        $maxTime = $latestRecord ? $latestRecord->timertc : $now->toIso8601String();

        $stats = [];
        if ($isRainfall) {
            $rainfallHourlyValues = $history->map(fn($r) => $r->rainfall_hourly);
            $stats['val_max'] = (float)$rainfallHourlyValues->max() ?? 0.0;
            $stats['val_avg'] = (float)$rainfallHourlyValues->avg() ?? 0.0;
            $stats['total'] = (int)$history->count();
        } else {
            foreach ($metrics as $key => $column) {
                // Check if key contains 'max' or 'avg' to customize
                if (str_contains($key, 'max')) {
                    $stats[$key] = (float)(clone $query)->max($column);
                } elseif (str_contains($key, 'avg')) {
                    $stats[$key] = (float)(clone $query)->avg($column);
                } else {
                    // Default behavior for simple metrics
                    $stats[$key . '_max'] = (float)(clone $query)->max($column);
                    $stats[$key . '_avg'] = (float)(clone $query)->avg($column);
                }
            }
            $stats['total'] = (int)(clone $query)->count();
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
        $latest = SensorData::orderBy('timertc', 'desc')->first();
        
        $queryStartTime = $startTime->copy()->subHour();
        $historyRaw = SensorData::where('timertc', '>=', $queryStartTime->format('Y-m-d H:i:s'))
            ->orWhere('created_at', '>=', $queryStartTime)
            ->orderBy('timertc', 'desc')->take(1100)->get()->reverse()->values();
            
        SensorData::calculateHourlyRainfall($historyRaw);
        
        $history = $historyRaw->filter(function($record) use ($startTime) {
            $time = $record->created_at;
            if (!empty($record->timertc)) {
                try {
                    $time = Carbon::parse($record->timertc);
                } catch (\Exception $e) {}
            }
            return $time->greaterThanOrEqualTo($startTime);
        })->values();

        $rainfallHourlyValues = $history->map(fn($r) => $r->rainfall_hourly);

        $globalStats = [
            'max' => (float)$rainfallHourlyValues->max() ?? 0.0,
            'avg' => (float)$rainfallHourlyValues->avg() ?? 0.0,
            'total' => SensorData::count()
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
        $latest = SensorData::orderBy('timertc', 'desc')->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        $globalStats = [
            'max' => SensorData::max('temperature') ?? 0,
            'avg' => SensorData::avg('temperature') ?? 0,
            'total' => SensorData::count()
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
        $latest = SensorData::orderBy('timertc', 'desc')->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        $globalStats = [
            'max' => SensorData::max('humidity') ?? 0,
            'avg' => SensorData::avg('humidity') ?? 0,
            'total' => SensorData::count()
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
        $latest = SensorData::orderBy('timertc', 'desc')->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        $globalStats = [
            'max' => SensorData::max('water_level') ?? 0,
            'avg' => SensorData::avg('water_level') ?? 0,
            'total' => SensorData::count()
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
        $latest = SensorData::orderBy('timertc', 'desc')->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        $globalStats = [
            'max' => SensorData::max('lux') ?? 0,
            'avg' => SensorData::avg('lux') ?? 0,
            'total' => SensorData::count()
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
        $latest = SensorData::orderBy('timertc', 'desc')->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        $globalStats = [
            'max_voltage' => SensorData::max('voltage_panel') ?? 0,
            'max_current' => SensorData::max('current_panel') ?? 0,
            'avg_voltage' => SensorData::avg('voltage_panel') ?? 0,
            'avg_current' => SensorData::avg('current_panel') ?? 0,
            'total' => SensorData::count()
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
        $latest = SensorData::orderBy('timertc', 'desc')->first();
        $history = SensorData::where('timertc', '>=', $startTime->format('Y-m-d H:i:s'))
            ->orderBy('timertc', 'desc')->take(1000)->get()->reverse()->values();
        $globalStats = [
            'max_voltage' => SensorData::max('voltage_baterai') ?? 0,
            'max_current' => SensorData::max('current_baterai') ?? 0,
            'avg_voltage' => SensorData::avg('voltage_baterai') ?? 0,
            'avg_current' => SensorData::avg('current_baterai') ?? 0,
            'total' => SensorData::count()
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
