<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Seat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
}
