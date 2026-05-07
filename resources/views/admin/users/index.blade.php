@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-mono">Create User</a>
</div>

<div class="panel">
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
                        <th>Role</th>
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
                                    —
                                @endif
                            </td>
                            <td>{{ $user->email ?: '—' }}</td>
                            <td>{{ $user->phone ?: '—' }}</td>
                            <td>{{ $user->bankak_name ?: '—' }}</td>
                            <td>{{ $user->bankak_number ?: '—' }}</td>
                            <td>{{ $user->role }}</td>
                            <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-mono">Edit</a>
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
