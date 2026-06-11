<?php

use App\Models\Flight;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function notificationAuthHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

function configureFirebaseForTests(): void
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $privateKey);

    config()->set('services.firebase.project_id', 'test-project');
    config()->set('services.firebase.client_email', 'firebase@test-project.iam.gserviceaccount.com');
    config()->set('services.firebase.private_key', $privateKey);
}

test('office can register and remove notification token', function () {
    $office = User::factory()->create(['role' => 'office']);

    $this->withHeaders(notificationAuthHeaders($office))
        ->postJson('/api/v1/notifications/token', [
            'token' => 'fcm-token-a',
            'platform' => 'android',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Notification token saved successfully');

    $this->assertDatabaseHas('user_device_tokens', [
        'user_id' => $office->id,
        'fcm_token' => 'fcm-token-a',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($office))
        ->deleteJson('/api/v1/notifications/token', [
            'token' => 'fcm-token-a',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Notification token removed successfully');

    $this->assertDatabaseMissing('user_device_tokens', [
        'user_id' => $office->id,
        'fcm_token' => 'fcm-token-a',
    ]);
});

test('traveler can register and remove notification token', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);

    $this->withHeaders(notificationAuthHeaders($traveler))
        ->postJson('/api/v1/notifications/token', [
            'token' => 'fcm-token-a',
            'platform' => 'android',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Notification token saved successfully');

    $this->assertDatabaseHas('user_device_tokens', [
        'user_id' => $traveler->id,
        'fcm_token' => 'fcm-token-a',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($traveler))
        ->deleteJson('/api/v1/notifications/token', [
            'token' => 'fcm-token-a',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Notification token removed successfully');
});

test('booking creation sends fcm notification to office devices', function () {
    configureFirebaseForTests();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token'], 200),
        'https://fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response(['name' => 'projects/test/messages/1'], 200),
    ]);

    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Push']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Kuwait',
        'travel_date' => '2026-12-10',
        'departure_time' => '2026-12-10 10:00:00',
        'price' => 300,
        'seats' => 4,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    UserDeviceToken::create([
        'user_id' => $office->id,
        'fcm_token' => 'valid-device-token',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($traveler))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 1,
            'passengers' => ['Traveler One'],
            'image' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertCreated();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/messages:send');
    });
});

test('invalid fcm token is removed after booking notification send failure', function () {
    configureFirebaseForTests();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token'], 200),
        'https://fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response([
            'error' => [
                'status' => 'NOT_FOUND',
                'details' => [
                    ['errorCode' => 'UNREGISTERED'],
                ],
            ],
        ], 404),
    ]);

    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Push']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Kuwait',
        'travel_date' => '2026-12-10',
        'departure_time' => '2026-12-10 10:00:00',
        'price' => 300,
        'seats' => 4,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    UserDeviceToken::create([
        'user_id' => $office->id,
        'fcm_token' => 'invalid-device-token',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($traveler))
        ->post('/api/v1/flights/'.$flight->id.'/bookings', [
            'seats_booked' => 1,
            'passengers' => ['Traveler One'],
            'image' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertCreated();

    $this->assertDatabaseMissing('user_device_tokens', [
        'fcm_token' => 'invalid-device-token',
    ]);
});

test('booking confirmation sends fcm notification to traveler devices', function () {
    configureFirebaseForTests();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token'], 200),
        'https://fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response(['name' => 'projects/test/messages/1'], 200),
    ]);

    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Push']);
    $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Traveler Push']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Kuwait',
        'travel_date' => '2026-12-10',
        'departure_time' => '2026-12-10 10:00:00',
        'price' => 300,
        'seats' => 4,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = \App\Models\Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'pending',
    ]);

    UserDeviceToken::create([
        'user_id' => $traveler->id,
        'fcm_token' => 'traveler-device-token',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/messages:send')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'message.token') === 'traveler-device-token'
            && data_get($data, 'message.data.type') === 'booking_confirmed'
            && data_get($data, 'message.notification.image') === url('assets/confirm-ticket.png');
    });
});

test('booking reconfirmation does not send duplicate traveler notification', function () {
    configureFirebaseForTests();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token'], 200),
        'https://fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response(['name' => 'projects/test/messages/1'], 200),
    ]);

    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Push']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Kuwait',
        'travel_date' => '2026-12-10',
        'departure_time' => '2026-12-10 10:00:00',
        'price' => 300,
        'seats' => 4,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = \App\Models\Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'confirmed',
    ]);

    UserDeviceToken::create([
        'user_id' => $traveler->id,
        'fcm_token' => 'traveler-device-token',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'confirmed',
        ])
        ->assertOk();

    Http::assertNothingSent();
});

test('non-confirmed booking status changes do not send traveler notification', function () {
    configureFirebaseForTests();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token'], 200),
        'https://fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response(['name' => 'projects/test/messages/1'], 200),
    ]);

    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Push']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Kuwait',
        'travel_date' => '2026-12-10',
        'departure_time' => '2026-12-10 10:00:00',
        'price' => 300,
        'seats' => 4,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = \App\Models\Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'pending',
    ]);

    UserDeviceToken::create([
        'user_id' => $traveler->id,
        'fcm_token' => 'traveler-device-token',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'rejected',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    Http::assertNothingSent();
});

test('rejected to confirmed sends traveler confirmation notification once', function () {
    configureFirebaseForTests();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token'], 200),
        'https://fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response(['name' => 'projects/test/messages/1'], 200),
    ]);

    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Push']);
    $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Traveler Push']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Kuwait',
        'travel_date' => '2026-12-11',
        'departure_time' => '2026-12-11 10:00:00',
        'price' => 300,
        'seats' => 3,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = \App\Models\Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'rejected',
    ]);

    UserDeviceToken::create([
        'user_id' => $traveler->id,
        'fcm_token' => 'traveler-device-token',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    Http::assertSentCount(2);
    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/messages:send')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'message.token') === 'traveler-device-token'
            && data_get($data, 'message.data.type') === 'booking_confirmed';
    });
});

test('invalid traveler fcm token is removed after booking confirmation notification failure', function () {
    configureFirebaseForTests();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token'], 200),
        'https://fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response([
            'error' => [
                'status' => 'NOT_FOUND',
                'details' => [
                    ['errorCode' => 'UNREGISTERED'],
                ],
            ],
        ], 404),
    ]);

    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Push']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Kuwait',
        'travel_date' => '2026-12-10',
        'departure_time' => '2026-12-10 10:00:00',
        'price' => 300,
        'seats' => 4,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $booking = \App\Models\Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'pending',
    ]);

    UserDeviceToken::create([
        'user_id' => $traveler->id,
        'fcm_token' => 'invalid-traveler-device-token',
        'platform' => 'android',
    ]);

    $this->withHeaders(notificationAuthHeaders($office))
        ->patchJson('/api/v1/office/bookings/'.$booking->id.'/status', [
            'status' => 'confirmed',
        ])
        ->assertOk();

    $this->assertDatabaseMissing('user_device_tokens', [
        'fcm_token' => 'invalid-traveler-device-token',
    ]);
});
