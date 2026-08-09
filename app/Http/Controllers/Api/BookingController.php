<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\Seat;
use App\Services\ActiveOfficeContext;
use App\Services\FcmNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function __construct(
        private readonly ActiveOfficeContext $activeOfficeContext
    ) {}

    public function store(Request $request, Flight $flight): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seats_booked' => ['required', 'integer', 'min:1'],
            'passengers' => ['required', 'array'],
            'passengers.*' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $requestedSeats = (int) $request->input('seats_booked');
        $passengers = array_values($request->input('passengers', []));

        if (count($passengers) !== $requestedSeats) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'passengers' => ['Passengers count must match seats_booked.'],
                ],
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('bookings', 'public');
        }

        $booking = null;

        try {
            DB::transaction(function () use ($request, $flight, $requestedSeats, $passengers, $imagePath, &$booking): void {
                $lockedFlight = Flight::whereKey($flight->id)->lockForUpdate()->firstOrFail();

                if ($requestedSeats > $lockedFlight->seats) {
                    throw new \RuntimeException('insufficient_seats');
                }

                $booking = Booking::create([
                    'flight_id' => $lockedFlight->id,
                    'office_id' => $lockedFlight->office_id,
                    'traveler_id' => $request->user()->id,
                    'seats_booked' => $requestedSeats,
                    'total' => $requestedSeats * $lockedFlight->finalPrice(),
                    'image' => $imagePath,
                    'status' => 'pending',
                ]);

                foreach ($passengers as $passengerName) {
                    Seat::create([
                        'traveler_id' => $request->user()->id,
                        'flight_id' => $lockedFlight->id,
                        'booking_id' => $booking->id,
                        'traveler_name' => $passengerName,
                    ]);
                }

                $lockedFlight->decrement('seats', $requestedSeats);
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'insufficient_seats') {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'seats_booked' => ['Not enough seats available.'],
                    ],
                ], 422);
            }

            throw $exception;
        }

        $booking->load(['flight', 'seats']);

        try {
            app(FcmNotificationService::class)->sendNewBookingToOffice($booking);
        } catch (\Throwable $exception) {
            Log::warning('Sending booking notification failed.', [
                'booking_id' => $booking->id,
                'office_id' => $booking->office_id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Booking created successfully',
            'data' => $this->bookingPayload($booking, true),
        ], 201);
    }

    public function travelerBookings(Request $request): JsonResponse
    {
        $bookings = Booking::where('traveler_id', $request->user()->id)
            ->with(['flight.officeUser.location', 'seats'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Bookings retrieved successfully',
            'data' => $bookings->map(fn (Booking $booking) => $this->bookingPayload($booking, true))->values(),
        ]);
    }

    public function officeBookings(Request $request): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);

        $bookings = Booking::whereHas('flight', function ($query) use ($office): void {
            $query->where('office_id', $office->id);
        })->with(['flight.officeUser.location', 'traveler', 'seats'])->latest()->get();

        return response()->json([
            'message' => 'Office bookings retrieved successfully',
            'data' => $bookings->map(fn (Booking $booking) => $this->bookingPayload($booking, true, true))->values(),
        ]);
    }

    public function officeBookingsSummary(Request $request): JsonResponse
    {
        $officeId = (int) $this->activeOfficeContext->resolve($request)->id;

        $summary = Booking::query()
            ->where('office_id', $officeId)
            ->where('status', '!=', 'rejected')
            ->where('demanded', true)
            ->selectRaw('COALESCE(SUM(total * seats_booked), 0) as total_sum, COALESCE(SUM(seats_booked), 0) as seats_sum')
            ->first();

        return response()->json([
            'message' => 'Office bookings summary retrieved successfully',
            'data' => [
                'total_sum' => (int) ($summary->total_sum ?? 0),
                'seats_sum' => (int) ($summary->seats_sum ?? 0),
            ],
        ]);
    }

    public function updateStatus(Request $request, Booking $booking): JsonResponse
    {
        $office = $this->activeOfficeContext->resolve($request);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:pending,confirmed,rejected'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = $request->string('status')->toString();
        $previousStatus = null;

        try {
            $booking = DB::transaction(function () use ($booking, $newStatus, $office, &$previousStatus): Booking {
                $lockedBooking = Booking::query()
                    ->lockForUpdate()
                    ->firstWhere('id', $booking->id);

                if (! $lockedBooking) {
                    abort(404);
                }

                $lockedFlight = Flight::query()
                    ->lockForUpdate()
                    ->firstWhere('id', $lockedBooking->flight_id);

                if (! $lockedFlight) {
                    abort(404);
                }

                if ((int) $lockedFlight->office_id !== (int) $office->id) {
                    abort(response()->json([
                        'message' => 'Forbidden',
                    ], 403));
                }

                $previousStatus = (string) $lockedBooking->status;
                $seatDelta = (int) $lockedBooking->seats_booked;

                if ($previousStatus !== 'rejected' && $newStatus === 'rejected') {
                    $lockedFlight->increment('seats', $seatDelta);
                } elseif ($previousStatus === 'rejected' && $newStatus !== 'rejected') {
                    if ($seatDelta > (int) $lockedFlight->seats) {
                        throw new \RuntimeException('insufficient_seats_for_status_change');
                    }

                    $lockedFlight->decrement('seats', $seatDelta);
                }

                $lockedBooking->update([
                    'status' => $newStatus,
                ]);

                return $lockedBooking->fresh(['flight']);
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'insufficient_seats_for_status_change') {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'status' => ['Not enough seats available to reactivate this booking.'],
                    ],
                ], 422);
            }

            throw $exception;
        }

        if ($previousStatus !== 'confirmed' && $newStatus === 'confirmed') {
            try {
                app(FcmNotificationService::class)->sendBookingConfirmedToTraveler($booking);
            } catch (\Throwable $exception) {
                Log::warning('Sending traveler booking confirmation notification failed.', [
                    'booking_id' => $booking->id,
                    'traveler_id' => $booking->traveler_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Booking status updated successfully',
            'data' => $this->bookingPayload($booking, true),
        ]);
    }

    private function bookingPayload(Booking $booking, bool $includeFlight = false, bool $includeTraveler = false): array
    {
        $payload = [
            'id' => $booking->id,
            'serial_number' => $booking->serial_number,
            'flight_id' => $booking->flight_id,
            'office_id' => $booking->office_id,
            'traveler_id' => $booking->traveler_id,
            'seats_booked' => $booking->seats_booked,
            'total' => $booking->total,
            'status' => $booking->status,
            'image' => $this->imageUrl($booking->image),
            'created_at' => $booking->created_at ? Carbon::parse($booking->created_at)->toIso8601String() : null,
        ];

        if ($includeFlight && $booking->relationLoaded('flight') && $booking->flight) {
            $location = $booking->flight->officeUser?->location;

            $payload['flight'] = [
                'id' => $booking->flight->id,
                'from' => $booking->flight->from,
                'to' => $booking->flight->to,
                'travel_date' => Carbon::parse($booking->flight->travel_date)->toDateString(),
                'departure_time' => $booking->flight->departure_time
                    ? Carbon::parse($booking->flight->departure_time)->toIso8601String()
                    : null,
                'price' => $booking->flight->price,
                'has_discount' => $booking->flight->normalizedDiscount()['has_discount'],
                'discount_percentage' => $booking->flight->normalizedDiscount()['discount_percentage'],
                'discount_value' => $booking->flight->normalizedDiscount()['discount_value'],
                'final_price' => $booking->flight->normalizedDiscount()['final_price'],
                'office_id' => $booking->flight->office_id,
                'office_name' => $booking->flight->office_name,
                'location' => $location
                    ? [
                        'lat' => (float) $location->lat,
                        'lng' => (float) $location->lng,
                    ]
                    : null,
            ];
        }

        if ($includeTraveler && $booking->relationLoaded('traveler') && $booking->traveler) {
            $payload['traveler'] = [
                'id' => $booking->traveler->id,
                'name' => $booking->traveler->name,
                'email' => $booking->traveler->email,
                'phone' => $booking->traveler->phone,
            ];
        }

        if ($booking->relationLoaded('seats')) {
            $payload['seats'] = $booking->seats->map(fn (Seat $seat) => [
                'id' => $seat->id,
                'traveler_id' => $seat->traveler_id,
                'flight_id' => $seat->flight_id,
                'booking_id' => $seat->booking_id,
                'traveler_name' => $seat->traveler_name,
            ])->values();
        }

        return $payload;
    }

    private function imageUrl(?string $image): ?string
    {
        if (! $image || trim($image) === '') {
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
