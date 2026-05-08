@extends('layouts.admin')

@section('content')
<h1 class="h4 mb-4">Bookings</h1>

<div class="panel">
    @if ($bookings->count())
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all-bookings">
                        </th>
                        <th>Serial</th>
                        <th>Traveler</th>
                        <th>Flight</th>
                        <th>Office</th>
                        <th>Seats</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $booking)
                        <tr>
                            <td>
                                <input type="checkbox" value="{{ $booking->id }}" class="booking-checkbox">
                            </td>
                            <td>{{ $booking->serial_number }}</td>
                            <td>{{ $booking->traveler?->name ?: '—' }}</td>
                            <td>{{ $booking->flight ? $booking->flight->from.' → '.$booking->flight->to : '—' }}</td>
                            <td>{{ $booking->office?->name ?: $booking->flight?->office_name ?: '—' }}</td>
                            <td>{{ $booking->seats_booked }}</td>
                            <td>{{ $booking->total }}</td>
                            <td>{{ $booking->status }}</td>
                            <td>{{ $booking->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" class="d-inline" onsubmit="return confirm('Delete this booking and related seats?');">
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

        <form id="bulk-booking-delete-form" method="POST" action="{{ route('admin.bookings.bulk-destroy') }}" class="mt-3 d-flex gap-2">
            @csrf
            <div id="bulk-booking-inputs"></div>
            <button type="submit" class="btn btn-outline-mono" id="bulk-delete-bookings-btn">Delete Selected</button>
        </form>

        <script>
            (() => {
                const form = document.getElementById('bulk-booking-delete-form');
                if (!form) return;
                const selectAll = document.getElementById('select-all-bookings');
                const checkboxes = Array.from(document.querySelectorAll('.booking-checkbox'));
                const bulkButton = document.getElementById('bulk-delete-bookings-btn');
                const bulkInputs = document.getElementById('bulk-booking-inputs');

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
                        alert('Select at least one booking.');
                        return;
                    }
                    bulkInputs.innerHTML = selected
                        .map((id) => `<input type="hidden" name="ids[]" value="${id}">`)
                        .join('');

                    if (!confirm('Delete selected bookings and related seats?')) {
                        event.preventDefault();
                    }
                });

                updateButtonState();
            })();
        </script>

        <div class="mt-3">{{ $bookings->links() }}</div>
    @else
        <p class="mb-0 text-secondary">No bookings found.</p>
    @endif
</div>
@endsection
