@extends('layouts.app')

@section('content')
<h3>حجوزاتي</h3>

<table class="table table-bordered mt-3" dir="rtl">
<tr>
    <th>الرحلة</th>
    <th>التاريخ</th>
    <th>المقاعد</th>
    <th>الحالة</th>
    <th>التذكرة</th>
</tr>

@foreach($bookings as $booking)
<tr>
    <td>{{ $booking->flight->from }} → {{ $booking->flight->to }}</td>
    <td>{{ $booking->flight->travel_date }}</td>
    <td>{{ $booking->seats_booked }}</td>
    <td>
        <span class="badge 
            @if($booking->status == 'confirmed') bg-success
            @elseif($booking->status == 'rejected') bg-danger
            @else bg-warning
            @endif">
            {{ $booking->status }}
        </span>
    </td>
    <td>
        @if($booking->status == 'confirmed')
            <a href="/ticket/{{ $booking->id }}" class="btn btn-sm btn-primary">
                PDF
            </a>
        @endif
    </td>
</tr>
@endforeach
</table>
@endsection
