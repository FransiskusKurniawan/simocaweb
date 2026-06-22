<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SensorData;

echo "Latest 10 sensor records:\n";
$records = SensorData::latest()->take(10)->get();
foreach ($records as $r) {
    echo "ID: {$r->id}, RTC: {$r->timertc}, Pump1: " . ($r->status_pompa ? 'ON' : 'OFF') . ", Pump2: " . ($r->status_pompa2 ? 'ON' : 'OFF') . ", Created: {$r->created_at}\n";
}
