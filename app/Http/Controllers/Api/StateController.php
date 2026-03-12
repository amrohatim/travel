<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StateController extends Controller
{
    public function index(): JsonResponse
    {
        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name', 'image']);

        return response()->json([
            'message' => 'States retrieved successfully',
            'data' => $states->map(fn (State $state) => [
                'id' => $state->id,
                'name' => $state->name,
                'image' => $this->imageUrl($state->image),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:states,name'],
            'image' => ['nullable', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $state = State::create([
            'name' => $request->string('name')->toString(),
            'image' => $request->filled('image')
                ? $request->string('image')->toString()
                : null,
        ]);

        return response()->json([
            'message' => 'State created successfully',
            'data' => [
                'id' => $state->id,
                'name' => $state->name,
                'image' => $this->imageUrl($state->image),
            ],
        ], 201);
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

        // States are stored on the public disk as "states/..." paths.
        return url('storage/'.$cleanImage);
    }
}
