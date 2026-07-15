<?php
$now = Carbon\Carbon::now();
$endTime = $now;
$startTime = $now->copy()->subMinutes(7 * 24 * 60);

$query = App\Models\SensorData::where(function($q) use ($startTime, $endTime) {
    $q->whereBetween('timertc', [$startTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s')])
      ->orWhereBetween('created_at', [$startTime, $endTime]);
});

$totalCount = (clone $query)->count();
echo "Total count without limit: " . $totalCount . "\n";

$step = (int)ceil($totalCount / 2000);
echo "Calculated step: " . $step . "\n";

if ($step > 1) {
    $query->whereRaw("id % {$step} = 0");
}

$historyRaw = $query->orderBy('timertc', 'desc')->get()->reverse()->values();

echo "Count after step: " . count($historyRaw) . "\n";
if (count($historyRaw) > 0) {
    echo "First element timertc: " . $historyRaw->first()->timertc . "\n";
    echo "Last element timertc: " . $historyRaw->last()->timertc . "\n";
}
