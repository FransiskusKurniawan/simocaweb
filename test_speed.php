<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$t0 = microtime(true);
$query = App\Models\SensorData::where('timertc', '>=', '2026-07-05 00:00:00')->where('timertc', '<=', '2026-07-11 23:59:59');
$records = $query->orderBy('timertc', 'desc')->get();
$t1 = microtime(true);
echo 'Query time: ' . ($t1 - $t0) . 's (count: ' . count($records) . ')' . PHP_EOL;

// Version B: Optimized calculation
$t_b0 = microtime(true);
$sorted = [];
foreach ($records as $record) {
    $record->temp_ts = App\Models\SensorData::getRecordTimestamp($record);
    $sorted[] = $record;
}

usort($sorted, function ($a, $b) {
    return $a->temp_ts <=> $b->temp_ts;
});

$left = 0;
$currentSum = 0.0;
$count = count($sorted);

for ($right = 0; $right < $count; $right++) {
    $rightTime = $sorted[$right]->temp_ts;
    $currentSum += $sorted[$right]->rainfall;

    while ($left < $right) {
        $leftTime = $sorted[$left]->temp_ts;
        if ($rightTime - $leftTime > 3600) {
            $currentSum -= $sorted[$left]->rainfall;
            $left++;
        } else {
            break;
        }
    }

    $sorted[$right]->setAttribute('rainfall_hourly', round($currentSum, 2));
}

// Filter
$startTime = Carbon\Carbon::parse('2026-07-05 00:00:00');
$startTimeTs = $startTime->timestamp;
$data = $records->filter(function($record) use ($startTimeTs) {
    return $record->temp_ts >= $startTimeTs;
})->values();

$t_b1 = microtime(true);
echo 'Optimized calculation & filter time: ' . ($t_b1 - $t_b0) . 's' . PHP_EOL;

// Excel Generation benchmark - NO STYLING AT ALL
$t_excel0 = microtime(true);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sensor Data');

$headers = [
    'A1' => 'No', 'B1' => 'Timestamp (RTC)', 'C1' => 'Rainfall (mm/minute)', 'D1' => 'Rainfall (mm/hour)',
    'E1' => 'Rainfall Status', 'F1' => 'Temperature (°C)', 'G1' => 'Humidity (%)', 'H1' => 'Water Level (m)',
    'I1' => 'Light (Lux)', 'J1' => 'Solar Panel Voltage (V)', 'K1' => 'Solar Panel Current (A)',
    'L1' => 'Battery Voltage (V)', 'M1' => 'Battery Current (A)', 'N1' => 'Pump 1 Status',
    'O1' => 'Pump 2 Status', 'P1' => 'Jitter (ms)', 'Q1' => 'Delay (ms)', 'R1' => 'Send Time',
    'S1' => 'Receive Time', 'T1' => 'Media',
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$rowNumber = 2;
foreach ($data as $index => $row) {
    $sheet->setCellValue('A' . $rowNumber, $index + 1);
    $sheet->setCellValue('B' . $rowNumber, $row->timertc);
    $sheet->setCellValue('C' . $rowNumber, $row->rainfall);
    $sheet->setCellValue('D' . $rowNumber, $row->rainfall_hourly);
    $sheet->setCellValue('E' . $rowNumber, $row->status);
    $sheet->setCellValue('F' . $rowNumber, $row->temperature);
    $sheet->setCellValue('G' . $rowNumber, $row->humidity);
    $sheet->setCellValue('H' . $rowNumber, $row->water_level);
    $sheet->setCellValue('I' . $rowNumber, $row->lux);
    $sheet->setCellValue('J' . $rowNumber, $row->voltage_panel);
    $sheet->setCellValue('K' . $rowNumber, $row->current_panel);
    $sheet->setCellValue('L' . $rowNumber, $row->voltage_baterai);
    $sheet->setCellValue('M' . $rowNumber, $row->current_baterai);
    $sheet->setCellValue('N' . $rowNumber, $row->status_pompa ? 'Active' : 'Offline');
    $sheet->setCellValue('O' . $rowNumber, $row->status_pompa2 ? 'Active' : 'Offline');
    $sheet->setCellValue('P' . $rowNumber, $row->jitter ?? 0);
    $sheet->setCellValue('Q' . $rowNumber, $row->delay ?? 0);
    $sheet->setCellValue('R' . $rowNumber, $row->send_time);
    $sheet->setCellValue('S' . $rowNumber, $row->receive_time);
    $sheet->setCellValue('T' . $rowNumber, $row->media);
    $rowNumber++;
}

$t_excel1 = microtime(true);
echo 'Spreadsheet creation time (NO STYLING): ' . ($t_excel1 - $t_excel0) . 's' . PHP_EOL;

// Save file
$t_save0 = microtime(true);
$writer = new Xlsx($spreadsheet);
$writer->save('test_export.xlsx');
$t_save1 = microtime(true);
echo 'Writer save time: ' . ($t_save1 - $t_save0) . 's' . PHP_EOL;
echo 'Peak memory: ' . (memory_get_peak_usage(true) / 1024 / 1024) . ' MB' . PHP_EOL;
unlink('test_export.xlsx');
