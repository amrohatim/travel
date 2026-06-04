<?php

namespace App\Http\Controllers;

use App\Models\ParentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminParentCompanyController extends Controller
{
    public function index(): View
    {
        $parentCompanies = ParentCompany::query()
            ->orderBy('name')
            ->get();

        return view('admin.parent_companies.index', compact('parentCompanies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:parent_companies,name'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('parent-companies', 'public');
        }

        ParentCompany::create([
            'name' => $validated['name'],
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.parent-companies.index')->with('success', 'Parent company added successfully.');
    }

    public function updateImage(Request $request, ParentCompany $parentCompany): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        if (! empty($parentCompany->image) && ! str_starts_with($parentCompany->image, 'http://') && ! str_starts_with($parentCompany->image, 'https://')) {
            $oldPath = ltrim($parentCompany->image, '/');
            if (str_starts_with($oldPath, 'storage/')) {
                $oldPath = substr($oldPath, strlen('storage/'));
            }
            Storage::disk('public')->delete($oldPath);
        }

        $imagePath = $request->file('image')->store('parent-companies', 'public');
        $parentCompany->update([
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.parent-companies.index')->with('success', "Image updated for parent company {$parentCompany->name}.");
    }
}
