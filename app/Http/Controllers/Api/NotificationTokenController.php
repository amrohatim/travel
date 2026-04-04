<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:android'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = (int) $request->user()->id;
        $token = $request->string('token')->toString();
        $platform = $request->string('platform')->toString();

        // Keep exactly one active token per office user.
        UserDeviceToken::where('user_id', $userId)
            ->where('fcm_token', '!=', $token)
            ->delete();

        // If this token was previously associated to another user, move ownership.
        UserDeviceToken::where('fcm_token', $token)
            ->where('user_id', '!=', $userId)
            ->delete();

        UserDeviceToken::updateOrCreate(
            ['user_id' => $userId],
            ['fcm_token' => $token, 'platform' => $platform],
        );

        return response()->json([
            'message' => 'Notification token saved successfully',
            'data' => null,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        UserDeviceToken::where('user_id', $request->user()->id)
            ->where('fcm_token', $request->string('token')->toString())
            ->delete();

        return response()->json([
            'message' => 'Notification token removed successfully',
            'data' => null,
        ]);
    }
}
