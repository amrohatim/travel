<?php

use App\Models\Booking;
use App\Models\Flight;
use App\Models\OfficeLocation;
use App\Models\ParentCompany;
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
        'phone' => '0999999991',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Registered successfully')
        ->assertJsonPath('data.user.phone', '0999999991')
        ->assertJsonPath('data.user.role', 'traveler');

    expect($response->json('data.token'))->not->toBeEmpty();
});

test('it logs in and logs out with sanctum token', function () {
    $user = User::factory()->create([
        'phone' => '0999999992',
        'password' => Hash::make('password123'),
        'role' => 'traveler',
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'phone' => $user->phone,
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

test('registration requires a valid 10 digit phone', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Traveler Invalid',
        'phone' => '09999',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('registration rejects duplicate phone', function () {
    User::factory()->create([
        'phone' => '0999999994',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Traveler Duplicate',
        'phone' => '0999999994',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('office and admin can login using phone', function () {
    $office = User::factory()->create([
        'role' => 'office',
        'phone' => '0999999995',
        'password' => Hash::make('password123'),
    ]);
    $admin = User::factory()->create([
        'role' => 'admin',
        'phone' => '0999999996',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $office->phone,
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'office');

    $this->postJson('/api/v1/auth/login', [
        'phone' => $admin->phone,
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'admin');
});

test('it requires authentication for protected endpoints', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

test('it lists offices with bankak fields', function () {
    $zetaCompany = ParentCompany::create([
        'name' => 'Zeta Group',
        'image' => 'companies/zeta.png',
    ]);
    $alphaCompany = ParentCompany::create([
        'name' => 'Alpha Group',
        'image' => 'companies/alpha.png',
    ]);

    User::factory()->create([
        'name' => 'Office Z',
        'role' => 'office',
        'bankak_name' => 'Bankak A',
        'bankak_number' => 123456,
        'parent_company_id' => $zetaCompany->id,
    ]);
    $officeA = User::factory()->create([
        'name' => 'Office A',
        'role' => 'office',
        'bankak_name' => 'Bankak B',
        'bankak_number' => 654321,
        'parent_company_id' => $alphaCompany->id,
    ]);
    OfficeLocation::create([
        'office_id' => $officeA->id,
        'lat' => 15.5000000,
        'lng' => 32.5000000,
    ]);
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'phone' => '0999999999',
    ]);

    $response = $this->withHeaders(tokenHeaders($traveler))
        ->getJson('/api/v1/offices');

    $response->assertOk()
        ->assertJsonPath('message', 'Offices retrieved successfully')
        ->assertJsonPath('data.0.name', 'Office A')
        ->assertJsonPath('data.0.bankak_name', 'Bankak B')
        ->assertJsonPath('data.0.bankak_number', 654321)
        ->assertJsonPath('data.0.parent_company_id', $alphaCompany->id)
        ->assertJsonPath('data.0.parent_company_name', 'Alpha Group')
        ->assertJsonPath('data.0.parent_company_image', url('companies/alpha.png'))
        ->assertJsonPath('data.0.location.lat', 15.5)
        ->assertJsonPath('data.0.location.lng', 32.5)
        ->assertJsonPath('data.1.name', 'Office Z')
        ->assertJsonPath('data.1.location', null)
        ->assertJsonPath('data.1.parent_company_name', 'Zeta Group');
});

test('office can view and update own profile', function () {
    Storage::fake('public');

    $office = User::factory()->create([
        'role' => 'office',
        'name' => 'Office Profile',
        'phone' => '0911111111',
        'bankak_name' => 'Old Bankak',
        'bankak_number' => 222222,
    ]);
    OfficeLocation::create([
        'office_id' => $office->id,
        'lat' => 12.3456789,
        'lng' => 45.6789123,
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->getJson('/api/v1/office/profile')
        ->assertOk()
        ->assertJsonPath('message', 'Office profile retrieved successfully')
        ->assertJsonPath('data.name', 'Office Profile')
        ->assertJsonPath('data.phone', '0911111111')
        ->assertJsonPath('data.bankak_name', 'Old Bankak')
        ->assertJsonPath('data.bankak_number', 222222)
        ->assertJsonPath('data.location.lat', 12.3456789)
        ->assertJsonPath('data.location.lng', 45.6789123);

    $response = $this->withHeaders(tokenHeaders($office))
        ->post('/api/v1/office/profile', [
            'name' => 'Office Updated',
            'phone' => '0953857300',
            'bankak_name' => 'New Bankak',
            'bankak_number' => 444444,
            'image' => UploadedFile::fake()->image('office.jpg'),
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Office profile updated successfully')
        ->assertJsonPath('data.name', 'Office Updated')
        ->assertJsonPath('data.phone', '0953857300')
        ->assertJsonPath('data.bankak_name', 'New Bankak')
        ->assertJsonPath('data.bankak_number', 444444);

    $this->assertDatabaseHas('users', [
        'id' => $office->id,
        'name' => 'Office Updated',
        'phone' => '0953857300',
        'bankak_name' => 'New Bankak',
        'bankak_number' => 444444,
    ]);
});

test('office can change password and login with the new password', function () {
    $office = User::factory()->create([
        'role' => 'office',
        'phone' => '0911111112',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->withHeaders(tokenHeaders($office))
        ->postJson('/api/v1/office/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Office password updated successfully');

    expect(Hash::check('newpassword123', $office->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/login', [
        'phone' => $office->phone,
        'password' => 'password123',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $office->phone,
        'password' => 'newpassword123',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'office');
});

test('office password change rejects wrong current password', function () {
    $office = User::factory()->create([
        'role' => 'office',
        'password' => Hash::make('password123'),
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->postJson('/api/v1/office/password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('office password change requires matching confirmation', function () {
    $office = User::factory()->create([
        'role' => 'office',
        'password' => Hash::make('password123'),
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->postJson('/api/v1/office/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword123',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('traveler cannot access office password change endpoint', function () {
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'password' => Hash::make('password123'),
    ]);

    $this->withHeaders(tokenHeaders($traveler))
        ->postJson('/api/v1/office/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertForbidden();
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

test('office can create future weekly flights and receives created dates', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Future']);

    $response = $this->withHeaders(tokenHeaders($office))
        ->postJson('/api/v1/office/flights/future', [
            'from' => 'Dubai',
            'to' => 'Riyadh',
            'departure_time' => '2026-06-01 15:45:00',
            'price' => 300,
            'seats' => 30,
            'days_ahead' => 30,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Future flights processed successfully')
        ->assertJsonPath('data.created_count', 4)
        ->assertJsonPath('data.skipped_count', 0)
        ->assertJsonPath('data.created_dates.0', '2026-06-08')
        ->assertJsonPath('data.created_dates.3', '2026-06-29');

    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-08',
        'departure_time' => '2026-06-08 15:45:00',
    ]);
});

test('future flight creation skips duplicates and still creates remaining weekly flights', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Future']);

    Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-15',
        'departure_time' => '2026-06-15 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $response = $this->withHeaders(tokenHeaders($office))
        ->postJson('/api/v1/office/flights/future', [
            'from' => 'Dubai',
            'to' => 'Riyadh',
            'departure_time' => '2026-06-01 15:45:00',
            'price' => 300,
            'seats' => 30,
            'days_ahead' => 30,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.created_count', 3)
        ->assertJsonPath('data.skipped_count', 1)
        ->assertJsonPath('data.skipped_dates.0', '2026-06-15');

    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'travel_date' => '2026-06-08',
        'departure_time' => '2026-06-08 15:45:00',
    ]);
    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'travel_date' => '2026-06-22',
        'departure_time' => '2026-06-22 15:45:00',
    ]);
});

test('traveler cannot access office flight creation endpoint', function () {
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'phone' => '0999999999',
    ]);

    $this->withHeaders(tokenHeaders($traveler))->postJson('/api/v1/office/flights', [
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
    ])->assertForbidden();
});

test('traveler cannot access future office flight creation endpoint', function () {
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'phone' => '0999999998',
    ]);

    $this->withHeaders(tokenHeaders($traveler))->postJson('/api/v1/office/flights/future', [
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
        'days_ahead' => 30,
    ])->assertForbidden();
});

test('traveler can create booking and seats are decremented', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office C']);
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'phone' => '0999999999',
    ]);

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

test('pending to rejected restores booked seats to the flight', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Restore']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Jeddah',
        'travel_date' => '2026-09-03',
        'departure_time' => '2026-09-03 08:00:00',
        'price' => 200,
        'seats' => 52,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 3,
        'total' => 600,
        'status' => 'pending',
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'rejected',
        ])->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($flight->fresh()->seats)->toBe(55);
});

test('confirmed to rejected restores booked seats to the flight', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Restore']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Cairo',
        'travel_date' => '2026-09-04',
        'departure_time' => '2026-09-04 09:00:00',
        'price' => 250,
        'seats' => 10,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 500,
        'status' => 'confirmed',
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'rejected',
        ])->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($flight->fresh()->seats)->toBe(12);
});

test('rejected to pending re-consumes seats from the flight', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Restore']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Doha',
        'travel_date' => '2026-09-05',
        'departure_time' => '2026-09-05 10:00:00',
        'price' => 180,
        'seats' => 9,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 3,
        'total' => 540,
        'status' => 'rejected',
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'pending',
        ])->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect($flight->fresh()->seats)->toBe(6);
});

test('rejected to confirmed re-consumes seats from the flight', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Restore']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-09-06',
        'departure_time' => '2026-09-06 11:00:00',
        'price' => 220,
        'seats' => 7,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 440,
        'status' => 'rejected',
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'confirmed',
        ])->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    expect($flight->fresh()->seats)->toBe(5);
});

test('rejected to active status returns validation error when seats are unavailable', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Restore']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Muscat',
        'travel_date' => '2026-09-07',
        'departure_time' => '2026-09-07 12:00:00',
        'price' => 190,
        'seats' => 1,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 380,
        'status' => 'rejected',
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'pending',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonPath('errors.status.0', 'Not enough seats available to reactivate this booking.');

    expect($flight->fresh()->seats)->toBe(1);
    expect($booking->fresh()->status)->toBe('rejected');
});

test('repeated rejected status does not change seats again', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Restore']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Bahrain',
        'travel_date' => '2026-09-08',
        'departure_time' => '2026-09-08 13:00:00',
        'price' => 210,
        'seats' => 6,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 420,
        'status' => 'rejected',
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'rejected',
        ])->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($flight->fresh()->seats)->toBe(6);
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
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'phone' => '0999999999',
    ]);

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
    expect($response->json('data.passengers.0.traveler_phone'))->toBe($traveler->phone);
    expect($response->json('data.passengers.0.booking_serial_number'))->toBe($confirmedBooking->serial_number);

    $this->withHeaders(tokenHeaders($officeA))
        ->getJson('/api/v1/office/flights/'.$flightB->id.'/passengers')
        ->assertForbidden();
});
