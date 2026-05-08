<?php

use App\Models\Booking;
use App\Models\Flight;
use App\Models\Seat;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('allows admin to access admin pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/flights')->assertOk();
    $this->actingAs($admin)->get('/admin/bookings')->assertOk();
    $this->actingAs($admin)->get('/admin/states')->assertOk();
});

it('blocks non admin users from admin pages', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);

    $this->actingAs($traveler)->get('/admin/users')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/flights')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/bookings')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/states')->assertForbidden();
});

it('blocks non admin users from admin delete endpoints', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);
    $office = User::factory()->create(['role' => 'office']);

    $flight = Flight::query()->create([
        'from' => 'X',
        'to' => 'Y',
        'travel_date' => now()->toDateString(),
        'departure_time' => '10:00',
        'price' => 120,
        'seats' => 12,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 120,
        'status' => 'pending',
    ]);

    $this->actingAs($traveler)->delete('/admin/flights/'.$flight->id)->assertForbidden();
    $this->actingAs($traveler)->post('/admin/flights/bulk-delete', ['ids' => [$flight->id]])->assertForbidden();
    $this->actingAs($traveler)->delete('/admin/bookings/'.$booking->id)->assertForbidden();
    $this->actingAs($traveler)->post('/admin/bookings/bulk-delete', ['ids' => [$booking->id]])->assertForbidden();
});

it('admin can create a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Created User',
        'email' => 'created@example.com',
        'phone' => '1234567',
        'role' => 'office',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('users', [
        'name' => 'Created User',
        'email' => 'created@example.com',
        'phone' => '1234567',
        'role' => 'office',
    ]);
});

it('admin create user validation catches invalid payload', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['email' => 'dupe@example.com', 'phone' => '999', 'role' => 'traveler']);

    $response = $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
        'name' => '',
        'email' => 'dupe@example.com',
        'phone' => '999',
        'role' => 'invalid-role',
        'password' => 'short',
        'password_confirmation' => 'no-match',
    ]);

    $response->assertRedirect('/admin/users/create');
    $response->assertSessionHasErrors(['name', 'email', 'phone', 'role', 'password']);
});

it('admin can update a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'traveler', 'phone' => '4444']);

    $response = $this->actingAs($admin)->put('/admin/users/'.$user->id, [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone' => '5555',
        'role' => 'office',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone' => '5555',
        'role' => 'office',
    ]);
});

it('admin can delete another user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'traveler']);

    $response = $this->actingAs($admin)->delete('/admin/users/'.$user->id);

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('admin pages render expected headings', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::query()->create([
        'from' => 'A',
        'to' => 'B',
        'travel_date' => now()->toDateString(),
        'departure_time' => '08:30',
        'price' => 100,
        'seats' => 10,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 100,
        'status' => 'pending',
    ]);

    State::query()->create(['name' => 'Test State']);

    $this->actingAs($admin)->get('/admin/flights')->assertSeeText('Flights')->assertSeeText('Delete Selected');
    $this->actingAs($admin)->get('/admin/bookings')->assertSeeText('Bookings')->assertSeeText('Delete Selected');
    $this->actingAs($admin)->get('/admin/states')->assertSeeText('States');
});

it('admin can delete a booking and its seats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::query()->create([
        'from' => 'A',
        'to' => 'B',
        'travel_date' => now()->toDateString(),
        'departure_time' => '08:30',
        'price' => 100,
        'seats' => 10,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 200,
        'status' => 'pending',
    ]);

    $seat = Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $booking->id,
        'traveler_name' => $traveler->name,
    ]);

    $this->actingAs($admin)->delete('/admin/bookings/'.$booking->id)->assertRedirect('/admin/bookings');

    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
});

