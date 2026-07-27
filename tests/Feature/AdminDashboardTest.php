<?php

use App\Models\Booking;
use App\Models\Device;
use App\Models\Flight;
use App\Models\HomeMessage;
use App\Models\OfficeLocation;
use App\Models\ParentCompany;
use App\Models\Seat;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('allows admin to access admin pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/flights')->assertOk();
    $this->actingAs($admin)->get('/admin/flights/future/create')->assertOk();
    $this->actingAs($admin)->get('/admin/fees')->assertOk();
    $this->actingAs($admin)->get('/admin/bookings')->assertOk();
    $this->actingAs($admin)->get('/admin/states')->assertOk();
    $this->actingAs($admin)->get('/admin/parent-companies')->assertOk();
    $this->actingAs($admin)->get('/admin/home-messages')->assertOk();
});

it('blocks non admin users from admin pages', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);

    $this->actingAs($traveler)->get('/admin/users')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/flights')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/flights/future/create')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/fees')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/bookings')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/states')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/parent-companies')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/home-messages')->assertForbidden();
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
    $this->actingAs($traveler)->post('/admin/fees/'.$office->id.'/clear')->assertForbidden();
    $this->actingAs($traveler)->delete('/admin/bookings/'.$booking->id)->assertForbidden();
    $this->actingAs($traveler)->post('/admin/bookings/bulk-delete', ['ids' => [$booking->id]])->assertForbidden();
});

it('blocks non admin users from admin seats endpoints', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);
    $office = User::factory()->create(['role' => 'office']);

    $flight = Flight::query()->create([
        'from' => 'U',
        'to' => 'V',
        'travel_date' => now()->toDateString(),
        'departure_time' => '11:00',
        'price' => 220,
        'seats' => 15,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 220,
        'status' => 'pending',
    ]);

    $this->actingAs($traveler)->get('/admin/flights/'.$flight->id.'/seats')->assertForbidden();
    $this->actingAs($traveler)->get('/admin/bookings/'.$booking->id.'/seats')->assertForbidden();
});

it('admin can create a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Created Group']);
    $state = State::create(['name' => 'Khartoum']);

    $response = $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Created User',
        'email' => 'created@example.com',
        'phone' => '1234567',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'state_id' => $state->id,
        'lat' => '15.1234567',
        'lng' => '32.7654321',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('users', [
        'name' => 'Created User',
        'email' => 'created@example.com',
        'phone' => '1234567',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'state_id' => $state->id,
    ]);
    $createdUser = User::query()->where('email', 'created@example.com')->firstOrFail();
    $this->assertDatabaseHas('office_locations', [
        'office_id' => $createdUser->id,
        'lat' => '15.1234567',
        'lng' => '32.7654321',
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

it('admin create office validation requires a parent company', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
        'name' => 'Office Missing Company',
        'email' => 'office-missing@example.com',
        'phone' => '3333',
        'role' => 'office',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/admin/users/create');
    $response->assertSessionHasErrors(['parent_company_id']);
});

it('admin can create a non-office user without a parent company', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Traveler Without Company',
        'email' => 'traveler@example.com',
        'phone' => '1234000',
        'role' => 'traveler',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('users', [
        'email' => 'traveler@example.com',
        'role' => 'traveler',
        'parent_company_id' => null,
    ]);
});

it('admin can update a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Updated Group']);
    $state = State::create(['name' => 'Gezira']);
    $user = User::factory()->create(['role' => 'traveler', 'phone' => '4444']);

    $response = $this->actingAs($admin)->put('/admin/users/'.$user->id, [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone' => '5555',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'state_id' => $state->id,
        'lat' => '14.1000000',
        'lng' => '33.2000000',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone' => '5555',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'state_id' => $state->id,
    ]);
    $this->assertDatabaseHas('office_locations', [
        'office_id' => $user->id,
        'lat' => '14.1000000',
        'lng' => '33.2000000',
    ]);
});

