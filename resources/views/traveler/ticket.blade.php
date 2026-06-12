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
            background: #ece9e6;
            color: #5f6368;
            font-family: cairopdf, sans-serif;
            font-size: 14px;
        }

        .page {
            background: #ece9e6;
        }

        .header {
            background: #f8933f;
            padding: 18px 26px;
        }

        .header-table,
        .content-table,
        .details-table,
        .office-table,
        .stats-table,
        .date-table,
        .route-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        .header-logo {
            width: 70px;
            text-align: left;
            vertical-align: middle;
        }

        .header-logo img {
            width: 58px;
            height: 58px;
        }

        .header-copy {
            text-align: right;
            color: #ffffff;
            vertical-align: middle;
        }

        .brand-name {
            font-size: 26px;
            font-weight: bold;
            line-height: 1.1;
            margin-bottom: 2px;
        }

        .brand-tagline {
            font-size: 16px;
            font-weight: bold;
            line-height: 1.2;
        }

        .sheet {
            padding: 22px 34px 28px;
        }

        .heading-table td {
            vertical-align: top;
        }

        .serial-cell {
            width: 190px;
            padding-left: 16px;
        }

        .serial-chip {
            background: #f8933f;
            padding: 16px 12px 12px;
            text-align: center;
        }

        .serial-title {
            color: #2f251d;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .serial-value {
            color: #ffffff;
            direction: ltr;
            font-size: 13px;
            font-weight: bold;
        }

        .heading-copy {
            text-align: right;
            padding-top: 8px;
        }

        .ticket-title {
            color: #f8933f;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .ticket-status {
            color: #6c7178;
            font-size: 20px;
        }

        .ticket-status strong {
            color: #f8933f;
            font-weight: bold;
        }

        .heading-rule {
            border-top: 1px solid #d9d2cc;
            margin-top: 14px;
        }

        .summary-card,
        .office-card {
            background: #ffffff;
            border: 1px solid #e6e0da;
        }

        .summary-card {
            margin-top: 16px;
        }

        .passengers-cell {
            width: 37%;
            border-right: 1px solid #ddd6d1;
            padding: 24px 24px 20px;
            text-align: center;
            vertical-align: top;
        }

        .trip-cell {
            width: 63%;
            padding: 24px 28px 20px;
            text-align: right;
            vertical-align: top;
        }

        .section-title {
            color: #6d737b;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .passenger-name {
            color: #767b82;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 6px;
        }

        .route-visual {
            text-align: center;
            margin-bottom: 10px;
        }

        .route-visual img {
            width: 118px;
            height: auto;
        }

        .route-table td {
            color: #6c7178;
            font-size: 19px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }

        .route-gap {
            width: 25%;
        }

        .date-table {
            margin-top: 12px;
        }

        .date-table td {
            text-align: center;
            vertical-align: middle;
        }

        .date-day {
            color: #6c7178;
            font-size: 18px;
            font-weight: bold;
        }

        .date-value {
            color: #6c7178;
            direction: ltr;
            font-size: 18px;
            font-weight: bold;
        }

        .time-wrap {
            color: #f8933f;
            font-size: 23px;
            font-weight: bold;
        }

        .time-value {
            direction: ltr;
        }

        .stats-table {
            margin-top: 28px;
        }

        .stats-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }

        .stat-label {
            color: #6c7178;
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .stat-value {
            color: #f8933f;
            font-size: 20px;
            font-weight: bold;
        }

        .money-value {
            direction: ltr;
        }

        .office-card {
            margin-top: 34px;
            padding: 12px 24px;
        }

        .office-branding {
            width: 112px;
            text-align: center;
            vertical-align: middle;
        }

        .office-branding img {
            width: 90px;
            height: 90px;
            margin-bottom: 4px;
        }

        .office-branding-name {
            color: #f8933f;
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .office-branding-copy {
            color: #223444;
            font-size: 13px;
            font-weight: bold;
            line-height: 1.35;
        }

        .office-info {
            text-align: right;
            vertical-align: middle;
        }

        .office-title {
            color: #171717;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .office-name {
            color: #171717;
            font-size: 22px;
            font-weight: bold;
        }

        .rtl {
            direction: rtl;
        }

        .ltr {
            direction: ltr;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-copy">
                        <div class="brand-name">سفريات</div>
                        <div class="brand-tagline">معك في كل الرحلات</div>
                    </td>
                    <td class="header-logo">
                        @if ($topHeaderLogoData)
                            <img src="{{ $topHeaderLogoData }}" alt="سفريات">
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="sheet">
            <table class="content-table heading-table">
                <tr>
                    <td class="heading-copy">
                        <div class="ticket-title">تذكرة مؤكدة</div>
                        <div class="ticket-status">حالة التذكرة: <strong>مؤكدة</strong></div>
                        <div class="heading-rule"></div>
                    </td>
                    <td class="serial-cell">
                        <div class="serial-chip">
                            <div class="serial-title">الرقم التسلسلي</div>
                            <div class="serial-value">{{ $booking->serial_number }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="summary-card details-table">
                <tr>
                    <td class="trip-cell">
                        @if ($routeVisualData)
                            <div class="route-visual">
                                <img src="{{ $routeVisualData }}" alt="">
                            </div>
                        @endif

                        <table class="route-table">
                            <tr>
                                <td class="rtl">{{ $routeTo }}</td>
                                <td class="route-gap"></td>
                                <td class="rtl">{{ $routeFrom }}</td>
                            </tr>
                        </table>

                        <table class="date-table">
                            <tr>
                                <td class="time-wrap">
                                    <span class="rtl">{{ $timePeriod }}</span>
                                    <span class="time-value">{{ $timeValue }}</span>
                                </td>
                                <td class="date-value">{{ $dateLabel }}</td>
                                <td class="date-day">{{ $dayLabel }}</td>
                            </tr>
                        </table>

                        <table class="stats-table">
                            <tr>
                                <td>
                                    <div class="stat-label">إجمالي التذاكر</div>
                                    <div class="stat-value money-value">{{ $totalAmount }} SDG</div>
                                </td>
                                <td>
                                    <div class="stat-label">عدد التذاكر</div>
                                    <div class="stat-value ltr">{{ $seatCount }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td class="passengers-cell">
                        <div class="section-title">المسافرين</div>
                        @if ($booking->relationLoaded('seats') && $booking->seats->count())
                            @foreach ($booking->seats as $seat)
                                <div class="passenger-name">{{ $seat->traveler_name }}</div>
                            @endforeach
                        @else
                            <div class="passenger-name">لا توجد أسماء مسجلة</div>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="office-card">
                <table class="office-table">
                    <tr>
                        <td class="office-info">
                            <div class="office-title">المكتب</div>
                            <div class="office-name">{{ $officeName }}</div>
                        </td>
                        <td class="office-branding">
                            @if ($bottomLeftLogoData)
                                <img src="{{ $bottomLeftLogoData }}" alt="سفريات">
                            @endif
                            <div class="office-branding-name">سفريات</div>
                            <div class="office-branding-copy">معك في كل الرحلات</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
