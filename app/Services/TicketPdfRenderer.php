<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Meneses\LaravelMpdf\Facades\LaravelMpdf;
use Throwable;

class TicketPdfRenderer
{
    public function render(Booking $booking): string
    {
        $this->ensureRuntimeDirectories();

        try {
            return LaravelMpdf::loadView(
                'traveler.ticket',
                compact('booking'),
                [],
                [
                    'title' => 'ticket-'.$booking->serial_number,
                ],
            )->output();
        } catch (Throwable $exception) {
            Log::error('Ticket PDF wrapper render failed.', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function ensureRuntimeDirectories(): void
    {
        $path = storage_path('app/mpdf');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
