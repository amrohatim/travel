<h2>تذكرة سفر</h2>
<hr>
<div class="row"><span class="label">المكتب:</span> <span>{{ $booking->flight->office_name }}</span></div>
<div class="row"><span class="label">المسافر:</span> <span>{{ $booking->traveler->name }}</span></div>

<p>اسم المسافر: {{ $booking->traveler->name }}</p>
<p>من: {{ $booking->flight->from }}</p>
<p>إلى: {{ $booking->flight->to }}</p>
<p>التاريخ: {{ $booking->flight->travel_date }}</p>
<p>عدد المقاعد: {{ $booking->seats_booked }}</p>
<p>السعر الإجمالي: {{ $booking->seats_booked * $booking->flight->price }}</p>
<p>رقم الحجز: {{ $booking->id }}</p>

<hr>
<p>شكراً لاستخدامك Travel Booking</p>
