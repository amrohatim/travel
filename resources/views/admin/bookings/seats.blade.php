@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1">Booking Seats</h1>
        <p class="mb-0 text-secondary">Serial: {{ $booking->serial_number ?: '—' }}</p>
    </div>
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-mono">Back</a>
</div>

<div class="panel">
    @if ($seats->count())
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Traveler Name</th>
                        <th>Phone</th>
                        <th>Booking Serial</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($seats as $seat)
                        <tr>
                            <td>{{ $seat->id }}</td>
                            <td>{{ $seat->traveler_name ?: $seat->traveler?->name ?: '—' }}</td>
                            <td>{{ $seat->traveler?->phone ?: '—' }}</td>
                            <td>{{ $seat->booking?->serial_number ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $seats->links() }}</div>
    @else
        <p class="mb-0 text-secondary">No seats found for this booking.</p>
    @endif
</div>
@endsection
