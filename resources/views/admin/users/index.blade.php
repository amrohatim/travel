@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-mono">Create User</a>
</div>

<div class="panel">
    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end mb-4">
        <div class="col-md-5">
            <label for="user-search" class="form-label">Search</label>
            <input
                type="text"
                name="search"
                id="user-search"
                class="form-control"
                value="{{ $search }}"
                placeholder="Search by name or phone"
            >
        </div>
        <div class="col-md-3">
            <label for="user-role" class="form-label">Role</label>
            <select name="role" id="user-role" class="form-select">
                <option value="">All roles</option>
                @foreach ($roles as $roleOption)
                    <option value="{{ $roleOption }}" @selected($role === $roleOption)>{{ ucfirst($roleOption) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-mono">Filter</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-mono">Reset</a>
        </div>
    </form>

    @if ($users->count())
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Image</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Bankak Name</th>
                        <th>Bankak Number</th>
                        <th>Parent Company</th>
                        <th>State</th>
                        <th>Role</th>
                        <th>Assignments</th>
                        <th>Status</th>
                        <th>Devices</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->name }}</td>
                            <td>
                                @if ($user->image)
                                    @php
                                        $imageUrl = str_starts_with($user->image, 'http')
                                            ? $user->image
                                            : asset('storage/'.ltrim(str_replace('storage/', '', $user->image), '/'));
                                    @endphp
                                    <img src="{{ $imageUrl }}" alt="{{ $user->name }}" style="width: 40px; height: 40px; object-fit: cover; border: 1px solid #000;">
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $user->email ?: '-' }}</td>
                            <td>{{ $user->phone ?: '-' }}</td>
                            <td>{{ $user->bankak_name ?: '-' }}</td>
                            <td>{{ $user->bankak_number ?: '-' }}</td>
                            <td>{{ $user->role === 'office' ? ($user->parentCompany?->name ?: '-') : '-' }}</td>
                            <td>{{ $user->role === 'office' ? ($user->state?->name ?: '-') : '-' }}</td>
                            <td>{{ $user->role }}</td>
                            <td>{{ $user->role === 'support' ? $user->assignedOffices->count().' office(s)' : '-' }}</td>
                            <td>
                                @if ($user->is_suspended)
                                    <div class="text-danger fw-semibold">Suspended</div>
                                    <div class="small text-muted">{{ $user->suspension_reason ?: 'No reason provided' }}</div>
                                @else
                                    <span class="text-success fw-semibold">Active</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $latestDevice = $user->devices->first();
                                @endphp
                                <div>{{ $user->devices->count() }} known</div>
                                @if ($latestDevice)
                                    <div class="small text-muted">
                                        {{ $latestDevice->platform ?: 'unknown' }} / {{ $latestDevice->device_model ?: 'unknown device' }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-mono">Edit</a>
                                @if ($user->is_suspended)
                                    <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-mono">Unsuspend</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="d-inline" onsubmit="const reason = window.prompt('Suspension reason'); if (!reason || !reason.trim()) { return false; } this.querySelector('input[name=reason]').value = reason.trim(); return true;">
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button type="submit" class="btn btn-sm btn-outline-mono">Suspend</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-mono">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
    @else
        <p class="mb-0 text-secondary">No users found.</p>
    @endif
</div>
@endsection
