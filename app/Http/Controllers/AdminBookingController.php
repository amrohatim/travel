<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Seat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminBookingController extends Controller
{
    public function index(): View
    {
        $bookings = Booking::query()
            ->with(['flight', 'traveler', 'office'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking): View
    {
        $booking->load([
            'flight',
            'traveler',
            'office',
            'seats' => fn ($query) => $query->with('traveler')->orderBy('id'),
        ]);

        $bookingImageUrl = $this->bookingImageUrl($booking->image);

        return view('admin.bookings.show', compact('booking', 'bookingImageUrl'));
    }

    public function seats(Booking $booking): View
    {
        $seats = Seat::query()
            ->with(['traveler', 'booking'])
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.bookings.seats', compact('booking', 'seats'));
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        DB::transaction(function () use ($booking): void {
            Seat::query()->where('booking_id', $booking->id)->delete();
            $booking->delete();
        });

        return redirect()->route('admin.bookings.index')->with('success', 'Booking deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:bookings,id'],
        ]);

        $bookingIds = $validated['ids'];

        DB::transaction(function () use ($bookingIds): void {
            Seat::query()->whereIn('booking_id', $bookingIds)->delete();
            Booking::query()->whereIn('id', $bookingIds)->delete();
        });

        return redirect()->route('admin.bookings.index')->with('success', count($bookingIds).' booking(s) deleted successfully.');
    }

    private function bookingImageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        $cleanImage = ltrim($image, '/');

        if (Str::startsWith($cleanImage, 'storage/')) {
            return url($cleanImage);
        }

        if (Storage::disk('public')->exists($cleanImage)) {
            return url('storage/'.$cleanImage);
        }

        return url($cleanImage);
    }
}
