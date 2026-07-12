@extends('layouts.admin')

@section('content')
<h1 class="h4 mb-4">Home Messages</h1>

@if ($errors->any())
    <div class="alert alert-light border border-dark">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="panel">
            <h2 class="h6 mb-3">Add New Home Message</h2>

            <form method="POST" action="{{ route('admin.home-messages.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="title">Title</label>
                    <input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="image">Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                </div>

                <button type="submit" class="btn btn-mono w-100">Add Home Message</button>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="panel">
            <h2 class="h6 mb-3">Home Message List</h2>

            @if (isset($homeMessages) && $homeMessages->count())
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Content</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($homeMessages as $homeMessage)
                                <tr>
                                    <td>{{ $homeMessage->id }}</td>
                                    <td style="min-width: 320px;">
                                        <form method="POST" action="{{ route('admin.home-messages.update', $homeMessage) }}" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                name="title"
                                                value="{{ old('title', $homeMessage->title) }}"
                                                required
                                            >
                                            <textarea
                                                class="form-control form-control-sm"
                                                name="description"
                                                rows="4"
                                                required
                                            >{{ old('description', $homeMessage->description) }}</textarea>
                                            <input type="file" class="form-control form-control-sm" name="image" accept="image/*">
                                            <button type="submit" class="btn btn-sm btn-outline-mono">Save</button>
                                        </form>
                                    </td>
                                    <td>
                                        @if ($homeMessage->imageUrl())
                                            <div class="d-flex flex-column gap-2">
                                                <img src="{{ $homeMessage->imageUrl() }}" alt="{{ $homeMessage->title }}" style="width: 96px; height: 96px; object-fit: cover; border: 1px solid #000;">
                                                <a href="{{ $homeMessage->imageUrl() }}" target="_blank" class="text-dark">Open</a>
                                            </div>
                                        @else
                                            <span class="text-secondary">No image</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.home-messages.destroy', $homeMessage) }}" onsubmit="return confirm('Delete this home message?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-secondary mb-0">No home messages added yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection
