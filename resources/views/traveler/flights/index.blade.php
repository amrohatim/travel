@extends('layouts.app')

@section('content')
<h3 class="mb-4">الرحلات المتاحة</h3>

<form method="GET" action="/flights" class="row mb-4">
    <div class="col-4">
        <input type="date" name="date" class="form-control" required>
    </div>
    <div class="col">
        <button class="btn btn-primary">بحث بالتاريخ</button>
    </div>
</form>

@if($flights->count() == 0)
    <div class="alert alert-warning">لا توجد رحلات في هذا التاريخ</div>
@endif

<div class="row" dir="rtl">
@foreach($flights as $flight)
    <div class="col-md-4">
        <div class="card mb-3 shadow">
            <div class="card-body">
                <h1><p><strong>المكتب المسؤول:</strong> {{ $flight->office_name }}</p>
</h1>
                <h5>{{ $flight->from }} → {{ $flight->to }}</h5>
                <p class="text-muted">
                    التاريخ: {{ $flight->travel_date }} <br>
                    السعر: {{ $flight->price }} <br>
                    المقاعد: {{ $flight->seats }}
                </p>

                @if($flight->seats > 0)
                    <form method="POST" action="/flights/{{ $flight->id }}/book">
                        @csrf
                        
                        <input type="number" name="seats_booked" min="1" max="{{ $flight->seats }}" class="form-control mb-2" placeholder="عدد المقاعد">
                             @if($flight->office_name == 'Alaziziah')
                                        <button class="btn btn-danger w-100">احجز الآن</button>
                                        @else
                                        <button class="btn btn-success w-100">احجز الآن</button>
                                    @endif
                    </form>
                @else
                    <span class="badge bg-danger">مكتملة</span>
                @endif
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection
