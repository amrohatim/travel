@extends('layouts.admin')

@section('content')
<h1 class="h4 mb-4">Bookings</h1>

<div class="panel">
    @if ($bookings->count())
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Traveler</th>
                        <th>Flight</th>
                        <th>Office</th>
                        <th>Seats</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $booking)
                        <tr>
                            <td>{{ $booking->serial_number }}</td>
                            <td>{{ $booking->traveler?->name ?: '—' }}</td>
                            <td>{{ $booking->flight ? $booking->flight->from.' → '.$booking->flight->to : '—' }}</td>
                            <td>{{ $booking->office?->name ?: $booking->flight?->office_name ?: '—' }}</td>
                            <td>{{ $booking->seats_booked }}</td>
                            <td>{{ $booking->total }}</td>
                            <td>{{ $booking->status }}</td>
                            <td>{{ $booking->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $bookings->links() }}</div>
    @else
        <p class="mb-0 text-secondary">No bookings found.</p>
    @endif
</div>
@endsection
