<?php

namespace App\Events;

use App\Models\SensorData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Data sensor yang akan dikirim secara realtime.
     * Variabel publik otomatis diserialisasikan ke JSON payload.
     *
     * @var SensorData
     */
    public $data;

    /**
     * Membuat instance event baru.
     */
    public function __construct(SensorData $data)
    {
        $this->data = $data;
    }

    /**
     * Menentukan channel mana event ini akan disiarkan.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Menggunakan Public Channel: sensor-channel
        return [
            new Channel('sensor-channel'),
        ];
    }

    /**
     * Nama event kustom yang akan disiarkan di Pusher.
     * Ini opsional, tetapi sangat mempermudah pemetaan di sisi frontend / JS.
     */
    public function broadcastAs(): string
    {
        return 'SensorDataUpdated';
    }
}
