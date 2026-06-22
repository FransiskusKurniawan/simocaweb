<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FcmToken;
use App\Services\FcmNotificationService;

echo "Registered FCM Tokens:\n";
$tokens = FcmToken::all();
if ($tokens->isEmpty()) {
    echo "No tokens found in database.\n";
} else {
    foreach ($tokens as $token) {
        echo "- ID: {$token->id}, Token: " . substr($token->token, 0, 20) . "..., Device: {$token->device_type}\n";
    }

    echo "\nSending test notification to all tokens...\n";
    FcmNotificationService::sendToAll(
        '🚨 Test Pump Notification',
        'This is a test notification for the water pump monitoring system using FCM HTTP v1.'
    );
    echo "Test notification sent. Check laravel.log for status.\n";
}
