<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Throwable;

class TicketPdfRenderer
{
    public function render(Booking $booking): string
    {
        $html = view('traveler.ticket', compact('booking'))->render();

        try {
            return $this->browsershot($html)->pdf();
        } catch (Throwable $browsershotException) {
            Log::error('Ticket PDF Browsershot render failed.', [
                'booking_id' => $booking->id,
                'message' => $browsershotException->getMessage(),
            ]);

            try {
                return Pdf::loadView('traveler.ticket', compact('booking'))->output();
            } catch (Throwable $dompdfException) {
                Log::error('Ticket PDF DomPDF fallback failed.', [
                    'booking_id' => $booking->id,
                    'message' => $dompdfException->getMessage(),
                ]);

                throw $dompdfException;
            }
        }
    }

    private function browsershot(string $html): Browsershot
    {
        $tempPath = storage_path('app/browsershot');

        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $browsershot = Browsershot::html($html)
            ->setNodeModulePath(base_path('node_modules'))
            ->setBinPath(base_path('bin/browsershot.cjs'))
            ->setCustomTempPath($tempPath)
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->waitUntilNetworkIdle(false)
            ->newHeadless()
            ->noSandbox();

        if ($nodeBinary = $this->resolveNodeBinary()) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if ($chromeBinary = $this->resolveChromeBinary()) {
            $browsershot->setChromePath($chromeBinary);
        }

        return $browsershot;
    }

    private function resolveNodeBinary(): ?string
    {
        $candidates = array_filter([
            env('BROWSERSHOT_NODE_BINARY'),
            '/usr/bin/node',
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Users\\ACER NITRO V15\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\node\\bin\\node.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveChromeBinary(): ?string
    {
        $candidates = array_filter([
            env('BROWSERSHOT_CHROME_PATH'),
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
