@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Add Flight</h1>
    <a href="{{ route('admin.flights.index') }}" class="btn btn-outline-mono">Back</a>
</div>

<div class="panel" style="max-width: 900px;">
    @if ($errors->any())
        <div class="alert alert-light border border-dark">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.flights.store') }}" class="row g-3">
        @csrf
        @include('admin.flights._form')

        <div class="col-12">
            <button type="submit" class="btn btn-mono">Create Flight</button>
        </div>
    </form>
</div>
@endsection
