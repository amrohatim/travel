<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Throwable;

class TicketPdfRenderer
{
    public function render(Booking $booking): string
    {
        try {
            $pdf = $this->makePdf();
            $pdf->SetTitle('ticket-'.$booking->serial_number);
            $pdf->SetDirectionality('rtl');

            $html = view('traveler.ticket', compact('booking'))->render();
            $pdf->WriteHTML($html);

            return $pdf->Output('', 'S');
        } catch (Throwable $exception) {
            Log::error('Ticket PDF mPDF render failed.', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function makePdf(): Mpdf
    {
        $this->ensureRuntimeDirectories();

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => storage_path('app/mpdf'),
            'fontDir' => array_merge($fontDirs, [
                public_path('assets/fonts'),
            ]),
            'fontdata' => $fontData + [
                'cairopdf' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                ],
            ],
            'default_font' => 'cairopdf',
            'default_font_size' => 12,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
    }

    private function ensureRuntimeDirectories(): void
    {
        foreach ([storage_path('app/mpdf')] as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
}