it('admin create office validation requires both location coordinates together', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Location Group']);

    $response = $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
        'name' => 'Office Partial Location',
        'email' => 'partial-location@example.com',
        'phone' => '1234569',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'lat' => '15.5000000',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/admin/users/create');
    $response->assertSessionHasErrors(['lat', 'lng']);
});

it('admin update office validation requires both location coordinates together', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Location Update Group']);
    $user = User::factory()->create([
        'role' => 'office',
        'phone' => '4446',
        'parent_company_id' => $parentCompany->id,
    ]);

    $response = $this->actingAs($admin)
        ->from('/admin/users/'.$user->id.'/edit')
        ->put('/admin/users/'.$user->id, [
            'name' => 'Updated Name',
            'email' => 'updated-location@example.com',
            'phone' => '5557',
            'role' => 'office',
            'parent_company_id' => $parentCompany->id,
            'lng' => '32.1000000',
        ]);

    $response->assertRedirect('/admin/users/'.$user->id.'/edit');
    $response->assertSessionHasErrors(['lat', 'lng']);
});

it('admin can create an office without location coordinates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'No Location Group']);

    $response = $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Office Without Location',
        'email' => 'office-without-location@example.com',
        'phone' => '1234570',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/admin/users');

    $office = User::query()->where('email', 'office-without-location@example.com')->firstOrFail();
    $this->assertDatabaseMissing('office_locations', [
        'office_id' => $office->id,
    ]);
});

it('admin can update an existing office location', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Existing Location Group']);
    $user = User::factory()->create([
        'role' => 'office',
        'phone' => '4447',
        'parent_company_id' => $parentCompany->id,
    ]);
    OfficeLocation::create([
        'office_id' => $user->id,
        'lat' => 10.0000000,
        'lng' => 20.0000000,
    ]);

    $response = $this->actingAs($admin)->put('/admin/users/'.$user->id, [
        'name' => 'Updated Name',
        'email' => 'updated-existing-location@example.com',
        'phone' => '5558',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'lat' => '11.1111111',
        'lng' => '22.2222222',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('office_locations', [
        'office_id' => $user->id,
        'lat' => '11.1111111',
        'lng' => '22.2222222',
    ]);
});

it('admin changing an office to traveler removes office location', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Role Change Group']);
    $user = User::factory()->create([
        'role' => 'office',
        'phone' => '4546',
        'parent_company_id' => $parentCompany->id,
    ]);
    OfficeLocation::create([
        'office_id' => $user->id,
        'lat' => 12.0000000,
        'lng' => 24.0000000,
    ]);

    $response = $this->actingAs($admin)->put('/admin/users/'.$user->id, [
        'name' => 'Traveler Now',
        'email' => 'traveler-now-2@example.com',
        'phone' => '5455',
        'role' => 'traveler',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseMissing('office_locations', [
        'office_id' => $user->id,
    ]);
});

it('admin update office validation requires a parent company', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'office', 'phone' => '4445']);

    $response = $this->actingAs($admin)
        ->from('/admin/users/'.$user->id.'/edit')
        ->put('/admin/users/'.$user->id, [
            'name' => 'Updated Name',
            'email' => 'updated-missing@example.com',
            'phone' => '5556',
            'role' => 'office',
        ]);

    $response->assertRedirect('/admin/users/'.$user->id.'/edit');
    $response->assertSessionHasErrors(['parent_company_id']);
});

it('admin can remove office company by changing role to traveler', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Role Switch Group']);
    $user = User::factory()->create([
        'role' => 'office',
        'phone' => '4545',
        'parent_company_id' => $parentCompany->id,
    ]);

    $response = $this->actingAs($admin)->put('/admin/users/'.$user->id, [
        'name' => 'Traveler Now',
        'email' => 'traveler-now@example.com',
        'phone' => '5454',
        'role' => 'traveler',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'traveler',
        'parent_company_id' => null,
        'state_id' => null,
    ]);
});

