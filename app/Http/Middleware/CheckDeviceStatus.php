<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDeviceStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_suspended) {
            return $this->suspendedResponse(
                'Account suspended',
                'This account has been suspended.',
                $user->suspension_reason,
            );
        }

        $deviceId = $this->resolveDeviceId($request);

        if ($deviceId === null) {
            return response()->json([
                'message' => 'Device ID is required.',
                'errors' => [
                    'device_id' => ['Device ID is required.'],
                ],
            ], 422);
        }

        $device = Device::query()->where('device_id', $deviceId)->first();

        if ($device && $device->is_suspended) {
            return $this->suspendedResponse(
                'Device suspended',
                'This device has been suspended.',
                $device->suspension_reason,
            );
        }

        return $next($request);
    }

    private function resolveDeviceId(Request $request): ?string
    {
        $deviceId = $request->header('X-Device-ID') ?? $request->input('device_id');
        $deviceId = is_string($deviceId) ? trim($deviceId) : '';

        return $deviceId !== '' ? $deviceId : null;
    }

    private function suspendedResponse(string $message, string $detail, ?string $reason): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'error' => $detail,
            'reason' => $reason,
        ], 403);
    }
}
