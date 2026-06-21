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

        $data = SensorData::create($request->all());
        
        // Eagerly calculate hourly rainfall and status to cache them before serialization/broadcasting
        $data->rainfall_hourly;
        $data->status;

        // Detect transitions: false/0 -> true/1
        if ($previousData) {
            $pump1Transition = (!$previousData->status_pompa && $data->status_pompa);
            $pump2Transition = (!$previousData->status_pompa2 && $data->status_pompa2);

            if ($pump1Transition || $pump2Transition) {
                $pumps = [];
                if ($pump1Transition) $pumps[] = 'Pump 1';
                if ($pump2Transition) $pumps[] = 'Pump 2';
                $pumpNames = implode(' and ', $pumps);

                try {
                    // Save notification to the database
                    \App\Models\Notification::create([
                        'title' => '🚨 Pump Activated',
                        'body' => "The water pump ({$pumpNames}) has been switched ON",
                        'type' => 'pump',
                        'is_read' => false
                    ]);

                    \App\Services\FcmNotificationService::sendToAll(
                        '🚨 Pump Activated',
                        "The water pump ({$pumpNames}) has been switched ON",
                        [
                            'pump_event' => 'activated',
                            'pumps' => $pumps,
                        ]
                    );
                } catch (\Exception $e) {
                    \Log::error('FCM dispatch failed: ' . $e->getMessage());
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