<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeMessage;
use Illuminate\Http\JsonResponse;

class HomeMessageController extends Controller
{
    public function index(): JsonResponse
    {
        $homeMessages = HomeMessage::query()
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'message' => 'Home messages retrieved successfully',
            'data' => $homeMessages->map(fn (HomeMessage $homeMessage) => [
                'id' => $homeMessage->id,
                'image' => $homeMessage->imageUrl(),
                'title' => $homeMessage->title,
                'description' => $homeMessage->description,
            ])->values(),
        ]);
    }
}