it('admin can bulk delete bookings and their seats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::query()->create([
        'from' => 'C',
        'to' => 'D',
        'travel_date' => now()->toDateString(),
        'departure_time' => '09:00',
        'price' => 90,
        'seats' => 7,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $bookingOne = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 90,
        'status' => 'pending',
    ]);

    $bookingTwo = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 180,
        'status' => 'pending',
    ]);

    Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $bookingOne->id,
        'traveler_name' => $traveler->name,
    ]);
    Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $bookingTwo->id,
        'traveler_name' => $traveler->name,
    ]);

    $this->actingAs($admin)->post('/admin/bookings/bulk-delete', [
        'ids' => [$bookingOne->id, $bookingTwo->id],
    ])->assertRedirect('/admin/bookings');

    $this->assertDatabaseMissing('bookings', ['id' => $bookingOne->id]);
    $this->assertDatabaseMissing('bookings', ['id' => $bookingTwo->id]);
    $this->assertDatabaseMissing('seats', ['booking_id' => $bookingOne->id]);
    $this->assertDatabaseMissing('seats', ['booking_id' => $bookingTwo->id]);
});

it('admin can delete a flight with related bookings and seats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::query()->create([
        'from' => 'M',
        'to' => 'N',
        'travel_date' => now()->toDateString(),
        'departure_time' => '14:00',
        'price' => 150,
        'seats' => 9,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 300,
        'status' => 'pending',
    ]);

    $seat = Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $booking->id,
        'traveler_name' => $traveler->name,
    ]);

    $this->actingAs($admin)->delete('/admin/flights/'.$flight->id)->assertRedirect('/admin/flights');

    $this->assertDatabaseMissing('flights', ['id' => $flight->id]);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
});

it('admin can bulk delete flights with related bookings and seats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flightOne = Flight::query()->create([
        'from' => 'Q',
        'to' => 'R',
        'travel_date' => now()->toDateString(),
        'departure_time' => '16:00',
        'price' => 60,
        'seats' => 4,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);
    $flightTwo = Flight::query()->create([
        'from' => 'S',
        'to' => 'T',
        'travel_date' => now()->toDateString(),
        'departure_time' => '18:00',
        'price' => 70,
        'seats' => 6,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $bookingOne = Booking::query()->create([
        'flight_id' => $flightOne->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 60,
        'status' => 'pending',
    ]);
    $bookingTwo = Booking::query()->create([
        'flight_id' => $flightTwo->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 70,
        'status' => 'pending',
    ]);

    Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flightOne->id,
        'booking_id' => $bookingOne->id,
        'traveler_name' => $traveler->name,
    ]);
    Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flightTwo->id,
        'booking_id' => $bookingTwo->id,
        'traveler_name' => $traveler->name,
    ]);

    $this->actingAs($admin)->post('/admin/flights/bulk-delete', [
        'ids' => [$flightOne->id, $flightTwo->id],
    ])->assertRedirect('/admin/flights');

    $this->assertDatabaseMissing('flights', ['id' => $flightOne->id]);
    $this->assertDatabaseMissing('flights', ['id' => $flightTwo->id]);
    $this->assertDatabaseMissing('bookings', ['id' => $bookingOne->id]);
    $this->assertDatabaseMissing('bookings', ['id' => $bookingTwo->id]);
    $this->assertDatabaseMissing('seats', ['booking_id' => $bookingOne->id]);
    $this->assertDatabaseMissing('seats', ['booking_id' => $bookingTwo->id]);
});

it('bulk delete endpoints validate ids', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from('/admin/flights')
        ->post('/admin/flights/bulk-delete', ['ids' => []])
        ->assertRedirect('/admin/flights')
        ->assertSessionHasErrors(['ids']);

    $this->actingAs($admin)
        ->from('/admin/bookings')
        ->post('/admin/bookings/bulk-delete', ['ids' => []])
        ->assertRedirect('/admin/bookings')
        ->assertSessionHasErrors(['ids']);
});

it('state create and image update still work', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);

    $createResponse = $this->actingAs($admin)->post('/admin/states', [
        'name' => 'Khartoum',
        'image' => UploadedFile::fake()->image('state.jpg'),
    ]);

    $createResponse->assertRedirect('/admin/states');
    $state = State::query()->where('name', 'Khartoum')->firstOrFail();

    $updateResponse = $this->actingAs($admin)->post('/admin/states/'.$state->id.'/image', [
        'image' => UploadedFile::fake()->image('state2.jpg'),
    ]);

    $updateResponse->assertRedirect('/admin/states');

    expect($state->fresh()->image)->not->toBeNull();
});
