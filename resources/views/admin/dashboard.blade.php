@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-3">Add New State</h4>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="/admin/states" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="name">State Name</label>
                        <input
                            type="text"
                            class="form-control"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="image">State Image</label>
                        <input
                            type="file"
                            class="form-control"
                            id="image"
                            name="image"
                            accept="image/*"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Add State</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-3">States</h4>

                @if (isset($states) && $states->count())
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Image</th>
                                    <th>Update Image</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($states as $state)
                                    @php
                                        $imageUrl = $state->image;
                                        if ($imageUrl && !str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://')) {
                                            $cleanPath = ltrim($imageUrl, '/');
                                            if (str_starts_with($cleanPath, 'storage/')) {
                                                $imageUrl = url($cleanPath);
                                            } else {
                                                $imageUrl = url('storage/'.$cleanPath);
                                            }
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $state->id }}</td>
                                        <td>{{ $state->name }}</td>
                                        <td>
                                            @if ($state->image)
                                                <div class="mb-1">
                                                    <img src="{{ $imageUrl }}" alt="{{ $state->name }}" style="width: 64px; height: 64px; object-fit: cover; border-radius: 6px;">
                                                </div>
                                                <a href="{{ $imageUrl }}" target="_blank">{{ $state->image }}</a>
                                            @else
                                                <span class="text-muted">No image</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="/admin/states/{{ $state->id }}/image" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                                @csrf
                                                <input
                                                    type="file"
                                                    class="form-control form-control-sm"
                                                    name="image"
                                                    accept="image/*"
                                                    required
                                                >
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No states added yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
