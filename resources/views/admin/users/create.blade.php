@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Create User</h1>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-mono">Back</a>
</div>

<div class="panel" style="max-width: 760px;">
    @if ($errors->any())
        <div class="alert alert-light border border-dark">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3" enctype="multipart/form-data">
        @csrf

        <div class="col-md-6">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" required>
                @foreach (['admin', 'office', 'traveler'] as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">Bankak Name</label>
            <input type="text" name="bankak_name" class="form-control" value="{{ old('bankak_name') }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">Bankak Number</label>
            <input type="text" name="bankak_number" class="form-control" value="{{ old('bankak_number') }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">Image</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>

        <div class="col-md-6">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-mono">Create User</button>
        </div>
    </form>
</div>
@endsection
