<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Seat;
use App\Models\State;
use App\Models\User;
use App\Services\FutureFlightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminFlightController extends Controller
{
    public function __construct(
        private readonly FutureFlightService $futureFlightService
    ) {}

    public function index(): View
    {
        $flights = Flight::query()
            ->orderByDesc('travel_date')
            ->orderByDesc('departure_time')
            ->paginate(20);

        return view('admin.flights.index', compact('flights'));
    }

    public function createFuture(): View
    {
        $offices = User::query()
            ->where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name']);

        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.flights.create-future', compact('offices', 'states'));
    }

    public function storeFuture(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'office_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'price' => ['required', 'integer', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
            'days_ahead' => ['required', 'integer', 'in:30,60,90'],
        ]);

        $office = User::query()
            ->where('id', $validated['office_id'])
            ->where('role', 'office')
            ->first();

        if (! $office) {
            return back()
                ->withErrors(['office_id' => 'The selected office is invalid.'])
                ->withInput();
        }

        $result = $this->futureFlightService->createWeeklyFlightsForOffice($office, [
            'from' => $validated['from'],
            'to' => $validated['to'],
            'departure_time' => $validated['departure_time'],
            'price' => (int) $validated['price'],
            'seats' => (int) $validated['seats'],
            'days_ahead' => (int) $validated['days_ahead'],
        ]);

        $summary = sprintf(
            'Future flights processed for %s. Created: %d (%s). Skipped: %d (%s).',
            $office->name,
            $result['created_count'],
            $this->formatDatesForFlash($result['created_dates']),
            $result['skipped_count'],
            $this->formatDatesForFlash($result['skipped_dates'])
        );

        return redirect()
            ->route('admin.flights.future.create')
            ->with('success', $summary);
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

    /**
     * @param  array<int, string>  $dates
     */
    private function formatDatesForFlash(array $dates): string
    {
        return $dates === [] ? 'none' : implode(', ', $dates);
    }
}
