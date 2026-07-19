<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorData extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'rainfall',
        'temperature',
        'humidity',
        'timertc',
        'lux',
        'water_level',
        'current_panel',
        'voltage_panel',
        'current_baterai',
        'voltage_baterai',
        'status_pompa',
        'status_pompa2',
        'status',
        'jitter',
        'delay',
        'send_time',
        'receive_time',
        'media',
    ];

    protected $casts = [
        'rainfall' => 'float',
        'temperature' => 'float',
        'humidity' => 'float',
        'lux' => 'float',
        'water_level' => 'float',
        'current_panel' => 'float',
        'voltage_panel' => 'float',
        'current_baterai' => 'float',
        'voltage_baterai' => 'float',
        'status_pompa' => 'boolean',
        'status_pompa2' => 'boolean',
        'jitter' => 'float',
        'delay' => 'float',
    ];

    protected $appends = [
        'status',
        'rainfall_hourly',
    ];

    /**
     * Get the timestamp of the record.
     */
    public static function getRecordTimestamp($record)
    {
        if (!empty($record->timertc)) {
            $ts = strtotime($record->timertc);
            if ($ts !== false) {
                return $ts;
            }
        }
        return $record->created_at ? $record->created_at->timestamp : time();
    }

    /**
     * Compute rainfall_hourly in-memory for a collection of SensorData records.
     */
    public static function calculateHourlyRainfall(iterable $records)
    {
        $sorted = [];
        foreach ($records as $record) {
            $record->temp_ts = self::getRecordTimestamp($record);
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
    }

    /**
     * Get accumulated hourly rainfall.
     */
    public function getRainfallHourlyAttribute()
    {
        if (array_key_exists('rainfall_hourly', $this->attributes)) {
            return $this->attributes['rainfall_hourly'];
        }

        $time = $this->created_at ?? \Carbon\Carbon::now();
        if (!empty($this->timertc)) {
            try {
                $time = \Carbon\Carbon::parse($this->timertc);
            } catch (\Exception $e) {
                // ignore
            }
        }
        
        $startTime = $time->copy()->subHour();
        
        $sum = self::where(function($q) use ($startTime, $time) {
            $q->whereBetween('timertc', [$startTime->format('Y-m-d H:i:s'), $time->format('Y-m-d H:i:s')])
              ->orWhereBetween('created_at', [$startTime, $time]);
        })->sum('rainfall');
        
        $val = round($sum, 2);
        $this->attributes['rainfall_hourly'] = $val;
        return $val;
    }

    /**
     * Get the dynamic status based on rainfall intensity.
     */
    public function getStatusAttribute()
    {
        $r = $this->rainfall_hourly;
        if ($r <= 0) {
            return 'No Rain';
        } elseif ($r <= 1) {
            return 'Very Light Rain';
        } elseif ($r <= 5) {
            return 'Light Rain';
        } elseif ($r <= 10) {
            return 'Moderate Rain';
        } elseif ($r <= 20) {
            return 'Heavy Rain';
        } else {
            return 'Very Heavy Rain';
        } 
    }
}
