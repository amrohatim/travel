@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Add Future Flights</h1>
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

    <form method="GET" action="{{ route('admin.flights.future.create') }}" class="row g-3 mb-4" id="office-preview-form">
        <div class="col-md-6">
            <label class="form-label">Office</label>
            <select name="office_id" class="form-select" id="office-preview-select">
                <option value="">Select office to preview flights</option>
                @foreach ($offices as $office)
                    <option value="{{ $office->id }}" @selected((string) old('office_id', request('office_id')) === (string) $office->id)>{{ $office->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.flights.future.store') }}" class="row g-3">
        @csrf

        <div class="col-md-6">
            <label class="form-label">Office</label>
            <select name="office_id" class="form-select" required>
                <option value="">Select office</option>
                @foreach ($offices as $office)
                    <option value="{{ $office->id }}" @selected((string) old('office_id', request('office_id')) === (string) $office->id)>{{ $office->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Days Ahead</label>
            <select name="days_ahead" class="form-select" required>
                @foreach ([30, 60, 90] as $daysAhead)
                    <option value="{{ $daysAhead }}" @selected((string) old('days_ahead', '30') === (string) $daysAhead)>{{ $daysAhead }} days</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">From</label>
            <select name="from" class="form-select" required>
                <option value="">Select departure state</option>
                @foreach ($states as $state)
                    <option value="{{ $state->name }}" @selected(old('from') === $state->name)>{{ $state->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">To</label>
            <select name="to" class="form-select" required>
                <option value="">Select destination state</option>
                @foreach ($states as $state)
                    <option value="{{ $state->name }}" @selected(old('to') === $state->name)>{{ $state->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Departure Time</label>
            <input type="datetime-local" name="departure_time" class="form-control" value="{{ old('departure_time') }}" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Price</label>
            <input type="number" name="price" class="form-control" min="0" value="{{ old('price') }}" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Seats</label>
            <input type="number" name="seats" class="form-control" min="1" value="{{ old('seats') }}" required>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-mono">Generate Future Flights</button>
        </div>
    </form>

    @if ($selectedOffice)
        <div class="mt-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0">{{ $selectedOffice->name }} Flights</h2>
                <span class="text-secondary small">Latest flights for this office</span>
            </div>

            @if ($officeFlights && $officeFlights->count())
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($officeFlights as $flight)
                                <tr>
                                    <td>{{ $flight->from }}</td>
                                    <td>{{ $flight->to }}</td>
                                    <td>{{ $flight->travel_date }}</td>
                                    <td>{{ $flight->departure_time ?: '—' }}</td>
                                    <td>{{ $flight->price }}</td>
                                    <td>{{ $flight->seats }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $officeFlights->links() }}</div>
            @else
                <p class="mb-0 text-secondary">No flights found for this office.</p>
            @endif
        </div>
    @endif
</div>

<script>
    (() => {
        const officePreviewSelect = document.getElementById('office-preview-select');
        const officePreviewForm = document.getElementById('office-preview-form');

        if (!officePreviewSelect || !officePreviewForm) {
            return;
        }

        officePreviewSelect.addEventListener('change', () => {
            officePreviewForm.submit();
        });
    })();
</script>
@endsection
