<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SensorData;
use App\Events\SensorDataStored;

class SensorController extends Controller
{
    // Ambil semua data
    public function index()
    {
        $data = SensorData::latest()->get();
        SensorData::calculateHourlyRainfall($data);
        return $data;
    }

    // Simpan data
    public function store(Request $request)
    {
        // Get the latest existing record to compare status
        $previousData = SensorData::latest()->first();

        // Server receive time
        $receiveTime = \Carbon\Carbon::now();
        $receiveTimeMs = (int) round($receiveTime->valueOf());

        $payload = $request->all();
        $payload['receive_time'] = $receiveTime->toIso8601String();

        $sendTimeInput = $request->input('send_time');
        if ($sendTimeInput) {
            $sendTimeMs = null;
            if (is_numeric($sendTimeInput)) {
                if (strlen((string)$sendTimeInput) >= 13) {
                    $sendTimeMs = (float) $sendTimeInput;
                } else {
                    $sendTimeMs = (float) $sendTimeInput * 1000;
                }
            } else {
                try {
                    $parsed = \Carbon\Carbon::parse($sendTimeInput);
                    $sendTimeMs = (float) round($parsed->valueOf());
                } catch (\Exception $e) {
                    // Ignore parsing error
                }
            }

            if ($sendTimeMs !== null) {
                // Delay = Receive Time - Send Time (in milliseconds)
                $delay = (float) ($receiveTimeMs - $sendTimeMs);
                // Prevent MySQL float out of range error by capping or resetting if unreasonably large
                if ($delay < 0 || $delay > 999999.99) {
                    $payload['delay'] = 0.0;
                } else {
                    $payload['delay'] = $delay;
                }

                // Calculate jitter: |Current Delay - Previous Delay|
                if ($previousData && isset($previousData->delay)) {
                    $jitter = abs($payload['delay'] - $previousData->delay);
                    if ($jitter > 999999.99) {
                        $payload['jitter'] = 0.0;
                    } else {
                        $payload['jitter'] = $jitter;
                    }
                } else {
                    $payload['jitter'] = 0.0;
                }
            }
        }

        $data = SensorData::create($payload);
        
        // Eagerly calculate hourly rainfall and status to cache them before serialization/broadcasting
        $data->rainfall_hourly;
        $data->status;

        // Detect transitions: false/0 -> true/1
        if ($previousData) {
            $pump1Transition = (!$previousData->status_pompa && $data->status_pompa);
            $pump2Transition = (!$previousData->status_pompa2 && $data->status_pompa2);

            if ($pump1Transition || $pump2Transition) {
                $pumps = [];
                if ($pump1Transition) $pumps[] = 'Pompa 1';
                if ($pump2Transition) $pumps[] = 'Pompa 2';
                $pumpNames = implode(' dan ', $pumps);

                try {
                    // Save notification to the database
                    \App\Models\Notification::create([
                        'title' => '🚨 Pompa Diaktifkan',
                        'body' => "Pompa air ({$pumpNames}) telah menyala",
                        'type' => 'pump',
                        'is_read' => false
                    ]);

                    \App\Services\FcmNotificationService::sendToAll(
                        '🚨 Pompa Diaktifkan',
                        "Pompa air ({$pumpNames}) telah menyala",
                        [
                            'pump_event' => 'activated',
                            'pumps' => $pumps,
                        ]
                    );
                } catch (\Exception $e) {
                    \Log::error('FCM dispatch failed: ' . $e->getMessage());
                }
            }

            // Detect rainfall crossing threshold: previous <= 10 and current > 10 mm/hour
            $prevRainfallHourly = $previousData->rainfall_hourly ?? 0;
            $currRainfallHourly = $data->rainfall_hourly ?? 0;
            $rainfallThresholdCrossed = ($prevRainfallHourly <= 10 && $currRainfallHourly > 10);

            if ($rainfallThresholdCrossed) {
                $rainfallFormatted = number_format($currRainfallHourly, 2);

                try {
                    // Save rainfall notification to the database
                    \App\Models\Notification::create([
                        'title' => '🌧️ Curah Hujan Tinggi',
                        'body' => "Curah hujan mencapai {$rainfallFormatted} mm/hour",
                        'type' => 'rainfall',
                        'is_read' => false
                    ]);

                    \App\Services\FcmNotificationService::sendToAll(
                        '🌧️ Curah Hujan Tinggi',
                        "Curah hujan mencapai {$rainfallFormatted} mm/hour",
                        [
                            'rainfall_event' => 'threshold_exceeded',
                            'rainfall_hourly' => (string) $currRainfallHourly,
                        ]
                    );
                } catch (\Exception $e) {
                    \Log::error('FCM rainfall dispatch failed: ' . $e->getMessage());
                }
            }
        }
        
        // Dispatch event for real-time update (wrapped in try-catch to prevent crash if websocket is offline)
        try {
            event(new SensorDataStored($data));
        } catch (\Exception $e) {
            \Log::error('Websocket broadcasting failed: ' . $e->getMessage());
        }

        return response()->json($data);
    }
}