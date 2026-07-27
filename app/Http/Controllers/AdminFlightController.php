<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Seat;
use App\Models\State;
use App\Models\User;
use App\Services\FutureFlightService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminFlightController extends Controller
{
    public function __construct(
        private readonly FutureFlightService $futureFlightService
    ) {}

    public function index(Request $request): View
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $offices = User::query()
            ->where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name']);

        $flights = Flight::query()
            ->when($request->filled('from'), fn ($query) => $query->where('from', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($query) => $query->where('to', $request->string('to')->toString()))
            ->when($request->filled('travel_date'), fn ($query) => $query->whereDate('travel_date', $request->string('travel_date')->toString()))
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', (int) $request->input('office_id')))
            ->orderByRaw('CASE WHEN travel_date >= ? THEN 0 ELSE 1 END', [$today])
            ->orderByRaw('CASE WHEN travel_date >= ? THEN travel_date END ASC', [$today])
            ->orderByRaw('CASE WHEN travel_date >= ? THEN departure_time END ASC', [$today])
            ->orderByRaw('CASE WHEN travel_date < ? THEN travel_date END DESC', [$today])
            ->orderByRaw('CASE WHEN travel_date < ? THEN departure_time END DESC', [$today])
            ->paginate(20)
            ->withQueryString();

        return view('admin.flights.index', compact('flights', 'states', 'offices'));
    }

    public function createFuture(Request $request): View
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $offices = User::query()
            ->where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name']);

        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedOfficeId = $request->filled('office_id')
            ? (int) $request->query('office_id')
            : (old('office_id') ? (int) old('office_id') : null);

        $selectedOffice = null;
        $officeFlights = null;

        if ($selectedOfficeId) {
            $selectedOffice = User::query()
                ->where('role', 'office')
                ->where('id', $selectedOfficeId)
                ->first();

            if ($selectedOffice) {
                $officeFlights = Flight::query()
                    ->where('office_id', $selectedOffice->id)
                    ->orderByRaw('ABS(DATEDIFF(travel_date, ?)) ASC', [$today])
                    ->orderBy('travel_date')
                    ->orderBy('departure_time')
                    ->paginate(10)
                    ->withQueryString();
            }
        }

        return view('admin.flights.create-future', compact('offices', 'states', 'selectedOffice', 'officeFlights'));
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
            ->route('admin.flights.future.create', ['office_id' => $office->id])
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
