@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Fees</h1>
</div>

<div class="panel">
    @if ($offices->count())
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Office</th>
                        <th>Bookings</th>
                        <th>Seats</th>
                        <th>Invoice</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($offices as $office)
                        <tr>
                            <td>{{ $office->id }}</td>
                            <td>{{ $office->name }}</td>
                            <td>{{ (int) $office->payable_bookings_count }}</td>
                            <td>{{ (int) $office->payable_seats_sum }}</td>
                            <td>{{ (int) $office->invoice_amount }} SDG</td>
                            <td class="text-end">
                                @if ((int) $office->payable_bookings_count > 0)
                                    <form method="POST" action="{{ route('admin.fees.clear', $office) }}" class="d-inline" onsubmit="return confirm('Clear payable fees for this office?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-mono">Clear Fees</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-mono" disabled>Clear Fees</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mb-0 text-secondary">No offices found.</p>
    @endif
</div>
@endsection
