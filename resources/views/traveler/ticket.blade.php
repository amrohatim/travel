@php
    use Carbon\Carbon;

    $travelDate = $booking->flight?->travel_date ? Carbon::parse($booking->flight->travel_date) : null;
    $departure = $booking->flight?->departure_time ? Carbon::parse($booking->flight->departure_time) : null;

    $topHeaderLogoPath = public_path('assets/top_header_logo.png');
    $topHeaderLogoData = file_exists($topHeaderLogoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($topHeaderLogoPath))
        : null;

    $bottomLeftLogoPath = public_path('assets/bottom_left_logo.png');
    $bottomLeftLogoData = file_exists($bottomLeftLogoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($bottomLeftLogoPath))
        : null;

    $routeVisualPath = public_path('assets/bus_between_from_to.png');
    $routeVisualData = file_exists($routeVisualPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($routeVisualPath))
        : null;

    $dayLabel = $travelDate ? $travelDate->locale('ar')->translatedFormat('l') : '';
    $dateLabel = $travelDate ? $travelDate->format('j-n-Y') : '--/--/----';
    $timeValue = $departure ? $departure->format('g') : '--';
    $timePeriod = $departure ? ($departure->hour < 12 ? 'صباحا' : 'مساء') : '';
    $routeFrom = $booking->flight?->from ?? '';
    $routeTo = $booking->flight?->to ?? '';
    $officeName = $booking->flight?->office_name ?? '';
    $seatCount = (int) $booking->seats_booked;
    $totalAmount = number_format((int) $booking->total, 0);
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Ticket</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }

        body {
            margin: 0;
            padding: 0;
            direction: rtl;
            background-color: #ece9e6;
            color: #5f6368;
            font-family: cairopdf, sans-serif;
            font-size: 14px;
        }

        .page {
            padding-bottom: 24px;
            background-color: #ece9e6;
        }

        .header {
            background-color: #f8933f;
            padding: 18px 24px;
            text-align: right;
        }

        .header-logo {
            text-align: left;
            margin-bottom: 8px;
        }

        .header-logo img {
            width: 56px;
            height: 56px;
        }

        .brand-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
            line-height: 1.15;
        }

        .brand-subtitle {
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            line-height: 1.3;
        }

        .sheet {
            padding: 18px 24px 0;
        }

        .card {
            background-color: #ffffff;
            border: 1px solid #e3ddd7;
            padding: 18px 20px;
            margin-bottom: 18px;
        }

        .status-card {
            position: relative;
            padding-top: 20px;
        }

        .serial-box {
            background-color: #f8933f;
            padding: 14px 12px 10px;
            text-align: center;
            margin-bottom: 18px;
        }

        .serial-title {
            color: #2f251d;
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .serial-value {
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            direction: ltr;
        }

        .ticket-title {
            color: #f8933f;
            font-size: 28px;
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 6px;
            text-align: right;
        }

        .ticket-status {
            color: #6c7178;
            font-size: 19px;
            text-align: right;
        }

        .ticket-status strong {
            color: #f8933f;
        }

        .divider {
            height: 1px;
            background-color: #d8d3cf;
            margin-top: 14px;
        }

        .route-visual {
            text-align: center;
            margin-bottom: 12px;
        }

        .route-visual img {
            width: 110px;
            height: auto;
        }

        .route-line {
            text-align: center;
            color: #6c7178;
            font-size: 19px;
            font-weight: bold;
            margin-bottom: 14px;
        }

        .date-line {
            text-align: center;
            color: #6c7178;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .time {
            color: #f8933f;
            font-size: 22px;
        }

        .ltr {
            direction: ltr;
        }

        .stats {
            text-align: center;
            margin-bottom: 18px;
        }

        .stat {
            margin-bottom: 12px;
        }

        .stat-label {
            color: #6c7178;
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .stat-value {
            color: #f8933f;
            font-size: 20px;
            font-weight: bold;
        }

        .section-title {
            color: #6d737b;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: right;
        }

        .passenger-name {
            color: #767b82;
            font-size: 18px;
            line-height: 1.6;
            text-align: right;
            margin-bottom: 4px;
        }

        .office-title {
            color: #171717;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 6px;
            text-align: right;
        }

        .office-name {
            color: #171717;
            font-size: 21px;
            font-weight: bold;
            text-align: right;
            margin-bottom: 16px;
        }

        .office-brand {
            text-align: center;
        }

        .office-brand img {
            width: 86px;
            height: 86px;
            margin-bottom: 4px;
        }

        .office-brand-name {
            color: #f8933f;
            font-size: 15px;
            font-weight: bold;
        }

        .office-brand-copy {
            color: #223444;
            font-size: 12px;
            font-weight: bold;
            line-height: 1.35;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-logo">
                @if ($topHeaderLogoData)
                    <img src="{{ $topHeaderLogoData }}" alt="سفريات">
                @endif
            </div>
            <div class="brand-title">سفريات</div>
            <div class="brand-subtitle">معك في كل الرحلات</div>
        </div>

        <div class="sheet">
            <div class="card status-card">
                <div class="serial-box">
                    <div class="serial-title">الرقم التسلسلي</div>
                    <div class="serial-value">{{ $booking->serial_number }}</div>
                </div>

                <div class="ticket-title">تذكرة مؤكدة</div>
                <div class="ticket-status">حالة التذكرة: <strong>مؤكدة</strong></div>
                <div class="divider"></div>
            </div>

            <div class="card">
                @if ($routeVisualData)
                    <div class="route-visual">
                        <img src="{{ $routeVisualData }}" alt="">
                    </div>
                @endif

                <div class="route-line">{{ $routeFrom }} - {{ $routeTo }}</div>

                <div class="date-line">
                    <span>{{ $dayLabel }}</span>
                    <span class="ltr" style="margin: 0 12px;">{{ $dateLabel }}</span>
                    <span class="time">
                        <span>{{ $timePeriod }}</span>
                        <span class="ltr">{{ $timeValue }}</span>
                    </span>
                </div>

                <div class="stats">
                    <div class="stat">
                        <div class="stat-label">إجمالي التذاكر</div>
                        <div class="stat-value ltr">{{ $totalAmount }} SDG</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">عدد التذاكر</div>
                        <div class="stat-value ltr">{{ $seatCount }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="section-title">المسافرين</div>
                @if ($booking->relationLoaded('seats') && $booking->seats->count())
                    @foreach ($booking->seats as $seat)
                        <div class="passenger-name">{{ $seat->traveler_name }}</div>
                    @endforeach
                @else
                    <div class="passenger-name">لا توجد أسماء مسجلة</div>
                @endif
            </div>

            <div class="card">
                <div class="office-title">المكتب</div>
                <div class="office-name">{{ $officeName }}</div>

                <div class="office-brand">
                    @if ($bottomLeftLogoData)
                        <img src="{{ $bottomLeftLogoData }}" alt="سفريات">
                    @endif
                    <div class="office-brand-name">سفريات</div>
                    <div class="office-brand-copy">معك في كل الرحلات</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
