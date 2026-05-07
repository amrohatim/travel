<?php

use App\Models\Booking;
use App\Models\Flight;
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

    $this->actingAs($admin)->get('/admin/flights')->assertSeeText('Flights');
    $this->actingAs($admin)->get('/admin/bookings')->assertSeeText('Bookings');
    $this->actingAs($admin)->get('/admin/states')->assertSeeText('States');
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