it('admin can create an office without a state because state is nullable', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Nullable State Group']);

    $response = $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Office Without State',
        'email' => 'office-without-state@example.com',
        'phone' => '1234568',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/admin/users');

    $this->assertDatabaseHas('users', [
        'email' => 'office-without-state@example.com',
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
        'state_id' => null,
    ]);
});

it('admin can delete another user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'traveler']);

    $response = $this->actingAs($admin)->delete('/admin/users/'.$user->id);

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('admin can suspend a user and all known devices', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'traveler']);
    Device::create([
        'user_id' => $user->id,
        'device_id' => 'device-admin-suspend-1',
        'device_model' => 'Pixel',
        'platform' => 'android',
    ]);
    $user->createToken('test-token')->plainTextToken;

    $response = $this->actingAs($admin)->post('/admin/users/'.$user->id.'/suspend', [
        'reason' => 'Abuse detected',
    ]);

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'is_suspended' => true,
        'suspension_reason' => 'Abuse detected',
    ]);
    $this->assertDatabaseHas('devices', [
        'device_id' => 'device-admin-suspend-1',
        'is_suspended' => true,
        'suspension_reason' => 'Abuse detected',
    ]);
    expect($user->tokens()->count())->toBe(0);
});

it('admin can unsuspend a user and linked devices', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create([
        'role' => 'traveler',
        'is_suspended' => true,
        'suspension_reason' => 'Cleanup',
        'suspended_at' => now(),
    ]);
    Device::create([
        'user_id' => $user->id,
        'device_id' => 'device-admin-unsuspend-1',
        'is_suspended' => true,
        'suspension_reason' => 'Cleanup',
        'suspended_at' => now(),
    ]);

    $response = $this->actingAs($admin)->post('/admin/users/'.$user->id.'/unsuspend');

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'is_suspended' => false,
        'suspension_reason' => null,
    ]);
    $this->assertDatabaseHas('devices', [
        'device_id' => 'device-admin-unsuspend-1',
        'is_suspended' => false,
        'suspension_reason' => null,
    ]);
});

it('non admin cannot suspend or unsuspend users', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);
    $target = User::factory()->create(['role' => 'traveler']);

    $this->actingAs($traveler)
        ->post('/admin/users/'.$target->id.'/suspend', ['reason' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($traveler)
        ->post('/admin/users/'.$target->id.'/unsuspend')
        ->assertForbidden();
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

    $this->actingAs($admin)->get('/admin/flights')->assertSeeText('Flights')->assertSeeText('Delete Selected')->assertSeeText('View Seats');
    $this->actingAs($admin)->get('/admin/flights/future/create')->assertSeeText('Add Future Flights')->assertSeeText('Generate Future Flights');
    $this->actingAs($admin)->get('/admin/fees')->assertSeeText('Fees')->assertSeeText('Clear Fees');
    $this->actingAs($admin)->get('/admin/bookings')->assertSeeText('Bookings')->assertSeeText('Delete Selected')->assertSeeText('View Seats');
    $this->actingAs($admin)->get('/admin/states')->assertSeeText('States');
    $this->actingAs($admin)->get('/admin/parent-companies')->assertSeeText('Parent Companies');
    $this->actingAs($admin)->get('/admin/home-messages')->assertSeeText('Home Messages')->assertSeeText('Add Home Message')->assertSeeText('Home Message List');
});

