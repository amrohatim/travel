<?php

use App\Models\Booking;
use App\Models\Flight;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

function tokenHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('it registers traveler and returns token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Traveler One',
        'email' => 'traveler1@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Registered successfully')
        ->assertJsonPath('data.user.email', 'traveler1@example.com')
        ->assertJsonPath('data.user.role', 'traveler');

    expect($response->json('data.token'))->not->toBeEmpty();
});

test('it logs in and logs out with sanctum token', function () {
    $user = User::factory()->create([
        'email' => 'traveler2@example.com',
        'password' => Hash::make('password123'),
        'role' => 'traveler',
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $login->assertOk()
        ->assertJsonPath('message', 'Logged in successfully');

    $token = $login->json('data.token');

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->postJson('/api/v1/auth/logout')->assertOk()
        ->assertJsonPath('message', 'Logged out successfully');
});

test('it requires authentication for protected endpoints', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

test('it lists offices with bankak fields', function () {
    User::factory()->create([
        'name' => 'Office One',
        'role' => 'office',
        'bankak_name' => 'Bankak A',
        'bankak_number' => 123456,
    ]);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $response = $this->withHeaders(tokenHeaders($traveler))
        ->getJson('/api/v1/offices');

    $response->assertOk()
        ->assertJsonPath('message', 'Offices retrieved successfully')
        ->assertJsonPath('data.0.bankak_name', 'Bankak A')
        ->assertJsonPath('data.0.bankak_number', 123456);
});

test('office can view and update own profile', function () {
    Storage::fake('public');

    $office = User::factory()->create([
        'role' => 'office',
        'name' => 'Office Profile',
        'phone' => '0111111',
        'bankak_name' => 'Old Bankak',
        'bankak_number' => 222222,
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->getJson('/api/v1/office/profile')
        ->assertOk()
        ->assertJsonPath('message', 'Office profile retrieved successfully')
        ->assertJsonPath('data.name', 'Office Profile')
        ->assertJsonPath('data.phone', '0111111')
        ->assertJsonPath('data.bankak_name', 'Old Bankak')
        ->assertJsonPath('data.bankak_number', 222222);

    $response = $this->withHeaders(tokenHeaders($office))
        ->post('/api/v1/office/profile', [
            'name' => 'Office Updated',
            'phone' => '0538573',
            'bankak_name' => 'New Bankak',
            'bankak_number' => 444444,
            'image' => UploadedFile::fake()->image('office.jpg'),
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Office profile updated successfully')
        ->assertJsonPath('data.name', 'Office Updated')
        ->assertJsonPath('data.phone', '0538573')
        ->assertJsonPath('data.bankak_name', 'New Bankak')
        ->assertJsonPath('data.bankak_number', 444444);

    $this->assertDatabaseHas('users', [
        'id' => $office->id,
        'name' => 'Office Updated',
        'phone' => '0538573',
        'bankak_name' => 'New Bankak',
        'bankak_number' => 444444,
    ]);
});

test('it lists flights and supports filtering', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office A']);

    Flight::create([
        'from' => 'Dubai',
        'to' => 'Cairo',
        'travel_date' => '2026-05-10',
        'departure_time' => '2026-05-10 10:30:00',
        'price' => 400,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Flight::create([
        'from' => 'Abu Dhabi',
        'to' => 'Amman',
        'travel_date' => '2026-05-11',
        'departure_time' => '2026-05-11 11:30:00',
        'price' => 500,
        'seats' => 25,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $response = $this->getJson('/api/v1/flights?date=2026-05-10&from=Dub&to=Cai');

    $response->assertOk()
        ->assertJsonPath('message', 'Flights retrieved successfully');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.from'))->toBe('Dubai');
});

test('office can create flight and travel date is derived from departure time', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office B']);

    $response = $this->withHeaders(tokenHeaders($office))->postJson('/api/v1/office/flights', [
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Flight created successfully')
        ->assertJsonPath('data.travel_date', '2026-06-01');

    $this->assertDatabaseHas('flights', [
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-01',
        'office_id' => $office->id,
    ]);
});

test('traveler cannot access office flight creation endpoint', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);

    $this->withHeaders(tokenHeaders($traveler))->postJson('/api/v1/office/flights', [
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
    ])->assertForbidden();
});

test('traveler can create booking and seats are decremented', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office C']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Jeddah',
        'travel_date' => '2026-07-10',
        'departure_time' => '2026-07-10 09:00:00',
        'price' => 250,
        'seats' => 5,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $response = $this->withHeaders(tokenHeaders($traveler))
        ->postJson('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 2,
        ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Booking created successfully')
        ->assertJsonPath('data.status', 'pending');

    expect($flight->fresh()->seats)->toBe(3);
    $this->assertDatabaseHas('bookings', [
        'flight_id' => $flight->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'status' => 'pending',
    ]);
});

test('booking creation rejects seat requests greater than available seats', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office D']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Istanbul',
        'travel_date' => '2026-08-01',
        'departure_time' => '2026-08-01 20:00:00',
        'price' => 700,
        'seats' => 1,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $this->withHeaders(tokenHeaders($traveler))
        ->postJson('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 2,
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('office sees only own bookings and can only update own booking status', function () {
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flightA = Flight::create([
        'from' => 'Dubai',
        'to' => 'Muscat',
        'travel_date' => '2026-09-01',
        'departure_time' => '2026-09-01 08:00:00',
        'price' => 200,
        'seats' => 10,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);

    $flightB = Flight::create([
        'from' => 'Dubai',
        'to' => 'Doha',
        'travel_date' => '2026-09-02',
        'departure_time' => '2026-09-02 09:00:00',
        'price' => 220,
        'seats' => 10,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    $bookingA = Booking::create([
        'flight_id' => $flightA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'status' => 'pending',
    ]);

    $bookingB = Booking::create([
        'flight_id' => $flightB->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'status' => 'pending',
    ]);

    $listResponse = $this->withHeaders(tokenHeaders($officeA))->getJson('/api/v1/office/bookings');

    $listResponse->assertOk();
    expect($listResponse->json('data'))->toHaveCount(1);
    expect($listResponse->json('data.0.id'))->toBe($bookingA->id);

    $this->withHeaders(tokenHeaders($officeA))
        ->patchJson('/api/v1/office/bookings/'.$bookingA->id.'/status', [
            'status' => 'confirmed',
        ])->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->withHeaders(tokenHeaders($officeA))
        ->patchJson('/api/v1/office/bookings/'.$bookingA->id.'/status', [
            'status' => 'pending',
        ])->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $this->withHeaders(tokenHeaders($officeA))
        ->patchJson('/api/v1/office/bookings/'.$bookingB->id.'/status', [
            'status' => 'rejected',
        ])->assertForbidden();
});

test('office bookings summary returns total and seats sums excluding rejected and non-demanded bookings', function () {
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flightA = Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-10-01',
        'departure_time' => '2026-10-01 09:00:00',
        'price' => 300,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);

    $flightB = Flight::create([
        'from' => 'Dubai',
        'to' => 'Cairo',
        'travel_date' => '2026-10-02',
        'departure_time' => '2026-10-02 10:00:00',
        'price' => 350,
        'seats' => 20,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    Booking::create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 600,
        'status' => 'pending',
        'demanded' => true,
    ]);

    Booking::create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'confirmed',
        'demanded' => true,
    ]);

    Booking::create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 4,
        'total' => 1200,
        'status' => 'rejected',
        'demanded' => true,
    ]);

    Booking::create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 3,
        'total' => 900,
        'status' => 'pending',
        'demanded' => false,
    ]);

    Booking::create([
        'flight_id' => $flightB->id,
        'office_id' => $officeB->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 5,
        'total' => 1750,
        'status' => 'pending',
        'demanded' => true,
    ]);

    $response = $this->withHeaders(tokenHeaders($officeA))
        ->getJson('/api/v1/office/bookings/summary');

    $response->assertOk()
        ->assertJsonPath('message', 'Office bookings summary retrieved successfully')
        ->assertJsonPath('data.total_sum', 1500)
        ->assertJsonPath('data.seats_sum', 3);
});

test('office flight passengers endpoint returns only confirmed booking seats for own flight', function () {
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flightA = Flight::create([
        'from' => 'Khartoum',
        'to' => 'Madani',
        'travel_date' => '2026-10-10',
        'departure_time' => '2026-10-10 09:30:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);

    $flightB = Flight::create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-10-11',
        'departure_time' => '2026-10-11 11:00:00',
        'price' => 120,
        'seats' => 20,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    $confirmedBooking = Booking::create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 200,
        'status' => 'confirmed',
        'demanded' => true,
    ]);

    $pendingBooking = Booking::create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 100,
        'status' => 'pending',
        'demanded' => true,
    ]);

    $otherOfficeBooking = Booking::create([
        'flight_id' => $flightB->id,
        'office_id' => $officeB->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 120,
        'status' => 'confirmed',
        'demanded' => true,
    ]);

    Seat::create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flightA->id,
        'booking_id' => $confirmedBooking->id,
        'traveler_name' => 'Amro Hatim',
    ]);
    Seat::create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flightA->id,
        'booking_id' => $confirmedBooking->id,
        'traveler_name' => 'John Doe',
    ]);
    Seat::create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flightA->id,
        'booking_id' => $pendingBooking->id,
        'traveler_name' => 'Pending Passenger',
    ]);
    Seat::create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flightB->id,
        'booking_id' => $otherOfficeBooking->id,
        'traveler_name' => 'Other Office Passenger',
    ]);

    $response = $this->withHeaders(tokenHeaders($officeA))
        ->getJson('/api/v1/office/flights/'.$flightA->id.'/passengers');

    $response->assertOk()
        ->assertJsonPath('message', 'Flight passengers retrieved successfully')
        ->assertJsonPath('data.flight.id', $flightA->id);

    expect($response->json('data.passengers'))->toHaveCount(2);
    expect($response->json('data.passengers.0.traveler_name'))->toBe('Amro Hatim');
    expect($response->json('data.passengers.1.traveler_name'))->toBe('John Doe');

    $this->withHeaders(tokenHeaders($officeA))
        ->getJson('/api/v1/office/flights/'.$flightB->id.'/passengers')
        ->assertForbidden();
});
