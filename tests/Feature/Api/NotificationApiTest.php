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
        ->postJson('/api/v1/office/notifications/token', [
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
        ->deleteJson('/api/v1/office/notifications/token', [
            'token' => 'fcm-token-a',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Notification token removed successfully');

    $this->assertDatabaseMissing('user_device_tokens', [
        'user_id' => $office->id,
        'fcm_token' => 'fcm-token-a',
    ]);
});

test('traveler cannot access office notification token endpoints', function () {
    $traveler = User::factory()->create(['role' => 'traveler']);

    $this->withHeaders(notificationAuthHeaders($traveler))
        ->postJson('/api/v1/office/notifications/token', [
            'token' => 'fcm-token-a',
            'platform' => 'android',
        ])
        ->assertForbidden();
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
