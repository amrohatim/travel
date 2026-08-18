<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\Seat;
use App\Services\ActiveOfficeContext;
use App\Services\FutureFlightService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlightController extends Controller
{
    public function __construct(
        private readonly FutureFlightService $futureFlightService,
        private readonly ActiveOfficeContext $activeOfficeContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $endDate = Carbon::today(config('app.timezone'))->addDays(7)->toDateString();

        $flights = Flight::query()
            ->when(
                $request->filled('date'),
                fn ($query) => $query->whereDate('travel_date', $request->query('date')),
                fn ($query) => $query
                    ->whereDate('travel_date', '>=', $today)
                    ->whereDate('travel_date', '<=', $endDate)
            )
            ->when($request->filled('from'), fn ($query) => $query->where('from', 'like', '%'.$request->query('from').'%'))
            ->when($request->filled('to'), fn ($query) => $query->where('to', 'like', '%'.$request->query('to').'%'))
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', (int) $request->query('office_id')))
            ->orderBy('departure_time')
            ->get();

        return response()->json([
            'message' => 'Flights retrieved successfully',
            'data' => $flights->map(fn (Flight $flight) => $this->flightPayload($flight))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'price' => ['required', 'integer', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $departureTime = Carbon::parse($request->input('departure_time'));

        $flight = Flight::create([
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
            'travel_date' => $departureTime->toDateString(),
            'departure_time' => $departureTime->toDateTimeString(),
            'price' => (int) $request->input('price'),
            'seats' => (int) $request->input('seats'),
            'office_id' => $office->id,
            'office_name' => $office->name,
        ]);

        return response()->json([
            'message' => 'Flight created successfully',
            'data' => $this->flightPayload($flight),
        ], 201);
    }

    public function storeFuture(Request $request): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'price' => ['required', 'integer', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
            'days_ahead' => ['required', 'integer', 'in:30,60,90'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->futureFlightService->createWeeklyFlightsForOffice($office, [
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
            'departure_time' => $request->string('departure_time')->toString(),
            'price' => (int) $request->input('price'),
            'seats' => (int) $request->input('seats'),
            'days_ahead' => (int) $request->input('days_ahead'),
        ]);

        return response()->json([
            'message' => 'Future flights processed successfully',
            'data' => [
                'created_dates' => $result['created_dates'],
                'skipped_dates' => $result['skipped_dates'],
                'created_count' => $result['created_count'],
                'skipped_count' => $result['skipped_count'],
                'flights' => collect($result['flights'])->map(
                    fn (Flight $flight) => $this->flightPayload($flight)
                )->values(),
            ],
        ]);
    }

    public function update(Request $request, Flight $flight): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);

        if ((int) $flight->office_id !== (int) $office->id) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'price' => ['required', 'integer', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $departureTime = Carbon::parse($request->input('departure_time'));
        $hasBookings = $flight->bookings()->exists();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $price = (int) $request->input('price');
        $seats = (int) $request->input('seats');

        if ($hasBookings) {
            $hasNonPriceChanges = $flight->from !== $from
                || $flight->to !== $to
                || (int) $flight->seats !== $seats
                || Carbon::parse($flight->departure_time)->notEqualTo($departureTime);

            if ($hasNonPriceChanges) {
                return response()->json([
                    'message' => 'Only the price can be edited when bookings exist.',
                ], 422);
            }

            $flight->update([
                'price' => $price,
            ]);

            return response()->json([
                'message' => 'Flight updated successfully',
                'data' => $this->flightPayload($flight->fresh()),
            ]);
        }

        $flight->update([
            'from' => $from,
            'to' => $to,
            'travel_date' => $departureTime->toDateString(),
            'departure_time' => $departureTime->toDateTimeString(),
            'price' => $price,
            'seats' => $seats,
        ]);

        return response()->json([
            'message' => 'Flight updated successfully',
            'data' => $this->flightPayload($flight->fresh()),
        ]);
    }

    public function officeToday(Request $request): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);
        $queryDate = $request->query('date');
        $targetDate = $queryDate && strtotime($queryDate) !== false
            ? Carbon::parse($queryDate)->toDateString()
            : Carbon::today(config('app.timezone'))->toDateString();

        $flights = Flight::query()
            ->where('office_id', $office->id)
            ->whereDate('travel_date', $targetDate)
            ->orderBy('departure_time')
            ->get();

        return response()->json([
            'message' => 'Today flights retrieved successfully',
            'data' => $flights->map(fn (Flight $flight) => $this->flightPayload($flight))->values(),
        ]);
    }

    public function officeUpcoming(Request $request): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);
        $queryDate = $request->query('date');
        $targetDate = $queryDate && strtotime($queryDate) !== false
            ? Carbon::parse($queryDate)->toDateString()
            : Carbon::today(config('app.timezone'))->toDateString();

        $flights = Flight::query()
            ->where('office_id', $office->id)
            ->whereDate('travel_date', '>', $targetDate)
            ->orderBy('departure_time')
            ->get();

        return response()->json([
            'message' => 'Upcoming flights retrieved successfully',
            'data' => $flights->map(fn (Flight $flight) => $this->flightPayload($flight))->values(),
        ]);
    }

    public function officePrevious(Request $request): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);
        $queryDate = $request->query('date');
        $targetDate = $queryDate && strtotime($queryDate) !== false
            ? Carbon::parse($queryDate)->toDateString()
            : Carbon::today(config('app.timezone'))->toDateString();

        $flights = Flight::query()
            ->where('office_id', $office->id)
            ->whereDate('travel_date', '<', $targetDate)
            ->orderByDesc('departure_time')
            ->get();

        return response()->json([
            'message' => 'Previous flights retrieved successfully',
            'data' => $flights->map(fn (Flight $flight) => $this->flightPayload($flight))->values(),
        ]);
    }

    public function officePassengers(Request $request, Flight $flight): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);

        if ((int) $flight->office_id !== (int) $office->id) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $passengers = Seat::query()
            ->where('flight_id', $flight->id)
            ->whereHas('booking', fn ($query) => $query->where('status', 'confirmed'))
            ->with([
                'traveler:id,phone',
                'booking:id,serial_number',
            ])
            ->orderBy('id')
            ->get(['id', 'traveler_name', 'traveler_id', 'booking_id'])
            ->map(fn (Seat $seat) => [
                'id' => $seat->id,
                'traveler_name' => $seat->traveler_name,
                'traveler_phone' => $seat->traveler?->phone,
                'booking_serial_number' => $seat->booking?->serial_number,
                'seat_number' => $seat->seat_number === null ? null : (int) $seat->seat_number,
            ])
            ->values();

        return response()->json([
            'message' => 'Flight passengers retrieved successfully',
            'data' => [
                'flight' => $this->flightPayload($flight),
                'passengers' => $passengers,
            ],
        ]);
    }

    public function reservedSeats(Request $request, Flight $flight): JsonResponse
    {
        $reservedSeatNumbers = Booking::query()
            ->where('flight_id', $flight->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with('seats:id,booking_id,seat_number')
            ->get(['id', 'selected_seat_numbers'])
            ->flatMap(function ($booking) {
                $selectedSeatNumbers = collect($booking->selected_seat_numbers ?? [])
                    ->map(static fn ($seatNumber) => (int) $seatNumber);
                $assignedSeatNumbers = $booking->seats
                    ->pluck('seat_number')
                    ->filter(static fn ($seatNumber) => $seatNumber !== null)
                    ->map(static fn ($seatNumber) => (int) $seatNumber);

                return $selectedSeatNumbers->merge($assignedSeatNumbers);
            })
            ->unique()
            ->sort()
            ->values()
            ->all();

        return response()->json([
            'message' => 'Reserved seats retrieved successfully',
            'data' => [
                'flight_id' => $flight->id,
                'reserved_seat_numbers' => $reservedSeatNumbers,
            ],
        ]);
    }

    private function flightPayload(Flight $flight): array
    {
        return [
            'id' => $flight->id,
            'from' => $flight->from,
            'to' => $flight->to,
            'travel_date' => Carbon::parse($flight->travel_date)->toDateString(),
            'departure_time' => $flight->departure_time
                ? Carbon::parse($flight->departure_time)->toIso8601String()
                : null,
            'price' => $flight->price,
            'has_discount' => $flight->normalizedDiscount()['has_discount'],
            'discount_percentage' => $flight->normalizedDiscount()['discount_percentage'],
            'discount_value' => $flight->normalizedDiscount()['discount_value'],
            'final_price' => $flight->normalizedDiscount()['final_price'],
            'seats' => $flight->seats,
            'office_id' => $flight->office_id,
            'office_name' => $flight->office_name,
        ];
    }
}
