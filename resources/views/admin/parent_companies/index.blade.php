@extends('layouts.admin')

@section('content')
<h1 class="h4 mb-4">Parent Companies</h1>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="panel">
            <h2 class="h6 mb-3">Add New Parent Company</h2>

            @if ($errors->any())
                <div class="alert alert-light border border-dark">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.parent-companies.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="name">Parent Company Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="image">Parent Company Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                </div>

                <button type="submit" class="btn btn-mono w-100">Add Parent Company</button>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="panel">
            <h2 class="h6 mb-3">Parent Company List</h2>

            @if (isset($parentCompanies) && $parentCompanies->count())
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Image</th>
                                <th>QR Code</th>
                                <th>Update Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($parentCompanies as $parentCompany)
                                @php
                                    $imageUrl = $parentCompany->image;
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
                                    <td>{{ $parentCompany->id }}</td>
                                    <td>{{ $parentCompany->name }}</td>
                                    <td>
                                        @if ($parentCompany->image)
                                            <div class="mb-1">
                                                <img src="{{ $imageUrl }}" alt="{{ $parentCompany->name }}" style="width: 64px; height: 64px; object-fit: cover; border: 1px solid #000;">
                                            </div>
                                            <a href="{{ $imageUrl }}" target="_blank" class="text-dark">Open</a>
                                        @else
                                            <span class="text-secondary">No image</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-2">
                                            <img src="{{ route('admin.parent-companies.qr.preview', $parentCompany) }}" alt="QR code for {{ $parentCompany->name }}" style="width: 96px; height: 96px; border: 1px solid #000;">
                                            <a href="{{ route('admin.parent-companies.qr.download', $parentCompany) }}" class="btn btn-sm btn-outline-mono">Download PNG</a>
                                            <a href="{{ $parentCompany->publicUrl() }}" target="_blank" class="text-dark">Open Landing Page</a>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.parent-companies.image.update', $parentCompany) }}" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                            @csrf
                                            <input type="file" class="form-control form-control-sm" name="image" accept="image/*" required>
                                            <button type="submit" class="btn btn-sm btn-outline-mono">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-secondary mb-0">No parent companies added yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection
