<?php

namespace App\Services;

use App\Models\FcmToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
     * Send notification payload using Firebase HTTP v1 API (or Legacy as fallback).
     */
    protected static function sendNotification(array $tokens, $title, $body, $data = [])
    {
        // Try to load Firebase credentials for HTTP v1
        $credentialsPath = env('FIREBASE_CREDENTIALS');
        if ($credentialsPath) {
            $absolutePath = base_path($credentialsPath);
            if (file_exists($absolutePath)) {
                self::sendHttpV1($tokens, $title, $body, $data, $absolutePath);
                return;
            } else {
                Log::warning("FCM: Credentials file not found at $absolutePath. Falling back to Legacy API.");
            }
        }

        // Fallback to legacy API
        self::sendLegacy($tokens, $title, $body, $data);
    }

    /**
     * Send using FCM HTTP v1 API.
     */
    protected static function sendHttpV1(array $tokens, $title, $body, $data, $credentialsPath)
    {
        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (!$credentials || !isset($credentials['project_id'])) {
                Log::error('FCM HTTP v1: Invalid service account credentials JSON structure.');
                return;
            }

            $projectId = $credentials['project_id'];
            $accessToken = self::getGoogleAccessToken($credentials);

            if (!$accessToken) {
                Log::error('FCM HTTP v1: Could not obtain Google OAuth2 access token.');
                return;
            }

            $successCount = 0;
            $failCount = 0;

            // HTTP v1 sends messages individually per token
            foreach ($tokens as $token) {
                $payload = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map(function($val) {
                            return is_array($val) ? json_encode($val) : (string)$val;
                        }, array_merge([
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'title' => $title,
                            'body' => $body,
                        ], $data)),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'pump_notifications',
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

                if ($response->successful()) {
                    $successCount++;
                } else {
                    $failCount++;
                    Log::error("FCM HTTP v1: Failed to send to token $token. Response: " . $response->body());
                }
            }

            Log::info("FCM HTTP v1: Dispatched push notifications. Success: $successCount, Failed: $failCount.");

        } catch (\Exception $e) {
            Log::error('FCM HTTP v1: Error sending notification: ' . $e->getMessage());
        }
    }

    /**
     * Generate or fetch cached Google OAuth2 Access Token for FCM HTTP v1.
     */
    protected static function getGoogleAccessToken(array $credentials)
    {
        $cacheKey = 'fcm_google_access_token_' . md5($credentials['client_email']);

        return Cache::remember($cacheKey, 3000, function () use ($credentials) {
            $time = time();
            
            // JWT Header
            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            
            // JWT Claim set
            $claimSet = json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $time + 3600,
                'iat' => $time,
            ]);

            // Base64Url encode helper
            $base64UrlHeader = self::base64UrlEncode($header);
            $base64UrlClaimSet = self::base64UrlEncode($claimSet);

            // Sign the data
            $signatureInput = $base64UrlHeader . '.' . $base64UrlClaimSet;
            $signature = '';
            
            $privateKey = $credentials['private_key'];
            if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('FCM OAuth2: Signing JWT assertion failed.');
                return null;
            }

            $base64UrlSignature = self::base64UrlEncode($signature);
            $assertion = $signatureInput . '.' . $base64UrlSignature;

            // Exchange JWT assertion for OAuth2 Access Token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('FCM OAuth2: Token exchange failed. Response: ' . $response->body());
            return null;
        });
    }

    /**
     * Base64 URL-safe encoding helper.
     */
    protected static function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Send using legacy FCM API (deprecated fallback).
     */
    protected static function sendLegacy(array $tokens, $title, $body, $data = [])
    {
        $serverKey = env('FCM_SERVER_KEY');

        if (!$serverKey) {
            Log::warning('FCM: Server Key (FCM_SERVER_KEY) is not set in env. Skipping notification send.');
            return;
        }

        Log::warning('FCM: Using deprecated Legacy API fallback. Please configure service account credentials for HTTP v1.');

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => '1',
            ],
            'data' => array_map(function($val) {
                return is_array($val) ? json_encode($val) : (string)$val;
            }, array_merge([
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'title' => $title,
                'body' => $body,
            ], $data)),
            'priority' => 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            if ($response->successful()) {
                Log::info('FCM Legacy: Push notification sent successfully to ' . count($tokens) . ' devices.');
            } else {
                Log::error('FCM Legacy: Failed to send notification. Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('FCM Legacy: Error sending notification: ' . $e->getMessage());
        }
    }
}
