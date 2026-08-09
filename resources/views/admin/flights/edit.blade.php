@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Edit Flight</h1>
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

    @if ($hasBookings)
        <div class="alert alert-light border border-dark">
            This flight already has bookings. Route, office, date, and seats are locked. Price and discount can still be updated for future bookings.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.flights.update', $flight) }}" class="row g-3">
        @csrf
        @method('PUT')
        @include('admin.flights._form')

        <div class="col-12">
            <button type="submit" class="btn btn-mono">Save Flight</button>
        </div>
    </form>
</div>
@endsection
