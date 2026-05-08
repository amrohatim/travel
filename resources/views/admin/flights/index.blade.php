@extends('layouts.admin')

@section('content')
<h1 class="h4 mb-4">Flights</h1>

<div class="panel">
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
                        <tr>
                            <td>
                                <input type="checkbox" value="{{ $flight->id }}" class="flight-checkbox">
                            </td>
                            <td>{{ $flight->from }}</td>
                            <td>{{ $flight->to }}</td>
                            <td>{{ $flight->travel_date }}</td>
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
