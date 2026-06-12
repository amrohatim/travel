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

    $cairoRegularPath = public_path('assets/fonts/Cairo-Regular.ttf');
    $cairoRegularData = file_exists($cairoRegularPath)
        ? 'data:font/ttf;base64,'.base64_encode(file_get_contents($cairoRegularPath))
        : null;

    $cairoBoldPath = public_path('assets/fonts/Cairo-Bold.ttf');
    $cairoBoldData = file_exists($cairoBoldPath)
        ? 'data:font/ttf;base64,'.base64_encode(file_get_contents($cairoBoldPath))
        : null;

    $dayLabel = $travelDate ? $travelDate->locale('ar')->translatedFormat('l') : '';
    $dateLabel = $travelDate ? $travelDate->format('j-n-Y') : '--/--/----';
    $timeLabel = '--:--';

    if ($departure) {
        $timePeriod = $departure->hour < 12 ? 'صباحا' : 'مساء';
        $timeLabel = $departure->format('g').' '.$timePeriod;
    }

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
        @if ($cairoRegularData)
        @font-face {
            font-family: "CairoPdf";
            src: url("{{ $cairoRegularData }}") format("truetype");
            font-weight: 400;
            font-style: normal;
        }
        @endif

        @if ($cairoBoldData)
        @font-face {
            font-family: "CairoPdf";
            src: url("{{ $cairoBoldData }}") format("truetype");
            font-weight: 700;
            font-style: normal;
        }
        @endif

        * {
            box-sizing: border-box;
        }

        @page {
            margin: 0;
            size: A4;
        }

        body {
            margin: 0;
            background: #eceae8;
            color: #5f646a;
            direction: rtl;
            font-family: "CairoPdf", "DejaVu Sans", sans-serif;
            text-align: right;
        }

        .ticket-page {
            width: 100%;
            min-height: 100vh;
            background: #eceae8;
        }

        .top-band {
            background: #f8933f;
            padding: 12px 28px 10px;
        }

        .top-band::after {
            content: "";
            display: block;
            clear: both;
        }

        .brand {
            float: right;
            text-align: right;
        }

        .brand-copy {
            float: right;
            margin-left: 14px;
            color: #ffffff;
            text-align: right;
        }

        .brand-copy h1 {
            margin: 0 0 2px;
            font-size: 26px;
            line-height: 1.05;
            font-weight: 700;
        }

        .brand-copy p {
            margin: 0;
            font-size: 16px;
            line-height: 1.1;
            font-weight: 700;
        }

        .brand-mark {
            float: right;
            width: 58px;
            height: 58px;
        }

        .brand-mark img {
            display: block;
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .sheet {
            padding: 20px 40px 26px;
        }

        .heading-wrap {
            position: relative;
            margin-bottom: 18px;
        }

        .heading-copy {
            text-align: right;
        }

        .heading-copy h2 {
            margin: 0 0 8px;
            color: #f8933f;
            font-size: 27px;
            line-height: 1.08;
            font-weight: 700;
        }

        .heading-copy p {
            margin: 0;
            color: #6f7379;
            font-size: 20px;
            line-height: 1.2;
            font-weight: 400;
        }

        .heading-copy strong {
            color: #f8933f;
            font-weight: 700;
        }

        .heading-rule {
            margin-top: 12px;
            border-top: 1px solid #d4d0cd;
        }

        .serial-chip {
            position: absolute;
            left: -40px;
            top: 40px;
            width: 184px;
            background: #f8933f;
            padding: 15px 14px 11px;
            text-align: center;
        }

        .serial-chip h3 {
            margin: 0 0 8px;
            color: #31261d;
            font-size: 17px;
            line-height: 1.15;
            font-weight: 700;
        }

        .serial-chip p {
            margin: 0;
            color: #ffffff;
            font-size: 13px;
            line-height: 1.35;
            word-break: break-word;
            font-weight: 700;
        }

        .summary-card,
        .office-card {
            background: #ffffff;
            border: 1px solid #e9e4df;
        }

        .summary-card::after {
            content: "";
            display: block;
            clear: both;
        }

        .passengers-panel,
        .trip-panel {
            float: right;
            width: 50%;
            min-height: 300px;
        }

        .trip-panel {
            padding: 20px 28px 18px;
        }

        .passengers-panel {
            border-left: 1px solid #d9d5d1;
            padding: 22px 28px 20px;
            text-align: center;
        }

        .passengers-panel h3 {
            margin: 0 0 8px;
            color: #70757c;
            font-size: 26px;
            line-height: 1.15;
            font-weight: 700;
        }

        .passenger-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .passenger-list li {
            margin: 0 0 7px;
            color: #767b81;
            font-size: 18px;
            line-height: 1.45;
            font-weight: 400;
        }

        .route-block {
            text-align: right;
        }

        .route-visual {
            text-align: center;
            margin-bottom: 8px;
        }

        .route-visual img {
            display: inline-block;
            width: 116px;
            height: auto;
        }

        .route-line {
            text-align: center;
            color: #71757b;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .route-from,
        .route-to {
            display: inline-block;
            vertical-align: middle;
            width: 34%;
            white-space: nowrap;
        }

        .route-from {
            text-align: left;
        }

        .route-to {
            text-align: right;
        }

        .route-gap {
            display: inline-block;
            width: 18%;
        }

        .date-row {
            text-align: center;
            color: #70757b;
            font-size: 17px;
            line-height: 1.25;
            font-weight: 700;
        }

        .date-row span {
            display: inline-block;
            vertical-align: baseline;
            margin: 0 4px;
        }

        .date-row .time {
            color: #f8933f;
            font-size: 20px;
            font-weight: 700;
        }

        .stats {
            margin-top: 22px;
            text-align: center;
        }

        .stats::after {
            content: "";
            display: block;
            clear: both;
        }

        .stat {
            float: right;
            width: 50%;
        }

        .stat-label {
            color: #6f7379;
            font-size: 17px;
            line-height: 1.25;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .stat-value {
            color: #f8933f;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 700;
        }

        .office-card {
            margin-top: 36px;
            padding: 10px 24px 12px;
        }

        .office-card::after {
            content: "";
            display: block;
            clear: both;
        }

        .office-info {
            float: right;
            text-align: right;
            margin-top: 22px;
        }

        .office-info h3 {
            margin: 0 0 8px;
            color: #171717;
            font-size: 24px;
            line-height: 1.15;
            font-weight: 700;
        }

        .office-info p {
            margin: 0;
            color: #171717;
            font-size: 21px;
            line-height: 1.2;
            font-weight: 700;
        }

        .office-branding {
            float: left;
            width: 106px;
            text-align: center;
        }

        .office-branding img {
            display: block;
            width: 92px;
            height: 92px;
            margin: 0 auto 2px;
            object-fit: contain;
        }

        .office-branding h4 {
            margin: 0 0 2px;
            color: #f8933f;
            font-size: 15px;
            line-height: 1.1;
            font-weight: 700;
        }

        .office-branding p {
            margin: 0;
            color: #243447;
            font-size: 13px;
            line-height: 1.25;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="ticket-page">
    <div class="top-band">
        <div class="brand">
            <div class="brand-mark">
                @if ($topHeaderLogoData)
                    <img src="{{ $topHeaderLogoData }}" alt="سفريات">
                @endif
            </div>
            <div class="brand-copy">
                <h1>سفريات</h1>
                <p>معك في كل الرحلات</p>
            </div>
        </div>
    </div>

    <div class="sheet">
        <div class="heading-wrap">
            <div class="heading-copy">
                <h2>تذكرة مؤكدة</h2>
                <p>حالة التذكرة: <strong>مؤكدة</strong></p>
                <div class="heading-rule"></div>
            </div>

            <div class="serial-chip">
                <h3>الرقم التسلسلي</h3>
                <p>{{ $booking->serial_number }}</p>
            </div>
        </div>

        <div class="summary-card">
            <div class="trip-panel">
                <div class="route-block">
                    @if ($routeVisualData)
                        <div class="route-visual">
                            <img src="{{ $routeVisualData }}" alt="">
                        </div>
                    @endif

                    <div class="route-line">
                        <span class="route-to">{{ $routeTo }}</span>
                        <span class="route-gap"></span>
                        <span class="route-from">{{ $routeFrom }}</span>
                    </div>

                    <div class="date-row">
                        <span>{{ $dayLabel }}</span>
                        <span>{{ $dateLabel }}</span>
                        <span class="time">{{ $timeLabel }}</span>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat">
                        <div class="stat-label">إجمالي التذاكر</div>
                        <div class="stat-value">{{ $totalAmount }} SDG</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">عدد التذاكر</div>
                        <div class="stat-value">{{ $seatCount }}</div>
                    </div>
                </div>
            </div>

            <div class="passengers-panel">
                <h3>المسافرين</h3>
                @if ($booking->relationLoaded('seats') && $booking->seats->count())
                    <ul class="passenger-list">
                        @foreach ($booking->seats as $seat)
                            <li>{{ $seat->traveler_name }}</li>
                        @endforeach
                    </ul>
                @else
                    <ul class="passenger-list">
                        <li>لا توجد أسماء مسجلة</li>
                    </ul>
                @endif
            </div>
        </div>

        <div class="office-card">
            <div class="office-info">
                <h3>المكتب</h3>
                <p>{{ $officeName }}</p>
            </div>

            <div class="office-branding">
                @if ($bottomLeftLogoData)
                    <img src="{{ $bottomLeftLogoData }}" alt="سفريات">
                @endif
                <h4>سفريات</h4>
                <p>معك في كل الرحلات</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
