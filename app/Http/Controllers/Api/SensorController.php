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
        $data = SensorData::create($request->all());
        
        // Eagerly calculate hourly rainfall and status to cache them before serialization/broadcasting
        $data->rainfall_hourly;
        $data->status;
        
        // Dispatch event for real-time update (wrapped in try-catch to prevent crash if websocket is offline)
        try {
            event(new SensorDataStored($data));
        } catch (\Exception $e) {
            \Log::error('Websocket broadcasting failed: ' . $e->getMessage());
        }

        return response()->json($data);
    }
}