<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$flight = new App\Models\Flight([
    'from' => 'بورتسودان',
    'to' => 'حلفا الجديدة',
    'travel_date' => '2026-06-23',
    'departure_time' => '2026-06-23 10:00:00',
    'price' => 60000,
    'office_name' => 'صلاح الدين | حلفا الجديدة',
]);
$booking = new App\Models\Booking([
    'serial_number' => '33425595',
    'seats_booked' => 1,
    'total' => 60000,
    'status' => 'confirmed',
]);
$seats = collect([new App\Models\Seat(['traveler_name' => 'عمرو حاتم محمد'])]);
$booking->setRelation('flight', $flight);
$booking->setRelation('seats', $seats);
$renderer = app(App\Services\TicketPdfRenderer::class);
$pdf = $renderer->render($booking);
file_put_contents('D:/safriat/ticket-debug.pdf', $pdf);
echo strlen($pdf);
