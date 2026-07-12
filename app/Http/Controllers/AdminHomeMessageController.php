<?php

namespace App\Http\Controllers;

use App\Models\HomeMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminHomeMessageController extends Controller
{
    public function index(): View
    {
        $homeMessages = HomeMessage::query()
            ->orderByDesc('id')
            ->get();

        return view('admin.home_messages.index', compact('homeMessages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('home-messages', 'public');
        }

        HomeMessage::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.home-messages.index')->with('success', 'Home message added successfully.');
    }

    public function update(Request $request, HomeMessage $homeMessage): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = $homeMessage->image;
        if ($request->hasFile('image')) {
            $this->deleteLocalImage($homeMessage->image);
            $imagePath = $request->file('image')->store('home-messages', 'public');
        }

        $homeMessage->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.home-messages.index')->with('success', 'Home message updated successfully.');
    }

    public function destroy(HomeMessage $homeMessage): RedirectResponse
    {
        $this->deleteLocalImage($homeMessage->image);
        $homeMessage->delete();

        return redirect()->route('admin.home-messages.index')->with('success', 'Home message deleted successfully.');
    }

    private function deleteLocalImage(?string $image): void
    {
        if (! $image || str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return;
        }

        $oldPath = ltrim($image, '/');
        if (str_starts_with($oldPath, 'storage/')) {
            $oldPath = substr($oldPath, strlen('storage/'));
        }

        Storage::disk('public')->delete($oldPath);
    }
}