it('fees page lists all offices and aggregates only payable bookings', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $traveler = User::factory()->create(['role' => 'traveler']);
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);
    $officeZero = User::factory()->create(['role' => 'office', 'name' => 'Office Zero']);

    $flightA = Flight::query()->create([
        'from' => 'A',
        'to' => 'B',
        'travel_date' => now()->toDateString(),
        'departure_time' => '08:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);
    $flightB = Flight::query()->create([
        'from' => 'C',
        'to' => 'D',
        'travel_date' => now()->toDateString(),
        'departure_time' => '10:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);
    $flightZero = Flight::query()->create([
        'from' => 'E',
        'to' => 'F',
        'travel_date' => now()->toDateString(),
        'departure_time' => '12:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeZero->id,
        'office_name' => $officeZero->name,
    ]);

    Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 200,
        'status' => 'pending',
        'demanded' => true,
    ]);
    Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 100,
        'status' => 'confirmed',
        'demanded' => true,
    ]);
    Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 4,
        'total' => 400,
        'status' => 'rejected',
        'demanded' => true,
    ]);
    Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 3,
        'total' => 300,
        'status' => 'pending',
        'demanded' => false,
    ]);
    Booking::query()->create([
        'flight_id' => $flightB->id,
        'office_id' => $officeB->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 5,
        'total' => 500,
        'status' => 'pending',
        'demanded' => true,
    ]);
    Booking::query()->create([
        'flight_id' => $flightZero->id,
        'office_id' => $officeZero->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 200,
        'status' => 'rejected',
        'demanded' => true,
    ]);
    Booking::query()->create([
        'flight_id' => $flightZero->id,
        'office_id' => $officeZero->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 100,
        'status' => 'pending',
        'demanded' => false,
    ]);

    $response = $this->actingAs($admin)->get('/admin/fees');

    $response->assertOk()
        ->assertSeeText('Fees')
        ->assertSeeText('Office A')
        ->assertSeeText('Office B')
        ->assertSeeText('Office Zero')
        ->assertSeeText('2')
        ->assertSeeText('3')
        ->assertSeeText('15000 SDG')
        ->assertSeeText('1')
        ->assertSeeText('5')
        ->assertSeeText('25000 SDG')
        ->assertSeeText('0')
        ->assertSeeText('Office A')
        ->assertSee('/admin/fees/'.$officeA->id.'/clear', false);
});

it('admin can clear fees for one office without affecting other bookings', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $traveler = User::factory()->create(['role' => 'traveler']);
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);

    $flightA = Flight::query()->create([
        'from' => 'A',
        'to' => 'B',
        'travel_date' => now()->toDateString(),
        'departure_time' => '08:30',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);
    $flightB = Flight::query()->create([
        'from' => 'C',
        'to' => 'D',
        'travel_date' => now()->toDateString(),
        'departure_time' => '09:30',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    $officeAPayableOne = Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 200,
        'status' => 'pending',
        'demanded' => true,
    ]);
    $officeAPayableTwo = Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 100,
        'status' => 'confirmed',
        'demanded' => true,
    ]);
    $officeARejected = Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 4,
        'total' => 400,
        'status' => 'rejected',
        'demanded' => true,
    ]);
    $officeAAlreadyCleared = Booking::query()->create([
        'flight_id' => $flightA->id,
        'office_id' => $officeA->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 3,
        'total' => 300,
        'status' => 'pending',
        'demanded' => false,
    ]);
    $officeBPayable = Booking::query()->create([
        'flight_id' => $flightB->id,
        'office_id' => $officeB->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 5,
        'total' => 500,
        'status' => 'pending',
        'demanded' => true,
    ]);

    $response = $this->actingAs($admin)->post('/admin/fees/'.$officeA->id.'/clear');

    $response->assertRedirect('/admin/fees');
    $response->assertSessionHas('success', 'Cleared fees for Office A. 2 booking(s) updated.');

    $this->assertDatabaseHas('bookings', [
        'id' => $officeAPayableOne->id,
        'demanded' => false,
    ]);
    $this->assertDatabaseHas('bookings', [
        'id' => $officeAPayableTwo->id,
        'demanded' => false,
    ]);
    $this->assertDatabaseHas('bookings', [
        'id' => $officeARejected->id,
        'demanded' => true,
    ]);
    $this->assertDatabaseHas('bookings', [
        'id' => $officeAAlreadyCleared->id,
        'demanded' => false,
    ]);
    $this->assertDatabaseHas('bookings', [
        'id' => $officeBPayable->id,
        'demanded' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/fees')
        ->assertOk()
        ->assertSeeText('Office A')
        ->assertSeeText('Office B')
        ->assertSeeText('0 SDG')
        ->assertSeeText('25000 SDG');
});

it('admin can view flight seats details with traveler data', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Traveler A', 'phone' => '7777']);

    $flight = Flight::query()->create([
        'from' => 'J',
        'to' => 'K',
        'travel_date' => now()->toDateString(),
        'departure_time' => '07:00',
        'price' => 80,
        'seats' => 10,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 80,
        'status' => 'pending',
    ]);

    Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $booking->id,
        'traveler_name' => 'Traveler A',
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights/'.$flight->id.'/seats')
        ->assertOk()
        ->assertSeeText('Flight Seats')
        ->assertSeeText('Traveler A')
        ->assertSeeText('7777')
        ->assertSeeText($booking->serial_number);
});

it('admin can view booking seats details with traveler data', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Traveler B', 'phone' => '8888']);

    $flight = Flight::query()->create([
        'from' => 'L',
        'to' => 'M',
        'travel_date' => now()->toDateString(),
        'departure_time' => '13:00',
        'price' => 90,
        'seats' => 10,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 90,
        'status' => 'pending',
    ]);

    Seat::query()->create([
        'traveler_id' => $traveler->id,
        'flight_id' => $flight->id,
        'booking_id' => $booking->id,
        'traveler_name' => 'Traveler B',
    ]);

    $this->actingAs($admin)
        ->get('/admin/bookings/'.$booking->id.'/seats')
        ->assertOk()
        ->assertSeeText('Booking Seats')
        ->assertSeeText('Traveler B')
        ->assertSeeText('8888')
        ->assertSeeText($booking->serial_number);
});

it('seat details pages show empty state when no seats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::query()->create([
        'from' => 'N',
        'to' => 'O',
        'travel_date' => now()->toDateString(),
        'departure_time' => '19:00',
        'price' => 55,
        'seats' => 5,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = Booking::query()->create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 55,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights/'.$flight->id.'/seats')
        ->assertOk()
        ->assertSeeText('No seats found for this flight.');

    $this->actingAs($admin)
        ->get('/admin/bookings/'.$booking->id.'/seats')
        ->assertOk()
        ->assertSeeText('No seats found for this booking.');
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

it('admin can load flights page without filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights')
        ->assertOk()
        ->assertSeeText('Khartoum')
        ->assertSeeText('Port Sudan')
        ->assertSeeText('Office A');
});

it('admin flights page orders closest upcoming travel dates first', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-10',
        'departure_time' => '2026-08-10 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-07-28',
        'departure_time' => '2026-07-28 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-07-20',
        'departure_time' => '2026-07-20 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $response = $this->actingAs($admin)->get('/admin/flights');

    $response->assertOk();

    $content = $response->getContent();
    expect(strpos($content, '2026-07-28'))->toBeLessThan(strpos($content, '2026-08-10'));
    expect(strpos($content, '2026-08-10'))->toBeLessThan(strpos($content, '2026-07-20'));
});

it('admin can filter flights by from state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);
    State::query()->create(['name' => 'Madani']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);
    Flight::query()->create([
        'from' => 'Madani',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-06',
        'departure_time' => '2026-08-06 09:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights?from=Khartoum')
        ->assertOk()
        ->assertSeeText('Khartoum')
        ->assertDontSeeText('Madani');
});

it('admin can filter flights by to state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);
    State::query()->create(['name' => 'Madani']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);
    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Madani',
        'travel_date' => '2026-08-06',
        'departure_time' => '2026-08-06 09:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights?to=Port%20Sudan')
        ->assertOk()
        ->assertSeeText('Port Sudan')
        ->assertDontSeeText('Madani');
});

it('admin can filter flights by travel date', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);
    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-06',
        'departure_time' => '2026-08-06 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights?travel_date=2026-08-05')
        ->assertOk()
        ->assertSeeText('2026-08-05')
        ->assertDontSeeText('2026-08-06');
});

it('admin can filter flights by office', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);
    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-06',
        'departure_time' => '2026-08-06 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights?office_id='.$officeA->id)
        ->assertOk()
        ->assertSeeText('Office A')
        ->assertDontSeeText('Office B');
});

