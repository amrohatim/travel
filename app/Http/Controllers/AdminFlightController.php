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

    public function create(): View
    {
        [$offices, $states] = $this->flightFormData();

        return view('admin.flights.create', compact('offices', 'states'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAdminFlight($request);
        $office = $this->findOfficeOrFail((int) $validated['office_id']);
        $departureTime = Carbon::parse($validated['departure_time']);

        Flight::query()->create([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'travel_date' => $departureTime->toDateString(),
            'departure_time' => $departureTime->toDateTimeString(),
            'price' => (int) $validated['price'],
            'seats' => (int) $validated['seats'],
            'office_id' => $office->id,
            'office_name' => $office->name,
            ...$this->discountAttributesFromValidated($validated),
        ]);

        return redirect()->route('admin.flights.index')->with('success', 'Flight created successfully.');
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

    public function edit(Flight $flight): View
    {
        [$offices, $states] = $this->flightFormData();
        $hasBookings = $flight->bookings()->exists();

        return view('admin.flights.edit', compact('flight', 'offices', 'states', 'hasBookings'));
    }

    public function update(Request $request, Flight $flight): RedirectResponse
    {
        $validated = $this->validateAdminFlight($request);
        $office = $this->findOfficeOrFail((int) $validated['office_id']);
        $departureTime = Carbon::parse($validated['departure_time']);
        $hasBookings = $flight->bookings()->exists();

        if ($hasBookings) {
            $hasProtectedChanges = $flight->from !== $validated['from']
                || $flight->to !== $validated['to']
                || (int) $flight->seats !== (int) $validated['seats']
                || (int) $flight->office_id !== (int) $office->id
                || Carbon::parse($flight->departure_time)->notEqualTo($departureTime);

            if ($hasProtectedChanges) {
                return back()
                    ->withErrors([
                        'flight' => 'Only price and discount can be edited when bookings exist.',
                    ])
                    ->withInput();
            }
        }

        $payload = [
            'price' => (int) $validated['price'],
            'office_name' => $office->name,
            ...$this->discountAttributesFromValidated($validated),
        ];

        if (! $hasBookings) {
            $payload = [
                ...$payload,
                'from' => $validated['from'],
                'to' => $validated['to'],
                'travel_date' => $departureTime->toDateString(),
                'departure_time' => $departureTime->toDateTimeString(),
                'seats' => (int) $validated['seats'],
                'office_id' => $office->id,
            ];
        }

        $flight->update($payload);

        return redirect()->route('admin.flights.index')->with('success', 'Flight updated successfully.');
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

    private function validateAdminFlight(Request $request): array
    {
        $validated = $request->validate([
            'office_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'price' => ['required', 'integer', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
            'has_discount' => ['nullable', 'boolean'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $validated['has_discount'] = $request->boolean('has_discount');

        if ($validated['has_discount'] && ! array_key_exists('discount_percentage', $validated)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_percentage' => ['The discount percentage field is required when discount is enabled.'],
            ]);
        }

        if ($validated['has_discount'] && (int) ($validated['discount_percentage'] ?? 0) <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_percentage' => ['The discount percentage must be greater than 0 when discount is enabled.'],
            ]);
        }

        $discountValue = $validated['has_discount']
            ? (int) floor(((int) $validated['price'] * (int) $validated['discount_percentage']) / 100)
            : null;

        if ($discountValue !== null && $discountValue >= (int) $validated['price']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_percentage' => ['The discount must be less than the base price.'],
            ]);
        }

        return $validated;
    }

    private function discountAttributesFromValidated(array $validated): array
    {
        if (! $validated['has_discount']) {
            return [
                'has_discount' => false,
                'discount_percentage' => null,
                'discount_value' => null,
            ];
        }

        $discountPercentage = (int) $validated['discount_percentage'];
        $discountValue = (int) floor(((int) $validated['price'] * $discountPercentage) / 100);

        return [
            'has_discount' => true,
            'discount_percentage' => $discountPercentage,
            'discount_value' => $discountValue,
        ];
    }

    private function findOfficeOrFail(int $officeId): User
    {
        $office = User::query()
            ->where('id', $officeId)
            ->where('role', 'office')
            ->first();

        if (! $office) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'office_id' => ['The selected office is invalid.'],
            ]);
        }

        return $office;
    }

    private function flightFormData(): array
    {
        $offices = User::query()
            ->where('role', 'office')
            ->orderBy('name')
            ->get(['id', 'name']);

        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return [$offices, $states];
    }
}
