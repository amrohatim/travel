
@extends('layouts.app')

@section('content')
  <h2>إضافة رحلة</h2>

<form method="POST" action="/office/flights">
    @csrf

    <input type="text" name="from" placeholder="من"><br>
    <input type="text" name="to" placeholder="إلى"><br>
    <label for="departure_time">وقت المغادرة</label><br>
    <input type="datetime-local" id="departure_time" name="departure_time" required><br>
    <input type="number" name="price" placeholder="السعر"><br>
    <input type="number" name="seats" placeholder="عدد المقاعد"><br>

    <button type="submit">حفظ</button>
</form>
@endsection
