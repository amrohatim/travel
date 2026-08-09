<?php

use App\Models\Booking;
use App\Models\Flight;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function discountTokenHeaders(User $user): array
{
    $token = $user->createToken('discount-test-token')->plainTextToken;

    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'X-Device-ID' => 'discount-device-'.$user->id,
    ];
}

it('admin can create a single discounted flight from the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Discount']);

    State::query()->create(['name' => 'Dubai']);
    State::query()->create(['name' => 'Cairo']);

    $response = $this->actingAs($admin)->post('/admin/flights', [
        'office_id' => $office->id,
        'from' => 'Dubai',
        'to' => 'Cairo',
        'departure_time' => '2026-08-20 09:00:00',
        'price' => 500,
        'seats' => 12,
        'has_discount' => '1',
        'discount_percentage' => 10,
    ]);

    $response->assertRedirect('/admin/flights');

    $this->assertDatabaseHas('flights', [
        'office_id' => $office->id,
        'office_name' => $office->name,
        'from' => 'Dubai',
        'to' => 'Cairo',
        'travel_date' => '2026-08-20',
        'price' => 500,
        'has_discount' => true,
        'discount_percentage' => 10,
        'discount_value' => 50,
    ]);
});

it('admin can update discount on an existing flight from the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Discount']);

    $flight = Flight::query()->create([
        'from' => 'Dubai',
        'to' => 'Amman',
        'travel_date' => '2026-08-22',
        'departure_time' => '2026-08-22 08:00:00',
        'price' => 400,
        'seats' => 10,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $response = $this->actingAs($admin)->put('/admin/flights/'.$flight->id, [
        'office_id' => $office->id,
        'from' => 'Dubai',
        'to' => 'Amman',
        'departure_time' => '2026-08-22 08:00:00',
        'price' => 400,
        'seats' => 10,
        'has_discount' => '1',
        'discount_percentage' => 15,
    ]);

    $response->assertRedirect('/admin/flights');

    $this->assertDatabaseHas('flights', [
        'id' => $flight->id,
        'has_discount' => true,
        'discount_percentage' => 15,
        'discount_value' => 60,
    ]);
});

it('admin single flight validation rejects discounts that consume the full price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $office = User::factory()->create(['role' => 'office']);

    $response = $this->actingAs($admin)
        ->from('/admin/flights/create')
        ->post('/admin/flights', [
            'office_id' => $office->id,
            'from' => 'Dubai',
            'to' => 'Amman',
            'departure_time' => '2026-08-22 08:00:00',
            'price' => 400,
            'seats' => 10,
            'has_discount' => '1',
            'discount_percentage' => 100,
        ]);

    $response->assertRedirect('/admin/flights/create');
    $response->assertSessionHasErrors(['discount_percentage']);
});

it('traveler booking uses the discounted final price and stays fixed after later flight edits', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Discount']);
    $traveler = User::factory()->create(['role' => 'traveler']);
    $admin = User::factory()->create(['role' => 'admin']);

    $flight = Flight::query()->create([
        'from' => 'Dubai',
        'to' => 'Jeddah',
        'travel_date' => '2026-08-21',
        'departure_time' => '2026-08-21 09:00:00',
        'price' => 500,
        'seats' => 8,
        'office_id' => $office->id,
        'office_name' => $office->name,
        'has_discount' => true,
        'discount_percentage' => 10,
        'discount_value' => 50,
    ]);

    $bookingResponse = $this->withHeaders(discountTokenHeaders($traveler))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 2,
            'passengers' => ['One', 'Two'],
            'image' => UploadedFile::fake()->image('ticket.jpg'),
        ]);

    $bookingResponse->assertCreated();

    $booking = Booking::query()->latest('id')->firstOrFail();
    expect($booking->total)->toBe(900);

    $updateResponse = $this->actingAs($admin)->put('/admin/flights/'.$flight->id, [
        'office_id' => $office->id,
        'from' => 'Dubai',
        'to' => 'Jeddah',
        'departure_time' => '2026-08-21 09:00:00',
        'price' => 500,
        'seats' => 8,
        'has_discount' => '1',
        'discount_percentage' => 20,
    ]);

    $updateResponse->assertRedirect('/admin/flights');
    expect($booking->fresh()->total)->toBe(900);
});

it('flight api payloads expose discount metadata and final price', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Discount']);

    $flight = Flight::query()->create([
        'from' => 'Dubai',
        'to' => 'Cairo',
        'travel_date' => '2026-08-24',
        'departure_time' => '2026-08-24 11:30:00',
        'price' => 500,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
        'has_discount' => true,
        'discount_percentage' => 10,
        'discount_value' => 50,
    ]);

    $this->getJson('/api/v1/flights?date=2026-08-24')
        ->assertOk()
        ->assertJsonPath('data.0.id', $flight->id)
        ->assertJsonPath('data.0.price', 500)
        ->assertJsonPath('data.0.has_discount', true)
        ->assertJsonPath('data.0.discount_percentage', 10)
        ->assertJsonPath('data.0.discount_value', 50)
        ->assertJsonPath('data.0.final_price', 450);
});

it('office api flight creation ignores discount fields', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Discount']);

    $response = $this->withHeaders(discountTokenHeaders($office))
        ->postJson('/api/v1/office/flights', [
            'from' => 'Dubai',
            'to' => 'Cairo',
            'departure_time' => '2026-08-25 15:45:00',
            'price' => 500,
            'seats' => 20,
            'has_discount' => true,
            'discount_percentage' => 10,
            'discount_value' => 50,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.has_discount', false)
        ->assertJsonPath('data.discount_percentage', null)
        ->assertJsonPath('data.discount_value', null)
        ->assertJsonPath('data.final_price', 500);
});

it('admin flights list and traveler flights page show discount details', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $traveler = User::factory()->create(['role' => 'traveler']);
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Discount']);
    State::query()->create(['name' => 'Dubai']);
    State::query()->create(['name' => 'Cairo']);

    Flight::query()->create([
        'from' => 'Dubai',
        'to' => 'Cairo',
        'travel_date' => '2026-08-26',
        'departure_time' => '2026-08-26 10:00:00',
        'price' => 500,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
        'has_discount' => true,
        'discount_percentage' => 10,
        'discount_value' => 50,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flights')
        ->assertOk()
        ->assertSeeText('Final Price')
        ->assertSeeText('10% (50)')
        ->assertSeeText('Edit');

    $this->actingAs($traveler)
        ->get('/flights?date=2026-08-26')
        ->assertOk()
        ->assertSeeText('10%')
        ->assertSeeText('450');
});
