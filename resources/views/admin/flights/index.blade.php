@extends('layouts.admin')

@section('content')
<h1 class="h4 mb-4">Flights</h1>

<div class="panel">
    @if ($flights->count())
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Travel Date</th>
                        <th>Departure</th>
                        <th>Price</th>
                        <th>Seats</th>
                        <th>Office</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($flights as $flight)
                        <tr>
                            <td>{{ $flight->from }}</td>
                            <td>{{ $flight->to }}</td>
                            <td>{{ $flight->travel_date }}</td>
                            <td>{{ $flight->departure_time ?: '—' }}</td>
                            <td>{{ $flight->price }}</td>
                            <td>{{ $flight->seats }}</td>
                            <td>{{ $flight->office_name ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $flights->links() }}</div>
    @else
        <p class="mb-0 text-secondary">No flights found.</p>
    @endif
</div>
@endsection
