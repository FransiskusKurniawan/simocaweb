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
        $sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Populate Data Rows
        $rowNumber = 2;
        foreach ($data as $index => $row) {
            $sheet->setCellValue('A' . $rowNumber, $index + 1);
            $sheet->setCellValue('B' . $rowNumber, $row->timertc);
            $sheet->setCellValue('C' . $rowNumber, $row->rainfall);
            $sheet->setCellValue('D' . $rowNumber, $row->rainfall_hourly);
            $sheet->setCellValue('E' . $rowNumber, $row->status); // dynamic status attribute
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

            $rowNumber++;
        }

        // Apply bulk styles to all data rows
        $maxRow = $rowNumber - 1;
        if ($maxRow >= 2) {
            // Alignment styles
            $sheet->getStyle('A2:A' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B2:B' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('N2:N' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('O2:O' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Add thin borders to all data rows
            $dataStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E2E8F0'], // Slate 200
                    ],
                ],
            ];
            $sheet->getStyle('A2:Q' . $maxRow)->applyFromArray($dataStyle);
        }

        // Auto-fit columns
        foreach (range('A', 'Q') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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
