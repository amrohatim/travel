<!DOCTYPE html>
<html>
<head>
    <title>Travel Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/">✈ Travel Booking</a>

    <div class="d-flex gap-2">
      @auth
        @if(auth()->user()->role == 'traveler')
            <a class="btn btn-light" href="/flights">الرحلات</a>
            <a class="btn btn-light" href="/my-bookings">حجوزاتي</a>
        @endif

        @if(auth()->user()->role == 'office')
            <a class="btn btn-light" href="/office/flights/create">إضافة رحلة</a>
            <a class="btn btn-light" href="/office/flights/create"> رحلاتنا</a>
            <a class="btn btn-light" href="/office/bookings">الحجوزات</a>
        @endif

        @if(auth()->user()->role == 'admin')
            <a class="btn btn-warning" href="/admin">لوحة المدير</a>
        @endif

        <form method="POST" action="/logout">
            @csrf
            <button class="btn btn-danger">خروج</button>
        </form>
      @endauth

      @guest
        <a class="btn btn-light" href="/login">دخول</a>
        <a class="btn btn-success" href="/register">تسجيل</a>
      @endguest
    </div>
  </div>
</nav>

<div class="container mt-4">

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

@yield('content')
</div>

</body>
</html>
