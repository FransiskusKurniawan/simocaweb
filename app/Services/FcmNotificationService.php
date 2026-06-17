<?php

namespace App\Services;

use App\Models\FcmToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    /**
     * Send a push notification to all registered FCM tokens.
     */
    public static function sendToAll($title, $body, $data = [])
    {
        $tokens = FcmToken::pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info('FCM: No registered tokens found.');
            return;
        }

        self::sendNotification($tokens, $title, $body, $data);
    }

    /**
     * Send notification payload using Firebase legacy or HTTP v1 simulated API.
     */
    protected static function sendNotification(array $tokens, $title, $body, $data = [])
    {
        $serverKey = env('FCM_SERVER_KEY');

        if (!$serverKey) {
            Log::warning('FCM: Server Key (FCM_SERVER_KEY) is not set in env. Skipping notification send.');
            return;
        }

        // Add standard sound and click action to the data payload so the mobile app handles it correctly
        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => '1',
            ],
            'data' => array_merge([
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'title' => $title,
                'body' => $body,
            ], $data),
            'priority' => 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            if ($response->successful()) {
                Log::info('FCM: Push notification sent successfully to ' . count($tokens) . ' devices.');
            } else {
                Log::error('FCM: Failed to send notification. Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('FCM: Error sending notification: ' . $e->getMessage());
        }
    }
}
