<?php

namespace App\Http\Controllers;

use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminStateController extends Controller
{
    public function index(): View
    {
        $states = State::query()
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', compact('states'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:states,name'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('states', 'public');
        }

        State::create([
            'name' => $validated['name'],
            'image' => $imagePath,
        ]);

        return redirect('/admin')->with('success', 'State added successfully.');
    }

    public function updateImage(Request $request, State $state): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        if (! empty($state->image) && ! str_starts_with($state->image, 'http://') && ! str_starts_with($state->image, 'https://')) {
            $oldPath = ltrim($state->image, '/');
            if (str_starts_with($oldPath, 'storage/')) {
                $oldPath = substr($oldPath, strlen('storage/'));
            }
            Storage::disk('public')->delete($oldPath);
        }

        $imagePath = $request->file('image')->store('states', 'public');
        $state->update([
            'image' => $imagePath,
        ]);

        return redirect('/admin')->with('success', "Image updated for state {$state->name}.");
    }
}
