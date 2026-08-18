@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1">Booking Details</h1>
        <p class="mb-0 text-secondary">Serial: {{ $booking->serial_number ?: '—' }}</p>
    </div>
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-mono">Back</a>
</div>

<div class="d-grid gap-4">
    <div class="panel">
        <h2 class="h6 mb-3">Travelers / Passengers</h2>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="text-secondary small">Primary Traveler</div>
                <div class="fw-semibold">{{ $booking->traveler?->name ?: '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small">Phone</div>
                <div>{{ $booking->traveler?->phone ?: '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small">Email</div>
                <div>{{ $booking->traveler?->email ?: '—' }}</div>
            </div>
        </div>

        @if ($booking->seats->count())
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Seat Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($booking->seats as $seat)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $seat->traveler_name ?: $seat->traveler?->name ?: '—' }}</td>
                                <td>{{ $seat->traveler?->phone ?: $booking->traveler?->phone ?: '—' }}</td>
                                <td>{{ $seat->seat_number ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mb-0">Passenger names are not assigned on seat records yet.</p>
        @endif
    </div>

    <div class="panel">
        <h2 class="h6 mb-3">Flight Info</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-secondary small">Route</div>
                <div>{{ $booking->flight ? $booking->flight->from.' ➡️ '.$booking->flight->to : '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Travel Date</div>
                <div>{{ $booking->flight?->travel_date ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Departure Time</div>
                <div>{{ $booking->flight?->departure_time ? \Illuminate\Support\Carbon::parse($booking->flight->departure_time)->format('Y-m-d H:i') : '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Office</div>
                <div>{{ $booking->office?->name ?: $booking->flight?->office_name ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Office Phone</div>
                <div>{{ $booking->office?->phone ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Selected Seat Numbers</div>
                <div>{{ filled($booking->selected_seat_numbers) ? implode(', ', $booking->selected_seat_numbers) : '—' }}</div>
            </div>
        </div>
    </div>

    <div class="panel">
        <h2 class="h6 mb-3">Booking Info</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="text-secondary small">Status</div>
                <div>{{ $booking->status }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small">Seats Booked</div>
                <div>{{ $booking->seats_booked }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small">Total</div>
                <div>{{ $booking->total ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small">Created</div>
                <div>{{ $booking->created_at?->format('Y-m-d H:i') ?: '—' }}</div>
            </div>
        </div>

        <div>
            <div class="text-secondary small mb-2">Bankak Image</div>
            @if ($bookingImageUrl)
                <a href="{{ $bookingImageUrl }}" target="_blank" rel="noopener noreferrer">
                    <img src="{{ $bookingImageUrl }}" alt="Booking payment proof" style="max-width: 100%; width: 420px; border: 1px solid #000; object-fit: cover;">
                </a>
            @else
                <p class="mb-0">No Bankak image uploaded for this booking.</p>
            @endif
        </div>
    </div>
</div>
@endsection
