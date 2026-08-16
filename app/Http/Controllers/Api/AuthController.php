<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\d{10}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_id' => ['required', 'string', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($response = $this->ensureDeviceAllowed($request)) {
            return $response;
        }

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'phone' => $request->string('phone')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'role' => 'traveler',
        ]);

        $this->syncDevice($request, $user);

        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully',
            'data' => [
                'token' => $token,
                'user' => $this->userPayload($user),
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($response = $this->ensureDeviceAllowed($request)) {
            return $response;
        }

        $user = User::where('phone', $request->string('phone')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'phone' => ['The provided credentials are incorrect.'],
                ],
            ], 422);
        }

        if ($user->is_suspended) {
            return $this->suspendedResponse(
                'Account suspended',
                'This account has been suspended.',
                $user->suspension_reason,
            );
        }

        $this->syncDevice($request, $user);

        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'data' => [
                'token' => $token,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully',
            'data' => null,
        ]);
    }

    public function updateTravelerPassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $validator->after(function ($validator) use ($request, $user): void {
            if (! $user || ! Hash::check($request->string('current_password')->toString(), $user->password)) {
                $validator->errors()->add('current_password', 'The current password is incorrect.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        return response()->json([
            'message' => 'Traveler password updated successfully',
            'data' => null,
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'image' => $user->image,
        ];
    }

    private function ensureDeviceAllowed(Request $request): ?JsonResponse
    {
        $device = Device::query()
            ->where('device_id', $request->string('device_id')->toString())
            ->first();

        if (! $device || ! $device->is_suspended) {
            return null;
        }

        return $this->suspendedResponse(
            'Device suspended',
            'This device has been suspended.',
            $device->suspension_reason,
        );
    }

    private function syncDevice(Request $request, User $user): void
    {
        Device::query()->updateOrCreate(
            ['device_id' => $request->string('device_id')->toString()],
            [
                'user_id' => $user->id,
                'device_model' => $request->filled('device_model')
                    ? $request->string('device_model')->toString()
                    : null,
                'platform' => $request->filled('platform')
                    ? $request->string('platform')->toString()
                    : null,
            ],
        );
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
