@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Flights</h1>
    <a href="{{ route('admin.flights.future.create') }}" class="btn btn-mono">Add Future Flights</a>
</div>

<div class="panel">
    <form method="GET" action="{{ route('admin.flights.index') }}" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">From</label>
            <select name="from" class="form-select">
                <option value="">All departure states</option>
                @foreach ($states as $state)
                    <option value="{{ $state->name }}" @selected(request('from') === $state->name)>{{ $state->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">To</label>
            <select name="to" class="form-select">
                <option value="">All destination states</option>
                @foreach ($states as $state)
                    <option value="{{ $state->name }}" @selected(request('to') === $state->name)>{{ $state->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Travel Date</label>
            <input type="date" name="travel_date" class="form-control" value="{{ request('travel_date') }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Office</label>
            <select name="office_id" class="form-select">
                <option value="">All offices</option>
                @foreach ($offices as $office)
                    <option value="{{ $office->id }}" @selected((string) request('office_id') === (string) $office->id)>{{ $office->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-mono">Filter</button>
            <a href="{{ route('admin.flights.index') }}" class="btn btn-outline-mono">Reset</a>
        </div>
    </form>

    @if ($flights->count())
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all-flights">
                        </th>
                        <th>From</th>
                        <th>To</th>
                        <th>Travel Date</th>
                        <th>Departure</th>
                        <th>Price</th>
                        <th>Seats</th>
                        <th>Office</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($flights as $flight)
                        @php
                            $flightDate = \Carbon\Carbon::parse($flight->travel_date);
                            $isToday = $flightDate->toDateString() === \Carbon\Carbon::today(config('app.timezone'))->toDateString();
                        @endphp
                        <tr @if ($isToday) style="background: #f97316; color: #ffffff;" @endif>
                            <td>
                                <input type="checkbox" value="{{ $flight->id }}" class="flight-checkbox">
                            </td>
                            <td>{{ $flight->from }}</td>
                            <td>{{ $flight->to }}</td>
                            <td>{{ $flightDate->toDateString() }} ({{ $flightDate->format('l') }})</td>
                            <td>{{ $flight->departure_time ?: '—' }}</td>
                            <td>{{ $flight->price }}</td>
                            <td>{{ $flight->seats }}</td>
                            <td>{{ $flight->office_name ?: '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.flights.seats', $flight) }}" class="btn btn-sm btn-outline-mono">View Seats</a>
                                <form method="POST" action="{{ route('admin.flights.destroy', $flight) }}" class="d-inline" onsubmit="return confirm('Delete this flight and all related bookings/seats?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-mono">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form id="bulk-flight-delete-form" method="POST" action="{{ route('admin.flights.bulk-destroy') }}" class="mt-3 d-flex gap-2">
            @csrf
            <div id="bulk-flight-inputs"></div>
            <button type="submit" class="btn btn-outline-mono" id="bulk-delete-flights-btn">Delete Selected</button>
        </form>

        <script>
            (() => {
                const form = document.getElementById('bulk-flight-delete-form');
                if (!form) return;
                const selectAll = document.getElementById('select-all-flights');
                const checkboxes = Array.from(document.querySelectorAll('.flight-checkbox'));
                const bulkButton = document.getElementById('bulk-delete-flights-btn');
                const bulkInputs = document.getElementById('bulk-flight-inputs');

                const updateButtonState = () => {
                    const anyChecked = checkboxes.some((checkbox) => checkbox.checked);
                    bulkButton.disabled = !anyChecked;
                };

                selectAll?.addEventListener('change', () => {
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                    updateButtonState();
                });

                checkboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', updateButtonState);
                });

                form.addEventListener('submit', (event) => {
                    const selected = checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
                    if (selected.length === 0) {
                        event.preventDefault();
                        alert('Select at least one flight.');
                        return;
                    }
                    bulkInputs.innerHTML = selected
                        .map((id) => `<input type="hidden" name="ids[]" value="${id}">`)
                        .join('');

                    if (!confirm('Delete selected flights and all related bookings/seats?')) {
                        event.preventDefault();
                    }
                });

                updateButtonState();
            })();
        </script>

        <div class="mt-3">{{ $flights->links() }}</div>
    @else
        <p class="mb-0 text-secondary">No flights found.</p>
    @endif
</div>
@endsection
