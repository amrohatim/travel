<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Meneses\LaravelMpdf\Facades\LaravelMpdf;
use Mpdf\Mpdf;
use Throwable;

class TicketPdfRenderer
{
    public function render(Booking $booking): string
    {
        $this->ensureRuntimeDirectories();

        try {
            $wrapper = LaravelMpdf::loadHTML('', [], [], [
                'title' => 'ticket-'.$booking->serial_number,
            ]);

            $pdf = $wrapper->getMpdf();
            $pdf->SetDirectionality('rtl');
            $pdf->SetAutoPageBreak(false);

            $this->drawTicket($pdf, $booking);

            return $pdf->Output('', 'S');
        } catch (Throwable $exception) {
            Log::error('Ticket PDF direct draw render failed.', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function drawTicket(Mpdf $pdf, Booking $booking): void
    {
        $travelDate = $booking->flight?->travel_date ? Carbon::parse($booking->flight->travel_date) : null;
        $departure = $booking->flight?->departure_time ? Carbon::parse($booking->flight->departure_time) : null;

        $dayLabel = $travelDate ? $travelDate->locale('ar')->translatedFormat('l') : '';
        $dateLabel = $travelDate ? $travelDate->format('j-n-Y') : '--/--/----';
        $timeValue = $departure ? $departure->format('g') : '--';
        $timePeriod = $departure ? ($departure->hour < 12 ? 'صباحا' : 'مساء') : '';
        $routeFrom = $booking->flight?->from ?? '';
        $routeTo = $booking->flight?->to ?? '';
        $officeName = $booking->flight?->office_name ?? '';
        $seatCount = (int) $booking->seats_booked;
        $totalAmount = number_format((int) $booking->total, 0).' SDG';
        $seatNames = $booking->relationLoaded('seats')
            ? $booking->seats->pluck('traveler_name')->filter()->values()->all()
            : [];
        $firstPageSeatNames = array_slice($seatNames, 0, 4);
        $remainingSeatNames = array_slice($seatNames, 4);

        $pdf->SetFillColor(236, 233, 230);
        $pdf->Rect(0, 0, 210, 297, 'F');

        $pdf->SetFillColor(248, 147, 63);
        $pdf->Rect(0, 0, 210, 34, 'F');

        $this->drawImageIfExists($pdf, public_path('assets/top_header_logo.png'), 172, 6, 24, 24);

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('cairopdf', 'B', 22);
        $this->writeTextBox($pdf, 120, 8, 44, 10, 'سفريات', 'R');
        $pdf->SetFont('cairopdf', 'B', 12);
        $this->writeTextBox($pdf, 96, 18, 68, 8, 'معك في كل الرحلات', 'R');

        $pdf->SetTextColor(95, 99, 104);

        $this->drawCard($pdf, 12, 42, 186, 40);
        $this->drawSerialBox($pdf, 12, 48, 50, 24, (string) $booking->serial_number);

        $pdf->SetTextColor(248, 147, 63);
        $pdf->SetFont('cairopdf', 'B', 20);
        $this->writeTextBox($pdf, 70, 48, 118, 10, 'تذكرة مؤكدة', 'R');
        $pdf->SetFont('cairopdf', '', 14);
        $pdf->SetTextColor(108, 113, 120);
        $this->writeTextBox($pdf, 96, 60, 92, 8, 'حالة التذكرة:', 'R');
        $pdf->SetFont('cairopdf', 'B', 14);
        $pdf->SetTextColor(248, 147, 63);
        $this->writeTextBox($pdf, 70, 60, 24, 8, 'مؤكدة', 'R');
        $pdf->SetDrawColor(216, 211, 207);
        $pdf->Line(70, 72, 188, 72);

        $this->drawCard($pdf, 12, 90, 186, 112);

        $this->drawImageIfExists($pdf, public_path('assets/bus_between_from_to.png'), 92, 98, 28, 14);

        $pdf->SetFont('cairopdf', 'B', 15);
        $pdf->SetTextColor(108, 113, 120);
        $this->writeTextBox($pdf, 24, 118, 60, 8, $routeFrom, 'C');
        $this->writeTextBox($pdf, 126, 118, 60, 8, $routeTo, 'C');

        $pdf->SetFont('cairopdf', 'B', 13);
        $pdf->SetTextColor(248, 147, 63);
        $this->writeTextBox($pdf, 20, 132, 48, 8, $timePeriod.' '.$timeValue, 'C');
        $pdf->SetTextColor(108, 113, 120);
        $this->writeTextBox($pdf, 72, 132, 56, 8, $dateLabel, 'C');
        $this->writeTextBox($pdf, 132, 132, 48, 8, $dayLabel, 'C');

        $pdf->SetFont('cairopdf', 'B', 14);
        $pdf->SetTextColor(109, 115, 123);
        $this->writeTextBox($pdf, 20, 152, 160, 8, 'إجمالي التذاكر', 'R');
        $pdf->SetTextColor(248, 147, 63);
        $this->writeTextBox($pdf, 20, 162, 160, 8, $totalAmount, 'R');

        $pdf->SetTextColor(109, 115, 123);
        $pdf->SetFont('cairopdf', 'B', 14);
        $this->writeTextBox($pdf, 20, 178, 160, 8, 'عدد التذاكر', 'R');
        $pdf->SetTextColor(248, 147, 63);
        $this->writeTextBox($pdf, 20, 188, 160, 8, (string) $seatCount, 'R');

        $this->drawCard($pdf, 12, 210, 186, 52);
        $pdf->SetFont('cairopdf', 'B', 18);
        $pdf->SetTextColor(109, 115, 123);
        $this->writeTextBox($pdf, 20, 218, 170, 10, 'المسافرين', 'R');

        $y = 232;
        $pdf->SetFont('cairopdf', '', 14);
        $pdf->SetTextColor(118, 123, 130);
        if ($firstPageSeatNames === []) {
            $this->writeTextBox($pdf, 20, $y, 170, 8, 'لا توجد أسماء مسجلة', 'R');
            $y += 8;
        } else {
            foreach ($firstPageSeatNames as $travelerName) {
                $this->writeTextBox($pdf, 20, $y, 170, 8, $travelerName, 'R');
                $y += 8;
            }
        }

        $this->drawCard($pdf, 12, 268, 186, 22);
        $pdf->SetFont('cairopdf', 'B', 18);
        $pdf->SetTextColor(23, 23, 23);
        $this->writeTextBox($pdf, 20, 274, 122, 8, 'المكتب', 'R');
        $pdf->SetFont('cairopdf', 'B', 14);
        $this->writeTextBox($pdf, 20, 282, 122, 6, $officeName, 'R');

        $this->drawImageIfExists($pdf, public_path('assets/bottom_left_logo.png'), 146, 271, 18, 18);
        $pdf->SetFont('cairopdf', 'B', 9);
        $pdf->SetTextColor(248, 147, 63);
        $this->writeTextBox($pdf, 164, 273, 24, 5, 'سفريات', 'C');
        $pdf->SetTextColor(34, 52, 68);
        $pdf->SetFont('cairopdf', 'B', 7);
        $this->writeTextBox($pdf, 160, 279, 32, 8, 'معك في كل الرحلات', 'C');

        $this->drawPassengerOverflowPages($pdf, $remainingSeatNames);
    }

    private function drawPassengerOverflowPages(Mpdf $pdf, array $remainingSeatNames): void
    {
        if ($remainingSeatNames === []) {
            return;
        }

        $chunks = array_chunk($remainingSeatNames, 18);

        foreach ($chunks as $index => $names) {
            $pdf->AddPage();
            $pdf->SetFillColor(236, 233, 230);
            $pdf->Rect(0, 0, 210, 297, 'F');
            $pdf->SetFillColor(248, 147, 63);
            $pdf->Rect(0, 0, 210, 26, 'F');

            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('cairopdf', 'B', 18);
            $this->writeTextBox($pdf, 20, 8, 170, 8, 'قائمة المسافرين', 'R');

            $pdf->SetTextColor(95, 99, 104);
            $this->drawCard($pdf, 12, 36, 186, 246);

            $pdf->SetFont('cairopdf', 'B', 14);
            $this->writeTextBox(
                $pdf,
                20,
                46,
                170,
                8,
                'الصفحة الإضافية '.($index + 2),
                'R',
            );

            $pdf->SetFont('cairopdf', '', 14);
            $pdf->SetTextColor(118, 123, 130);
            $y = 62;

            foreach ($names as $travelerName) {
                $this->writeTextBox($pdf, 20, $y, 170, 8, $travelerName, 'R');
                $y += 11;
            }
        }
    }

    private function drawCard(Mpdf $pdf, float $x, float $y, float $w, float $h): void
    {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(227, 221, 215);
        $pdf->Rect($x, $y, $w, $h, 'DF');
    }

    private function drawSerialBox(Mpdf $pdf, float $x, float $y, float $w, float $h, string $serial): void
    {
        $pdf->SetFillColor(248, 147, 63);
        $pdf->Rect($x, $y, $w, $h, 'F');

        $pdf->SetFont('cairopdf', 'B', 11);
        $pdf->SetTextColor(47, 37, 29);
        $this->writeTextBox($pdf, $x + 4, $y + 3, $w - 8, 6, 'الرقم التسلسلي', 'C');

        $pdf->SetFont('cairopdf', 'B', 10);
        $pdf->SetTextColor(255, 255, 255);
        $this->writeTextBox($pdf, $x + 4, $y + 11, $w - 8, 6, $serial, 'C');
    }

    private function drawImageIfExists(Mpdf $pdf, string $path, float $x, float $y, float $w, float $h): void
    {
        if (is_file($path)) {
            $pdf->Image($path, $x, $y, $w, $h, 'png');
        }
    }

    private function writeTextBox(Mpdf $pdf, float $x, float $y, float $w, float $h, string $text, string $align): void
    {
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $text, 0, 0, $align, false);
    }

    private function ensureRuntimeDirectories(): void
    {
        $path = storage_path('app/mpdf');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
