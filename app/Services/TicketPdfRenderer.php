<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\Browsershot\Browsershot;
use Throwable;

class TicketPdfRenderer
{
    public function render(Booking $booking): string
    {
        $html = view('traveler.ticket', compact('booking'))->render();

        try {
            return $this->browsershot($html)->pdf();
        } catch (Throwable) {
            return Pdf::loadView('traveler.ticket', compact('booking'))->output();
        }
    }

    private function browsershot(string $html): Browsershot
    {
        $browsershot = Browsershot::html($html)
            ->setNodeModulePath(base_path('node_modules'))
            ->setBinPath(base_path('bin/browsershot.cjs'))
            ->setCustomTempPath(storage_path('app/browsershot'))
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
