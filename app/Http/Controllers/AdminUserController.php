<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\OfficeLocation;
use App\Models\ParentCompany;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['parentCompany', 'state', 'assignedOffices:id,name', 'devices' => function ($query): void {
                $query->latest();
            }])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $parentCompanies = ParentCompany::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $offices = User::query()
            ->where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.users.create', compact('parentCompanies', 'states', 'offices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'bankak_name' => ['nullable', 'string', 'max:255'],
            'bankak_number' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'role' => ['required', Rule::in(['admin', 'office', 'traveler', 'support'])],
            'parent_company_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'office'),
                'nullable',
                'integer',
                'exists:parent_companies,id',
            ],
            'office_ids' => [
                Rule::requiredIf(fn () => $request->input('role') === 'support'),
                'array',
            ],
            'office_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'office')),
            ],
            'state_id' => [
                'nullable',
                'integer',
                'exists:states,id',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->validateOfficeLocationPair($validator, $request);

        $validated = $validator->validate();

        if (($validated['role'] ?? null) !== 'office') {
            $validated['parent_company_id'] = null;
            $validated['state_id'] = null;
        }
        if (($validated['role'] ?? null) !== 'support') {
            $validated['office_ids'] = [];
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('users', 'public');
        }

        $userAttributes = collect($validated)
            ->except(['lat', 'lng', 'office_ids'])
            ->all();

        /** @var User $user */
        $user = User::create($userAttributes);
        $this->syncOfficeLocation($user, $validated);
        $this->syncSupportAssignments($user, $validated);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $parentCompanies = ParentCompany::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $offices = User::query()
            ->where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.users.edit', compact('user', 'parentCompanies', 'states', 'offices'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'bankak_name' => ['nullable', 'string', 'max:255'],
            'bankak_number' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'role' => ['required', Rule::in(['admin', 'office', 'traveler', 'support'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'parent_company_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'office'),
                'nullable',
                'integer',
                'exists:parent_companies,id',
            ],
            'office_ids' => [
                Rule::requiredIf(fn () => $request->input('role') === 'support'),
                'array',
            ],
            'office_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'office')),
            ],
            'state_id' => [
                'nullable',
                'integer',
                'exists:states,id',
            ],
        ]);

        $this->validateOfficeLocationPair($validator, $request);

        $validated = $validator->validate();

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        if (($validated['role'] ?? null) !== 'office') {
            $validated['parent_company_id'] = null;
            $validated['state_id'] = null;
        }
        if (($validated['role'] ?? null) !== 'support') {
            $validated['office_ids'] = [];
        }

        if ($request->hasFile('image')) {
            if (! empty($user->image) && ! str_starts_with($user->image, 'http://') && ! str_starts_with($user->image, 'https://')) {
                $oldPath = ltrim($user->image, '/');
                if (str_starts_with($oldPath, 'storage/')) {
                    $oldPath = substr($oldPath, strlen('storage/'));
                }
                Storage::disk('public')->delete($oldPath);
            }

            $validated['image'] = $request->file('image')->store('users', 'public');
        }

        $userAttributes = collect($validated)
            ->except(['lat', 'lng', 'office_ids'])
            ->all();

        $user->update($userAttributes);
        $this->syncOfficeLocation($user->fresh(), $validated, true);
        $this->syncSupportAssignments($user->fresh(), $validated);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot suspend your own account.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $timestamp = now();
        $reason = trim($validated['reason']);

        $user->update([
            'is_suspended' => true,
            'suspension_reason' => $reason,
            'suspended_at' => $timestamp,
        ]);

        $user->devices()->update([
            'is_suspended' => true,
            'suspension_reason' => $reason,
            'suspended_at' => $timestamp,
        ]);

        $user->tokens()->delete();

        return redirect()->route('admin.users.index')->with('success', 'User suspended successfully.');
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->update([
            'is_suspended' => false,
            'suspension_reason' => null,
            'suspended_at' => null,
        ]);

        Device::query()
            ->where('user_id', $user->id)
            ->update([
                'is_suspended' => false,
                'suspension_reason' => null,
                'suspended_at' => null,
            ]);

        return redirect()->route('admin.users.index')->with('success', 'User unsuspended successfully.');
    }

    private function validateOfficeLocationPair($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request): void {
            if ($request->input('role') !== 'office') {
                return;
            }

            $hasLat = $request->filled('lat');
            $hasLng = $request->filled('lng');

            if ($hasLat xor $hasLng) {
                $validator->errors()->add('lat', 'Latitude and longitude must both be provided together.');
                $validator->errors()->add('lng', 'Latitude and longitude must both be provided together.');
            }
        });
    }

    private function syncOfficeLocation(User $user, array $validated, bool $keepExistingOnBlank = false): void
    {
        if (($validated['role'] ?? null) !== 'office') {
            $user->location()->delete();
            return;
        }

        $hasLat = array_key_exists('lat', $validated) && $validated['lat'] !== null && $validated['lat'] !== '';
        $hasLng = array_key_exists('lng', $validated) && $validated['lng'] !== null && $validated['lng'] !== '';

        if (! $hasLat && ! $hasLng) {
            if (! $keepExistingOnBlank) {
                $user->location()->delete();
            }

            return;
        }

        OfficeLocation::updateOrCreate(
            ['office_id' => $user->id],
            [
                'lat' => (float) $validated['lat'],
                'lng' => (float) $validated['lng'],
            ],
        );
    }

    private function syncSupportAssignments(User $user, array $validated): void
    {
        if (($validated['role'] ?? null) !== 'support') {
            $user->assignedOffices()->sync([]);
            return;
        }

        $user->assignedOffices()->sync($validated['office_ids'] ?? []);
    }
}
