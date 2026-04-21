<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class OfficeController extends Controller
{
    public function index(): JsonResponse
    {
        $offices = User::query()
            ->where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name', 'image', 'bankak_name', 'bankak_number']);

        return response()->json([
            'message' => 'Offices retrieved successfully',
            'data' => $offices->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'image' => $this->imageUrl($user->image),
                'bankak_name' => $user->bankak_name,
                'bankak_number' => $user->bankak_number,
            ])->values(),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'message' => 'Office profile retrieved successfully',
            'data' => $this->officePayload($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $normalize = static function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        };

        $request->merge([
            'phone' => $normalize($request->input('phone')),
            'bankak_name' => $normalize($request->input('bankak_name')),
            'bankak_number' => $normalize($request->input('bankak_number')),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^\d{10}$/'],
            'bankak_name' => ['nullable', 'string', 'max:255'],
            'bankak_number' => ['nullable', 'regex:/^\d+$/'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imagePath = $user->image;
        if ($request->hasFile('image')) {
            $newPath = $request->file('image')->store('users', 'public');
            if ($imagePath && trim($imagePath) !== '' && ! Str::startsWith($imagePath, ['http://', 'https://'])) {
                $cleanOldPath = ltrim($imagePath, '/');
                if (Str::startsWith($cleanOldPath, 'storage/')) {
                    $cleanOldPath = substr($cleanOldPath, strlen('storage/'));
                }
                if ($cleanOldPath !== '' && Storage::disk('public')->exists($cleanOldPath)) {
                    Storage::disk('public')->delete($cleanOldPath);
                }
            }
            $imagePath = $newPath;
        }

        $user->update([
            'name' => $request->string('name')->toString(),
            'phone' => $request->input('phone') !== null ? (string) $request->input('phone') : null,
            'bankak_name' => $request->filled('bankak_name')
                ? $request->string('bankak_name')->toString()
                : null,
            'bankak_number' => $request->input('bankak_number') !== null
                ? (int) $request->input('bankak_number')
                : null,
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Office profile updated successfully',
            'data' => $this->officePayload($user->fresh()),
        ]);
    }

    private function officePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'image' => $this->imageUrl($user->image),
            'bankak_name' => $user->bankak_name,
            'bankak_number' => $user->bankak_number,
        ];
    }

    private function imageUrl(?string $image): ?string
    {
        if (! $image || trim($image) === '') {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }
        $cleanImage = ltrim($image, '/');
        if (Str::startsWith($cleanImage, 'storage/')) {
            return url($cleanImage);
        }
        if (Storage::disk('public')->exists($cleanImage)) {
            return url('storage/'.$cleanImage);
        }
        return url($cleanImage);
    }
}