it('admin can combine flight filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);
    State::query()->create(['name' => 'Madani']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);
    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Madani',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);
    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-05',
        'departure_time' => '2026-08-05 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights?from=Khartoum&to=Port%20Sudan&travel_date=2026-08-05&office_id='.$officeA->id)
        ->assertOk()
        ->assertSeeText('Office A')
        ->assertDontSeeText('Office B')
        ->assertDontSeeText('Madani');
});

it('non admin users are forbidden from filtered admin flights page', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);

    $this->actingAs($traveler)
        ->get('/admin/flights?from=Khartoum&travel_date=2026-08-05')
        ->assertForbidden();
});

it('flight pagination keeps active filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office A']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office B']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);

    foreach (range(1, 21) as $day) {
        Flight::query()->create([
            'from' => 'Khartoum',
            'to' => 'Port Sudan',
            'travel_date' => sprintf('2026-08-%02d', $day),
            'departure_time' => sprintf('2026-08-%02d 08:00:00', $day),
            'price' => 100,
            'seats' => 20,
            'office_id' => $officeA->id,
            'office_name' => $officeA->name,
        ]);
    }

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-22',
        'departure_time' => '2026-08-22 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights?from=Khartoum&to=Port%20Sudan&office_id='.$officeA->id)
        ->assertOk()
        ->assertSee('/admin/flights?page=2&amp;from=Khartoum&amp;to=Port%20Sudan&amp;office_id='.$officeA->id, false);
});

it('admin can create future flights for a selected office from the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Future Admin']);

    $response = $this->actingAs($admin)->post('/admin/flights/future', [
        'office_id' => $office->id,
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'departure_time' => '2026-08-03 15:45:00',
        'price' => 300,
        'seats' => 30,
        'days_ahead' => 30,
    ]);

    $response->assertRedirect('/admin/flights/future/create');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'office_name' => 'Office Future Admin',
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-08-10',
        'departure_time' => '2026-08-10 15:45:00',
    ]);
    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'travel_date' => '2026-08-31',
        'departure_time' => '2026-08-31 15:45:00',
    ]);
});

it('admin future flight creation skips duplicates for the selected office', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Future Admin']);
    $otherOffice = User::factory()->create(['role' => 'office', 'name' => 'Office Other']);

    Flight::query()->create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-08-17',
        'departure_time' => '2026-08-17 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Flight::query()->create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-08-17',
        'departure_time' => '2026-08-17 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $otherOffice->id,
        'office_name' => $otherOffice->name,
    ]);

    $response = $this->actingAs($admin)->post('/admin/flights/future', [
        'office_id' => $office->id,
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'departure_time' => '2026-08-03 15:45:00',
        'price' => 300,
        'seats' => 30,
        'days_ahead' => 30,
    ]);

    $response->assertRedirect('/admin/flights/future/create');
    expect(session('success'))->toContain('Created: 3');
    expect(session('success'))->toContain('Skipped: 1');
    expect(session('success'))->toContain('2026-08-17');

    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'travel_date' => '2026-08-10',
    ]);
    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'travel_date' => '2026-08-24',
    ]);
});

it('admin future flight creation rejects non office targets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $response = $this->actingAs($admin)
        ->from('/admin/flights/future/create')
        ->post('/admin/flights/future', [
            'office_id' => $traveler->id,
            'from' => 'Dubai',
            'to' => 'Riyadh',
            'departure_time' => '2026-08-03 15:45:00',
            'price' => 300,
            'seats' => 30,
            'days_ahead' => 30,
        ]);

    $response->assertRedirect('/admin/flights/future/create');
    $response->assertSessionHasErrors(['office_id']);
});

it('non admin users cannot submit admin future flight creation', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);
    $office = User::factory()->create(['role' => 'office']);

    $this->actingAs($traveler)->post('/admin/flights/future', [
        'office_id' => $office->id,
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'departure_time' => '2026-08-03 15:45:00',
        'price' => 300,
        'seats' => 30,
        'days_ahead' => 30,
    ])->assertForbidden();
});

