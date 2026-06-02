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

    /**
     * Get the dynamic status based on rainfall intensity.
     */
    public function getStatusAttribute()
    {
        $r = $this->rainfall;
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
