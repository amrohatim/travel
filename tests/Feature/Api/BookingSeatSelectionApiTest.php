<?php

use App\Models\Booking;
use App\Models\Flight;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function bookingSeatHeaders(User $user, ?int $officeId = null): array
{
    $token = $user->createToken('seat-test-token')->plainTextToken;

    $headers = [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'X-Device-ID' => 'seat-test-device-'.$user->id,
    ];

    if ($officeId !== null) {
        $headers['X-Office-ID'] = (string) $officeId;
    }

    return $headers;
}

function createSeatTestFlight(User $office): Flight
{
    return Flight::create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => now()->addDay()->toDateString(),
        'departure_time' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
        'price' => 50000,
        'seats' => 49,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);
}

test('traveler can create booking with selected seat numbers', function () {
    Storage::fake('public');

    $office = User::factory()->create(['role' => 'office', 'phone' => '0911111101']);
    $traveler = User::factory()->create(['role' => 'traveler', 'phone' => '0911111102']);
    $flight = createSeatTestFlight($office);

    $response = $this->withHeaders(bookingSeatHeaders($traveler))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 2,
            'passengers' => ['Passenger One', 'Passenger Two'],
            'selected_seat_numbers' => [13, 14],
            'image' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.selected_seat_numbers.0', 13)
        ->assertJsonPath('data.selected_seat_numbers.1', 14)
        ->assertJsonPath('data.seats.0.seat_number', null);

    $this->assertDatabaseHas('bookings', [
        'flight_id' => $flight->id,
        'traveler_id' => $traveler->id,
        'status' => 'pending',
    ]);
});

test('pending booking blocks duplicate seat selection and rejected booking releases it', function () {
    Storage::fake('public');

    $office = User::factory()->create(['role' => 'office', 'phone' => '0911111103']);
    $travelerA = User::factory()->create(['role' => 'traveler', 'phone' => '0911111104']);
    $travelerB = User::factory()->create(['role' => 'traveler', 'phone' => '0911111105']);
    $flight = createSeatTestFlight($office);

    $firstBookingResponse = $this->withHeaders(bookingSeatHeaders($travelerA))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 1,
            'passengers' => ['Passenger A'],
            'selected_seat_numbers' => [21],
            'image' => UploadedFile::fake()->image('receipt-a.jpg'),
        ]);

    $firstBookingResponse->assertCreated();
    $bookingId = (int) $firstBookingResponse->json('data.id');

    $this->withHeaders(bookingSeatHeaders($travelerB))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 1,
            'passengers' => ['Passenger B'],
            'selected_seat_numbers' => [21],
            'image' => UploadedFile::fake()->image('receipt-b.jpg'),
        ])->assertStatus(422)
        ->assertJsonPath(
            'errors.selected_seat_numbers.0',
            'One or more selected seats are already reserved.',
        );

    $this->withHeaders(bookingSeatHeaders($office, $office->id))
        ->patchJson('/api/v1/office/bookings/'.$bookingId.'/status', [
            'status' => 'rejected',
        ])->assertOk();

    $this->withHeaders(bookingSeatHeaders($travelerB))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 1,
            'passengers' => ['Passenger B'],
            'selected_seat_numbers' => [21],
            'image' => UploadedFile::fake()->image('receipt-c.jpg'),
        ])->assertCreated();
});

test('confirming booking assigns seat numbers and office can edit them', function () {
    Storage::fake('public');

    $office = User::factory()->create(['role' => 'office', 'phone' => '0911111106']);
    $traveler = User::factory()->create(['role' => 'traveler', 'phone' => '0911111107']);
    $flight = createSeatTestFlight($office);

    $createResponse = $this->withHeaders(bookingSeatHeaders($traveler))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 2,
            'passengers' => ['Passenger One', 'Passenger Two'],
            'selected_seat_numbers' => [31, 32],
            'image' => UploadedFile::fake()->image('receipt-d.jpg'),
        ]);

    $createResponse->assertCreated();
    $bookingId = (int) $createResponse->json('data.id');

    $confirmResponse = $this->withHeaders(bookingSeatHeaders($office, $office->id))
        ->patchJson('/api/v1/office/bookings/'.$bookingId.'/status', [
            'status' => 'confirmed',
        ]);

    $confirmResponse->assertOk();
    expect(collect($confirmResponse->json('data.seats'))->pluck('seat_number')->sort()->values()->all())
        ->toBe([31, 32]);

    $seatUpdateResponse = $this->withHeaders(bookingSeatHeaders($office, $office->id))
        ->patchJson('/api/v1/office/bookings/'.$bookingId.'/seats', [
            'seat_numbers' => [40, 41],
        ]);

    $seatUpdateResponse->assertOk()
        ->assertJsonPath('data.selected_seat_numbers.0', 40)
        ->assertJsonPath('data.selected_seat_numbers.1', 41);

    expect(
        Seat::query()
            ->where('booking_id', $bookingId)
            ->pluck('seat_number')
            ->sort()
            ->values()
            ->all()
    )->toBe([40, 41]);
});

test('reserved seats endpoint returns pending and confirmed seats for a flight', function () {
    $office = User::factory()->create(['role' => 'office', 'phone' => '0911111108']);
    $traveler = User::factory()->create(['role' => 'traveler', 'phone' => '0911111109']);
    $flight = createSeatTestFlight($office);

    $pendingBooking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 50000,
        'status' => 'pending',
        'selected_seat_numbers' => [11],
    ]);

    $confirmedBooking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 50000,
        'status' => 'confirmed',
        'selected_seat_numbers' => [12],
    ]);

    Seat::create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $pendingBooking->id,
        'traveler_name' => 'Pending Passenger',
        'seat_number' => null,
    ]);

    Seat::create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $confirmedBooking->id,
        'traveler_name' => 'Confirmed Passenger',
        'seat_number' => 12,
    ]);

    $response = $this->withHeaders(bookingSeatHeaders($traveler))
        ->getJson('/api/v1/flights/'.$flight->id.'/reserved-seats');

    $response->assertOk();
    expect($response->json('data.reserved_seat_numbers'))->toBe([11, 12]);
});
