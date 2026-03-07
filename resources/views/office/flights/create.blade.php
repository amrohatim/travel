
@extends('layouts.app')

@section('content')
  <h2>إضافة رحلة</h2>

<form method="POST" action="/office/flights">
    @csrf

    <input type="text" name="from" placeholder="من"><br>
    <input type="text" name="to" placeholder="إلى"><br>
    <input type="date" name="travel_date"><br>
    <input type="number" name="price" placeholder="السعر"><br>
    <input type="number" name="seats" placeholder="عدد المقاعد"><br>

    <button type="submit">حفظ</button>
</form>
@endsection
