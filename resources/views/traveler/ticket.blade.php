<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { direction: rtl; text-align: right; background: #f7f7f7; padding: 18px; }
        .ticket { background: #ffffff; border: 1px solid #e6e6e6; border-radius: 10px; padding: 18px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eeeeee; padding-bottom: 10px; margin-bottom: 12px; }
        .title { font-size: 28px; font-weight: 700; color: #222; }
        .subtle { color: #666; font-size: 12px; }
        .section { margin-top: 10px; }
        .section-title { font-size: 14px; font-weight: 700; margin-bottom: 6px; color: #333; }
        .row { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 6px; font-size: 13px; }
        .label { color: #555; font-weight: 700; font-size: 18px; }
        .value { color: #222; font-size: 18px;}
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
        .badge { background: #f2f2f2; padding: 4px 8px; border-radius: 6px; font-size: 12px; }
        .list { margin: 0; padding-right: 16px; }
        .total { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ddd; font-weight: 700; }
        .footer { margin-top: 12px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
<div class="ticket">
    <div class="header">
        <div>
            <div class="title">{!! arabic_text('تذكرة سفر') !!}</div>
            {{-- <div class="subtle">{!! arabic_text('رقم الحجز') !!}: {{ $booking->id }}</div> --}}
        </div>
        {{-- <div class="badge">{!! arabic_text($booking->status) !!}: {!! arabic_text('الحالة') !!} </div> --}}
    </div>

    <div class="section">
        <div class="section-title">{!! arabic_text('بيانات الرحلة') !!}</div>
        <div class="grid">
            <div class="row"><span class="value">{!! arabic_text($booking->flight->office_name) !!}</span><span class="label"> :{!! arabic_text('المكتب') !!}</span></div>
            {{-- <div class="row"><span class="label">{!! arabic_text('المسافر') !!}</span><span class="value">{!! arabic_text($booking->traveler->name) !!}</span></div> --}}
            <div class="row"><span class="value">{!! arabic_text($booking->flight->from) !!}</span><span class="label"> :{!! arabic_text('من') !!}</span></div>
            <div class="row"> <span class="value">{!! arabic_text($booking->flight->to) !!}</span><span class="label"> :{!! arabic_text('إلى') !!}</span></div>
            <div class="row"><span class="value">{{ $booking->flight->travel_date }}</span><span class="label"> :{!! arabic_text('التاريخ') !!}</span></div>
            <div class="row"><span class="value">{{ $booking->seats_booked }}</span><span class="label"> :{!! arabic_text('عدد المقاعد') !!}</span></div>
        </div>
    </div>

    @if($booking->relationLoaded('seats') && $booking->seats->count())
        <div class="section">
            <div class="section-title">{!! arabic_text('أسماء المسافرين') !!}</div>
            
                @foreach($booking->seats as $seat)
                    <p>{!! arabic_text($seat->traveler_name) !!} o</p><br>
                @endforeach
           
        </div>
    @endif

    <div class="section total">
        <div class="row">
                                  <span class="label">{!! arabic_text('جنيه') !!}</span>

              <span class="value" style="color:orange;">{{ $booking->seats_booked * $booking->flight->price }}</span>
          <span class="value">{{ $booking->serial_number }}</span>
          <span class="label">{!! arabic_text('الرقم التسلسلي : ') !!}</span>

            <span class="label">{!! arabic_text('المجموع الكلي: ') !!}</span>
          
        </div>
    </div>

    <div class="footer">{!! arabic_text('شكراً لاستخدامك سفريات') !!}</div>
</div>
</body>

</html>
