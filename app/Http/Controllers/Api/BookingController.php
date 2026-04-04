<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\Seat;
use App\Services\FcmNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class BookingController extends Controller
{
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
                    'total' => $requestedSeats * $lockedFlight->price,
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
            ->with(['flight', 'seats'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Bookings retrieved successfully',
            'data' => $bookings->map(fn (Booking $booking) => $this->bookingPayload($booking, true))->values(),
        ]);
    }

    public function officeBookings(Request $request): JsonResponse
    {
        $bookings = Booking::whereHas('flight', function ($query) use ($request): void {
            $query->where('office_id', $request->user()->id);
        })->with(['flight', 'traveler'])->latest()->get();

        return response()->json([
            'message' => 'Office bookings retrieved successfully',
            'data' => $bookings->map(fn (Booking $booking) => $this->bookingPayload($booking, true, true))->values(),
        ]);
    }

    public function officeBookingsSummary(Request $request): JsonResponse
    {
        $officeId = (int) $request->user()->id;

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
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:pending,confirmed,rejected'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking->loadMissing('flight');

        if ((int) $booking->flight->office_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $booking->update([
            'status' => $request->string('status')->toString(),
        ]);

        $booking->load('flight');

        return response()->json([
            'message' => 'Booking status updated successfully',
            'data' => $this->bookingPayload($booking, true),
        ]);
    }

    public function ticket(Booking $booking)
    {
        if ((int) $booking->traveler_id !== (int) auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'message' => 'Booking is not confirmed yet.',
            ], 422);
        }

        $booking->load(['flight', 'traveler', 'seats']);
        $pdf = Pdf::loadView('traveler.ticket', compact('booking'));
        $filename = 'ticket-'.$booking->id.'.pdf';

        return $pdf->download($filename);
    }

    private function bookingPayload(Booking $booking, bool $includeFlight = false, bool $includeTraveler = false): array
    {
        $payload = [
            'id' => $booking->id,
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
            $payload['flight'] = [
                'id' => $booking->flight->id,
                'from' => $booking->flight->from,
                'to' => $booking->flight->to,
                'travel_date' => Carbon::parse($booking->flight->travel_date)->toDateString(),
                'departure_time' => $booking->flight->departure_time
                    ? Carbon::parse($booking->flight->departure_time)->toIso8601String()
                    : null,
                'office_id' => $booking->flight->office_id,
                'office_name' => $booking->flight->office_name,
            ];
        }

        if ($includeTraveler && $booking->relationLoaded('traveler') && $booking->traveler) {
            $payload['traveler'] = [
                'id' => $booking->traveler->id,
                'name' => $booking->traveler->name,
                'email' => $booking->traveler->email,
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