it('admin future flights page shows selected office flights under the form', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officeA = User::factory()->create(['role' => 'office', 'name' => 'Office Alpha']);
    $officeB = User::factory()->create(['role' => 'office', 'name' => 'Office Beta']);
    State::query()->create(['name' => 'Khartoum']);
    State::query()->create(['name' => 'Port Sudan']);
    State::query()->create(['name' => 'Madani']);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Port Sudan',
        'travel_date' => '2026-08-10',
        'departure_time' => '2026-08-10 08:00:00',
        'price' => 100,
        'seats' => 20,
        'office_id' => $officeA->id,
        'office_name' => $officeA->name,
    ]);

    Flight::query()->create([
        'from' => 'Khartoum',
        'to' => 'Madani',
        'travel_date' => '2026-08-11',
        'departure_time' => '2026-08-11 09:00:00',
        'price' => 110,
        'seats' => 15,
        'office_id' => $officeB->id,
        'office_name' => $officeB->name,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights/future/create?office_id='.$officeA->id)
        ->assertOk()
        ->assertSeeText('Office Alpha Flights')
        ->assertSeeText('Port Sudan')
        ->assertDontSeeText('Office Beta Flights')
        ->assertDontSeeText('Madani');
});

it('admin future flights page shows empty state for selected office without flights', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Empty']);
    State::query()->create(['name' => 'Khartoum']);

    $this->actingAs($admin)
        ->get('/admin/flights/future/create?office_id='.$office->id)
        ->assertOk()
        ->assertSeeText('Office Empty Flights')
        ->assertSeeText('No flights found for this office.');
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

it('parent company create and image update still work', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);

    $createResponse = $this->actingAs($admin)->post('/admin/parent-companies', [
        'name' => 'Safriat Group',
        'image' => UploadedFile::fake()->image('company.jpg'),
    ]);

    $createResponse->assertRedirect('/admin/parent-companies');
    $parentCompany = ParentCompany::query()->where('name', 'Safriat Group')->firstOrFail();

    $updateResponse = $this->actingAs($admin)->post('/admin/parent-companies/'.$parentCompany->id.'/image', [
        'image' => UploadedFile::fake()->image('company-2.jpg'),
    ]);

    $updateResponse->assertRedirect('/admin/parent-companies');

    expect($parentCompany->fresh()->image)->not->toBeNull();
});

it('admin can update a parent company name', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Old Company Name']);

    $response = $this->actingAs($admin)->put('/admin/parent-companies/'.$parentCompany->id, [
        'name' => 'New Company Name',
    ]);

    $response->assertRedirect('/admin/parent-companies');

    $this->assertDatabaseHas('parent_companies', [
        'id' => $parentCompany->id,
        'name' => 'New Company Name',
    ]);
});

it('admin can delete an unused parent company', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Unused Company']);

    $response = $this->actingAs($admin)->delete('/admin/parent-companies/'.$parentCompany->id);

    $response->assertRedirect('/admin/parent-companies');

    $this->assertDatabaseMissing('parent_companies', [
        'id' => $parentCompany->id,
    ]);
});

it('admin cannot delete a parent company that still has office users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'Assigned Company']);
    User::factory()->create([
        'role' => 'office',
        'parent_company_id' => $parentCompany->id,
    ]);

    $response = $this->actingAs($admin)->delete('/admin/parent-companies/'.$parentCompany->id);

    $response->assertRedirect('/admin/parent-companies');
    $response->assertSessionHas('error');

    $this->assertDatabaseHas('parent_companies', [
        'id' => $parentCompany->id,
    ]);
});

it('parent company admin page renders qr preview and download controls', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'QR Group']);

    $this->actingAs($admin)
        ->get('/admin/parent-companies')
        ->assertOk()
        ->assertSeeText('Save Name')
        ->assertSeeText('Delete')
        ->assertSee('/admin/parent-companies/'.$parentCompany->id.'/qr', false)
        ->assertSee('/admin/parent-companies/'.$parentCompany->id.'/qr/download', false);
});

