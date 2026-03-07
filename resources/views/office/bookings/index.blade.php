@extends('layouts.app')

@section('content')
<table class="table table-striped">
<tr>
    <th>المسافر</th>
    <th>الرحلة</th>
    <th>المكتب</th>
    <th>المقاعد</th>
    <th>الحالة</th>
    <th>إجراء</th>
</tr>

@foreach($bookings as $booking)
<tr>
    <td>{{ $booking->traveler->name }}</td>
    <td>{{ $booking->flight->from }} → {{ $booking->flight->to }}</td>
    <td>{{ $booking->flight->office_name }}</td>
    <td>{{ $booking->seats_booked }}</td>
    <td>{{ $booking->status }}</td>
    <td>
        <form method="POST" action="/bookings/{{ $booking->id }}/status">
            @csrf
            <select name="status" class="form-select mb-1">
                <option value="confirmed">تأكيد</option>
                <option value="rejected">رفض</option>
            </select>
            <button class="btn btn-sm btn-primary">تحديث</button>
        </form>
    </td>
</tr>
@endforeach
</table>

@endsection
