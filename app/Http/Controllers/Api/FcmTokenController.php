<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FcmToken;

class FcmTokenController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string',
        ]);

        $token = FcmToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id' => auth('sanctum')->id() ?: $request->user_id,
                'device_type' => $request->device_type ?: 'android',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'FCM Token registered successfully.',
            'data' => $token
        ]);
    }
}
