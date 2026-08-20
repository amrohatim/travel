<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActiveOfficeContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class OfficeController extends Controller
{
    public function __construct(
        private readonly ActiveOfficeContext $activeOfficeContext
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['parentCompany', 'location'])
            ->leftJoin('parent_companies', 'parent_companies.id', '=', 'users.parent_company_id')
            ->where('role', 'office')
            ->orderByRaw('CASE WHEN parent_companies.name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('parent_companies.name')
            ->orderBy('users.name');

        if ($request->user()?->role === 'support') {
            $query->whereIn('users.id', $request->user()->assignedOffices()->select('users.id'));
        }

        $offices = $query->get([
                'users.id',
                'users.name',
                'users.image',
                'users.bankak_name',
                'users.bankak_number',
                'users.parent_company_id',
            ]);

        return response()->json([
            'message' => 'Offices retrieved successfully',
            'data' => $offices->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'image' => $this->imageUrl($user->image),
                'bankak_name' => $user->bankak_name,
                'bankak_number' => $user->bankak_number,
                'parent_company_id' => $user->parent_company_id,
                'parent_company_name' => $user->parentCompany?->name,
                'parent_company_image' => $this->imageUrl($user->parentCompany?->image),
                'location' => $this->locationPayload($user),
            ])->values(),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $this->activeOfficeContext->resolve($request);

        return response()->json([
            'message' => 'Office profile retrieved successfully',
            'data' => $this->officePayload($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->activeOfficeContext->resolve($request);
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
            'phone' => ['nullable', 'regex:/^(?:\d{10}|0\d{10})$/'],
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

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $this->activeOfficeContext->resolve($request);

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $validator->after(function ($validator) use ($request, $user): void {
            if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
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
            'message' => 'Office password updated successfully',
            'data' => null,
        ]);
    }

    private function officePayload(User $user): array
    {
        $user->loadMissing(['state', 'location']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'image' => $this->imageUrl($user->image),
            'bankak_name' => $user->bankak_name,
            'bankak_number' => $user->bankak_number,
            'state_id' => $user->state_id,
            'state_name' => $user->state?->name,
            'location' => $this->locationPayload($user),
        ];
    }

    private function locationPayload(User $user): ?array
    {
        if (! $user->relationLoaded('location')) {
            $user->load('location');
        }

        if (! $user->location) {
            return null;
        }

        return [
            'lat' => (float) $user->location->lat,
            'lng' => (float) $user->location->lng,
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
