<?php

use App\Models\Booking;
use App\Models\Device;
use App\Models\Flight;
use App\Models\HomeMessage;
use App\Models\OfficeLocation;
use App\Models\ParentCompany;
use App\Models\Seat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

function tokenHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'X-Device-ID' => 'test-device-'.$user->id,
    ];
}

test('it registers traveler and returns token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Traveler One',
        'phone' => '0999999991',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_id' => 'device-register-1',
        'device_model' => 'Pixel Test',
        'platform' => 'android',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Registered successfully')
        ->assertJsonPath('data.user.phone', '0999999991')
        ->assertJsonPath('data.user.role', 'traveler');

    expect($response->json('data.token'))->not->toBeEmpty();
    $this->assertDatabaseHas('devices', [
        'device_id' => 'device-register-1',
        'platform' => 'android',
        'device_model' => 'Pixel Test',
    ]);
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
        'device_id' => 'device-login-1',
        'device_model' => 'iPhone Test',
        'platform' => 'ios',
    ]);

    $login->assertOk()
        ->assertJsonPath('message', 'Logged in successfully');

    $token = $login->json('data.token');

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
        'X-Device-ID' => 'device-login-1',
    ])->postJson('/api/v1/auth/logout')->assertOk()
        ->assertJsonPath('message', 'Logged out successfully');
});

test('registration requires a valid 10 digit phone', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Traveler Invalid',
        'phone' => '09999',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_id' => 'device-invalid-1',
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
        'device_id' => 'device-duplicate-1',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('registration requires device id', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Traveler Missing Device',
        'phone' => '0999999993',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.device_id.0', 'The device id field is required.');
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
        'device_id' => 'device-office-login',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'office');

    $this->postJson('/api/v1/auth/login', [
        'phone' => $admin->phone,
        'password' => 'password123',
        'device_id' => 'device-admin-login',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'admin');
});

