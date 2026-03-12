<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flight;
use App\Models\Seat;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $flights = Flight::query()
            ->when($request->filled('date'), fn ($query) => $query->whereDate('travel_date', $request->query('date')))
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
            'office_id' => $request->user()->id,
            'office_name' => $request->user()->name,
        ]);

        return response()->json([
            'message' => 'Flight created successfully',
            'data' => $this->flightPayload($flight),
        ], 201);
    }

    public function officeToday(Request $request): JsonResponse
    {
        $queryDate = $request->query('date');
        $targetDate = $queryDate && strtotime($queryDate) !== false
            ? Carbon::parse($queryDate)->toDateString()
            : Carbon::today(config('app.timezone'))->toDateString();

        $flights = Flight::query()
            ->where('office_id', $request->user()->id)
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
        $queryDate = $request->query('date');
        $targetDate = $queryDate && strtotime($queryDate) !== false
            ? Carbon::parse($queryDate)->toDateString()
            : Carbon::today(config('app.timezone'))->toDateString();

        $flights = Flight::query()
            ->where('office_id', $request->user()->id)
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
        $queryDate = $request->query('date');
        $targetDate = $queryDate && strtotime($queryDate) !== false
            ? Carbon::parse($queryDate)->toDateString()
            : Carbon::today(config('app.timezone'))->toDateString();

        $flights = Flight::query()
            ->where('office_id', $request->user()->id)
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
        if ((int) $flight->office_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $passengers = Seat::query()
            ->where('flight_id', $flight->id)
            ->whereHas('booking', fn ($query) => $query->where('status', 'confirmed'))
            ->orderBy('id')
            ->get(['id', 'traveler_name'])
            ->map(fn (Seat $seat) => [
                'id' => $seat->id,
                'traveler_name' => $seat->traveler_name,
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
            'seats' => $flight->seats,
            'office_id' => $flight->office_id,
            'office_name' => $flight->office_name,
        ];
    }
}
