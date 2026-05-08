<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Seat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminFlightController extends Controller
{
    public function index(): View
    {
        $flights = Flight::query()
            ->orderByDesc('travel_date')
            ->orderByDesc('departure_time')
            ->paginate(20);

        return view('admin.flights.index', compact('flights'));
    }

    public function seats(Flight $flight): View
    {
        $seats = Seat::query()
            ->with(['traveler', 'booking'])
            ->where('flight_id', $flight->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.flights.seats', compact('flight', 'seats'));
    }

    public function destroy(Flight $flight): RedirectResponse
    {
        DB::transaction(function () use ($flight): void {
            $bookingIds = $flight->bookings()->pluck('id');

            if ($bookingIds->isNotEmpty()) {
                Seat::query()->whereIn('booking_id', $bookingIds)->delete();
            }

            $flight->bookings()->delete();
            $flight->delete();
        });

        return redirect()->route('admin.flights.index')->with('success', 'Flight deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:flights,id'],
        ]);

        $flightIds = $validated['ids'];

        DB::transaction(function () use ($flightIds): void {
            $bookingIds = DB::table('bookings')
                ->whereIn('flight_id', $flightIds)
                ->pluck('id');

            if ($bookingIds->isNotEmpty()) {
                Seat::query()->whereIn('booking_id', $bookingIds)->delete();
            }

            DB::table('bookings')->whereIn('flight_id', $flightIds)->delete();
            Flight::query()->whereIn('id', $flightIds)->delete();
        });

        return redirect()->route('admin.flights.index')->with('success', count($flightIds).' flight(s) deleted successfully.');
    }
}