it('admin can create a home message with image', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/home-messages', [
        'title' => 'Welcome Home',
        'description' => 'First launch content for travelers.',
        'image' => UploadedFile::fake()->image('welcome.jpg'),
    ]);

    $response->assertRedirect('/admin/home-messages');

    $message = HomeMessage::query()->where('title', 'Welcome Home')->firstOrFail();
    expect($message->image)->not->toBeNull();
    $this->assertDatabaseHas('home_messages', [
        'id' => $message->id,
        'title' => 'Welcome Home',
        'description' => 'First launch content for travelers.',
    ]);
});

it('admin can create a home message without image', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/home-messages', [
        'title' => 'No Image Message',
        'description' => 'Text only message.',
    ]);

    $response->assertRedirect('/admin/home-messages');
    $this->assertDatabaseHas('home_messages', [
        'title' => 'No Image Message',
        'description' => 'Text only message.',
        'image' => null,
    ]);
});

it('admin can update a home message and replace image', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $homeMessage = HomeMessage::create([
        'title' => 'Original Title',
        'description' => 'Original description',
        'image' => UploadedFile::fake()->image('original.jpg')->store('home-messages', 'public'),
    ]);

    $oldImage = $homeMessage->image;

    $response = $this->actingAs($admin)->put('/admin/home-messages/'.$homeMessage->id, [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'image' => UploadedFile::fake()->image('updated.jpg'),
    ]);

    $response->assertRedirect('/admin/home-messages');

    $homeMessage->refresh();
    $this->assertDatabaseHas('home_messages', [
        'id' => $homeMessage->id,
        'title' => 'Updated Title',
        'description' => 'Updated description',
    ]);
    expect($homeMessage->image)->not->toBe($oldImage);
    Storage::disk('public')->assertMissing($oldImage);
});

it('admin can delete a home message and its stored image', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $imagePath = UploadedFile::fake()->image('delete-me.jpg')->store('home-messages', 'public');
    $homeMessage = HomeMessage::create([
        'title' => 'Delete Me',
        'description' => 'Delete this message',
        'image' => $imagePath,
    ]);

    $response = $this->actingAs($admin)->delete('/admin/home-messages/'.$homeMessage->id);

    $response->assertRedirect('/admin/home-messages');
    $this->assertDatabaseMissing('home_messages', ['id' => $homeMessage->id]);
    Storage::disk('public')->assertMissing($imagePath);
});

it('admin can preview and download a parent company qr code png', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $parentCompany = ParentCompany::create(['name' => 'QR Download Group']);

    $previewResponse = $this->actingAs($admin)
        ->get('/admin/parent-companies/'.$parentCompany->id.'/qr');

    $previewResponse->assertOk();
    expect($previewResponse->headers->get('content-type'))->toContain('image/png');

    $downloadResponse = $this->actingAs($admin)
        ->get('/admin/parent-companies/'.$parentCompany->id.'/qr/download');

    $downloadResponse->assertOk();
    expect($downloadResponse->headers->get('content-type'))->toContain('image/png');
    expect($downloadResponse->headers->get('content-disposition'))->toContain('attachment;');
});

it('public company landing page renders company details and open app call to action', function () {
    $parentCompany = ParentCompany::create(['name' => 'Landing Group']);

    config()->set(
        'deep_links.android_store_url',
        'https://play.google.com/store/apps/details?id=com.safriat.safriat'
    );

    $this->get('/companies/'.$parentCompany->id)
        ->assertOk()
        ->assertSeeText('Landing Group')
        ->assertSeeText('Open In App')
        ->assertSeeText('Get The App')
        ->assertSee($parentCompany->appDeepLinkUrl(), false)
        ->assertSee(config('deep_links.android_store_url'), false)
        ->assertSeeText('Other devices will stay on this page');
});

it('public company landing page returns 404 for invalid company id', function () {
    $this->get('/companies/999999')->assertNotFound();
});
