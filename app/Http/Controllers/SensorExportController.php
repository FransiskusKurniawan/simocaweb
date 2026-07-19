<?php

namespace App\Http\Controllers;

use App\Models\SensorData;
use Illuminate\Http\Request;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SensorExportController extends Controller
{
    /**
     * Export sensor data to .xlsx format.
     */
    public function export(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = SensorData::query();
        $startTime = null;

        // Apply date filter if provided (matching same logic as MonitoringController)
        if ($startDate || $endDate) {
            $now = Carbon::now();
            $startTime = $startDate ? Carbon::parse($startDate)->startOfDay() : $now->copy()->subDay();
            $endTime = $endDate ? Carbon::parse($endDate)->endOfDay() : $now;

            // Subtract 1 hour from startTime for lookback query buffer
            $queryStartTime = $startTime->copy()->subHour();

            $query->where(function ($q) use ($queryStartTime, $endTime) {
                $q->whereBetween('timertc', [$queryStartTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s')])
                  ->orWhereBetween('created_at', [$queryStartTime, $endTime]);
            });
        }

        $rawRecords = $query->orderBy('timertc', 'desc')->get();

        // Calculate hourly rainfall in-memory
        SensorData::calculateHourlyRainfall($rawRecords);

        // Filter out the buffer if filter was applied
        if ($startTime) {
            $data = $rawRecords->filter(function($record) use ($startTime) {
                $time = $record->created_at;
                if (!empty($record->timertc)) {
                    try {
                        $time = Carbon::parse($record->timertc);
                    } catch (\Exception $e) {}
                }
                return $time->greaterThanOrEqualTo($startTime);
            })->values();
        } else {
            $data = $rawRecords;
        }

        // Create Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sensor Data');
        $sheet->setShowGridlines(true);

        // Set Header Columns
        $headers = [
            'A1' => 'No',
            'B1' => 'Timestamp (RTC)',
            'C1' => 'Rainfall (mm/minute)',
            'D1' => 'Rainfall (mm/hour)',
            'E1' => 'Rainfall Status',
            'F1' => 'Temperature (°C)',
            'G1' => 'Humidity (%)',
            'H1' => 'Water Level (m)',
            'I1' => 'Light (Lux)',
            'J1' => 'Solar Panel Voltage (V)',
            'K1' => 'Solar Panel Current (A)',
            'L1' => 'Battery Voltage (V)',
            'M1' => 'Battery Current (A)',
            'N1' => 'Pump 1 Status',
            'O1' => 'Pump 2 Status',
            'P1' => 'Jitter (ms)',
            'Q1' => 'Delay (ms)',
            'R1' => 'Send Time',
            'S1' => 'Receive Time',
            'T1' => 'Media',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style header row
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'], // Slate 900
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A1:T1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Populate Data Rows in bulk using fromArray
        $dataArray = [];
        foreach ($data as $index => $row) {
            $dataArray[] = [
                $index + 1,
                $row->timertc,
                $row->rainfall,
                $row->rainfall_hourly,
                $row->status, // dynamic status attribute
                $row->temperature,
                $row->humidity,
                $row->water_level,
                $row->lux,
                $row->voltage_panel,
                $row->current_panel,
                $row->voltage_baterai,
                $row->current_baterai,
                $row->status_pompa ? 'Active' : 'Offline',
                $row->status_pompa2 ? 'Active' : 'Offline',
                $row->jitter ?? 0,
                $row->delay ?? 0,
                $row->send_time,
                $row->receive_time,
                $row->media,
            ];
        }
        $sheet->fromArray($dataArray, null, 'A2');

        // Column-level styling (alignment)
        $sheet->getStyle('A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('N')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('O')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Re-align headers to center (since column alignment overrides header row)
        $sheet->getStyle('A1:T1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Set predefined column widths instead of slow auto-fit
        $colWidths = [
            'A' => 6,
            'B' => 22,
            'C' => 22,
            'D' => 22,
            'E' => 18,
            'F' => 18,
            'G' => 15,
            'H' => 18,
            'I' => 15,
            'J' => 24,
            'K' => 24,
            'L' => 24,
            'M' => 24,
            'N' => 16,
            'O' => 16,
            'P' => 15,
            'Q' => 15,
            'R' => 22,
            'S' => 22,
            'T' => 12,
        ];
        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Create file name
        $fileName = 'sensor_data_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        // Direct download output stream headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