test('support can login using phone', function () {
    $support = User::factory()->create([
        'role' => 'support',
        'phone' => '0999999997',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $support->phone,
        'password' => 'password123',
        'device_id' => 'device-support-login',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'support');
});

test('traveler can change password and login with the new password', function () {
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'phone' => '0911111113',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->withHeaders(tokenHeaders($traveler))
        ->postJson('/api/v1/traveler/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Traveler password updated successfully');

    expect(Hash::check('newpassword123', $traveler->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/login', [
        'phone' => $traveler->phone,
        'password' => 'password123',
        'device_id' => 'device-old-traveler-password',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $traveler->phone,
        'password' => 'newpassword123',
        'device_id' => 'device-new-traveler-password',
    ])->assertOk()
        ->assertJsonPath('data.user.role', 'traveler');
});

test('traveler password change rejects wrong current password', function () {
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'password' => Hash::make('password123'),
    ]);

    $this->withHeaders(tokenHeaders($traveler))
        ->postJson('/api/v1/traveler/password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('traveler password change requires matching confirmation', function () {
    $traveler = User::factory()->create([
        'role' => 'traveler',
        'password' => Hash::make('password123'),
    ]);

    $this->withHeaders(tokenHeaders($traveler))
        ->postJson('/api/v1/traveler/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword123',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
});

test('office cannot access traveler password change endpoint', function () {
    $office = User::factory()->create([
        'role' => 'office',
        'password' => Hash::make('password123'),
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->postJson('/api/v1/traveler/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertForbidden();
});

test('traveler password change requires authentication', function () {
    $this->postJson('/api/v1/traveler/password', [
        'current_password' => 'password123',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertUnauthorized();
});

test('login requires device id', function () {
    $user = User::factory()->create([
        'phone' => '0999999900',
        'password' => Hash::make('password123'),
        'role' => 'traveler',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $user->phone,
        'password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonPath('errors.device_id.0', 'The device id field is required.');
});

test('login rejects suspended user', function () {
    $user = User::factory()->create([
        'phone' => '0999999910',
        'password' => Hash::make('password123'),
        'role' => 'traveler',
        'is_suspended' => true,
        'suspension_reason' => 'Terms violation',
        'suspended_at' => now(),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $user->phone,
        'password' => 'password123',
        'device_id' => 'device-suspended-user',
    ])->assertStatus(403)
        ->assertJsonPath('message', 'Account suspended')
        ->assertJsonPath('reason', 'Terms violation');
});

test('login rejects suspended device', function () {
    Device::create([
        'device_id' => 'device-suspended-1',
        'is_suspended' => true,
        'suspension_reason' => 'Repeated abuse',
        'suspended_at' => now(),
    ]);

    $user = User::factory()->create([
        'phone' => '0999999911',
        'password' => Hash::make('password123'),
        'role' => 'traveler',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => $user->phone,
        'password' => 'password123',
        'device_id' => 'device-suspended-1',
    ])->assertStatus(403)
        ->assertJsonPath('message', 'Device suspended')
        ->assertJsonPath('reason', 'Repeated abuse');
});

test('authenticated endpoint rejects suspended user', function () {
    $user = User::factory()->create([
        'role' => 'traveler',
        'is_suspended' => true,
        'suspension_reason' => 'Fraud review',
        'suspended_at' => now(),
    ]);

    $this->withHeaders(tokenHeaders($user))
        ->getJson('/api/v1/offices')
        ->assertStatus(403)
        ->assertJsonPath('message', 'Account suspended')
        ->assertJsonPath('reason', 'Fraud review');
});

test('authenticated endpoint rejects suspended device', function () {
    $user = User::factory()->create(['role' => 'traveler']);
    Device::create([
        'user_id' => $user->id,
        'device_id' => 'test-device-'.$user->id,
        'is_suspended' => true,
        'suspension_reason' => 'Blocked device',
        'suspended_at' => now(),
    ]);

    $this->withHeaders(tokenHeaders($user))
        ->getJson('/api/v1/offices')
        ->assertStatus(403)
        ->assertJsonPath('message', 'Device suspended')
        ->assertJsonPath('reason', 'Blocked device');
});

test('it requires authentication for protected endpoints', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

test('app version endpoint returns configured version with no-cache headers', function () {
    config(['app.flutter_app_version' => '2.5.0']);

    $response = $this->getJson('/api/v1/getAppVersion');

    $response->assertOk()
        ->assertJsonPath('version', '2.5.0')
        ->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Expires', '0');
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

test('support sees only assigned offices', function () {
    $officeA = User::factory()->create(['name' => 'Office One', 'role' => 'office']);
    $officeB = User::factory()->create(['name' => 'Office Two', 'role' => 'office']);
    $support = User::factory()->create(['role' => 'support']);
    $support->assignedOffices()->sync([$officeB->id]);

    $response = $this->withHeaders(tokenHeaders($support))
        ->getJson('/api/v1/offices');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($officeB->id);
});

test('it lists home messages publicly in latest-first order', function () {
    HomeMessage::create([
        'title' => 'Older Message',
        'description' => 'Shown second',
        'image' => 'home-messages/older.jpg',
    ]);
    $latest = HomeMessage::create([
        'title' => 'Latest Message',
        'description' => 'Shown first',
        'image' => 'https://cdn.example.com/latest.jpg',
    ]);

    $response = $this->getJson('/api/v1/home-messages');

    $response->assertOk()
        ->assertJsonPath('message', 'Home messages retrieved successfully')
        ->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonPath('data.0.title', 'Latest Message')
        ->assertJsonPath('data.0.description', 'Shown first')
        ->assertJsonPath('data.0.image', 'https://cdn.example.com/latest.jpg')
        ->assertJsonPath('data.1.title', 'Older Message')
        ->assertJsonPath('data.1.image', url('storage/home-messages/older.jpg'));
});

test('home messages api returns null image when not provided', function () {
    HomeMessage::create([
        'title' => 'No Image',
        'description' => 'Image is optional',
        'image' => null,
    ]);

    $this->getJson('/api/v1/home-messages')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'No Image')
        ->assertJsonPath('data.0.image', null);
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

test('support can view assigned office profile and is forbidden without office header', function () {
    $office = User::factory()->create([
        'role' => 'office',
        'name' => 'Office Profile',
        'phone' => '0911111114',
    ]);
    $support = User::factory()->create(['role' => 'support']);
    $support->assignedOffices()->sync([$office->id]);

    $this->withHeaders(array_merge(tokenHeaders($support), [
        'X-Office-ID' => (string) $office->id,
    ]))
        ->getJson('/api/v1/office/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $office->id)
        ->assertJsonPath('data.name', 'Office Profile');

    $this->withHeaders(tokenHeaders($support))
        ->getJson('/api/v1/office/profile')
        ->assertForbidden();
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

test('it lists only traveler flights from today through 7 days ahead by default', function () {
    Carbon::setTestNow('2026-08-16 09:00:00');

    try {
        $office = User::factory()->create(['role' => 'office', 'name' => 'Office Window']);

        Flight::create([
            'from' => 'Khartoum',
            'to' => 'Madani',
            'travel_date' => '2026-08-15',
            'departure_time' => '2026-08-15 08:00:00',
            'price' => 200,
            'seats' => 20,
            'office_id' => $office->id,
            'office_name' => $office->name,
        ]);

        Flight::create([
            'from' => 'Khartoum',
            'to' => 'Port Sudan',
            'travel_date' => '2026-08-16',
            'departure_time' => '2026-08-16 09:00:00',
            'price' => 250,
            'seats' => 20,
            'office_id' => $office->id,
            'office_name' => $office->name,
        ]);

        Flight::create([
            'from' => 'Khartoum',
            'to' => 'Atbara',
            'travel_date' => '2026-08-23',
            'departure_time' => '2026-08-23 10:00:00',
            'price' => 300,
            'seats' => 20,
            'office_id' => $office->id,
            'office_name' => $office->name,
        ]);

        Flight::create([
            'from' => 'Khartoum',
            'to' => 'Dongola',
            'travel_date' => '2026-08-24',
            'departure_time' => '2026-08-24 11:00:00',
            'price' => 350,
            'seats' => 20,
            'office_id' => $office->id,
            'office_name' => $office->name,
        ]);

        $response = $this->getJson('/api/v1/flights');

        $response->assertOk()
            ->assertJsonPath('message', 'Flights retrieved successfully');

        expect($response->json('data'))->toHaveCount(2);
        expect(array_column($response->json('data'), 'travel_date'))
            ->toBe(['2026-08-16', '2026-08-23']);
    } finally {
        Carbon::setTestNow();
    }
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

test('office can update only the price for a flight that already has bookings', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Price Update']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-01',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 600,
        'status' => 'pending',
        'demanded' => true,
    ]);

    $response = $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/flights/'.$flight->id, [
            'from' => 'Dubai',
            'to' => 'Riyadh',
            'departure_time' => '2026-06-01 15:45:00',
            'price' => 450,
            'seats' => 30,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Flight updated successfully')
        ->assertJsonPath('data.price', 450)
        ->assertJsonPath('data.from', 'Dubai')
        ->assertJsonPath('data.to', 'Riyadh')
        ->assertJsonPath('data.departure_time', '2026-06-01 15:45:00');

    $this->assertDatabaseHas('flights', [
        'id' => $flight->id,
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-01',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 450,
        'seats' => 30,
    ]);
});

test('office cannot change seats for a flight that already has bookings', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Seat Lock']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-01',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'pending',
        'demanded' => true,
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/flights/'.$flight->id, [
            'from' => 'Dubai',
            'to' => 'Riyadh',
            'departure_time' => '2026-06-01 15:45:00',
            'price' => 450,
            'seats' => 31,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only the price can be edited when bookings exist.');
});

test('office cannot change departure time for a flight that already has bookings', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Time Lock']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-01',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'pending',
        'demanded' => true,
    ]);

    $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/flights/'.$flight->id, [
            'from' => 'Dubai',
            'to' => 'Riyadh',
            'departure_time' => '2026-06-01 16:00:00',
            'price' => 450,
            'seats' => 30,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only the price can be edited when bookings exist.');
});

test('office can fully update a flight that has no bookings', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Full Update']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-01',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    $response = $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/flights/'.$flight->id, [
            'from' => 'Abu Dhabi',
            'to' => 'Jeddah',
            'departure_time' => '2026-06-02 10:15:00',
            'price' => 500,
            'seats' => 18,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Flight updated successfully')
        ->assertJsonPath('data.from', 'Abu Dhabi')
        ->assertJsonPath('data.to', 'Jeddah')
        ->assertJsonPath('data.travel_date', '2026-06-02')
        ->assertJsonPath('data.departure_time', '2026-06-02 10:15:00')
        ->assertJsonPath('data.price', 500)
        ->assertJsonPath('data.seats', 18);
});

test('office can update booked flight price when departure time format is equivalent', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Equivalent Time']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Riyadh',
        'travel_date' => '2026-06-01',
        'departure_time' => '2026-06-01 15:45:00',
        'price' => 300,
        'seats' => 30,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 300,
        'status' => 'pending',
        'demanded' => true,
    ]);

    $response = $this->withHeaders(tokenHeaders($office))
        ->patchJson('/api/v1/office/flights/'.$flight->id, [
            'from' => 'Dubai',
            'to' => 'Riyadh',
            'departure_time' => '2026-06-01T15:45:00',
            'price' => 475,
            'seats' => 30,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Flight updated successfully')
        ->assertJsonPath('data.price', 475)
        ->assertJsonPath('data.departure_time', '2026-06-01 15:45:00');
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

test('traveler bookings include office location when office coordinates exist', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Maps']);
    OfficeLocation::create([
        'office_id' => $office->id,
        'lat' => 15.6123456,
        'lng' => 32.5345678,
    ]);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Khartoum',
        'travel_date' => '2026-10-09',
        'departure_time' => '2026-10-09 07:30:00',
        'price' => 500,
        'seats' => 20,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 1,
        'total' => 500,
        'status' => 'confirmed',
    ]);

    $this->withHeaders(tokenHeaders($traveler))
        ->getJson('/api/v1/traveler/bookings')
        ->assertOk()
        ->assertJsonPath('data.0.flight.location.lat', 15.6123456)
        ->assertJsonPath('data.0.flight.location.lng', 32.5345678);
});

test('traveler bookings return null office location when coordinates are missing', function () {
    $office = User::factory()->create(['role' => 'office', 'name' => 'Office Maps']);
    $traveler = User::factory()->create(['role' => 'traveler']);

    $flight = Flight::create([
        'from' => 'Dubai',
        'to' => 'Port Sudan',
        'travel_date' => '2026-10-12',
        'departure_time' => '2026-10-12 10:15:00',
        'price' => 450,
        'seats' => 18,
        'office_id' => $office->id,
        'office_name' => $office->name,
    ]);

    Booking::create([
        'flight_id' => $flight->id,
        'office_id' => $office->id,
        'traveler_id' => $traveler->id,
        'seats_booked' => 2,
        'total' => 900,
        'status' => 'confirmed',
    ]);

    $this->withHeaders(tokenHeaders($traveler))
        ->getJson('/api/v1/traveler/bookings')
        ->assertOk()
        ->assertJsonPath('data.0.flight.location', null);
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
