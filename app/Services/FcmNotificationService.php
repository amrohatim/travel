<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\UserDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    public function sendNewBookingToOffice(Booking $booking): void
    {
        $token = UserDeviceToken::where('user_id', $booking->office_id)
            ->latest('id')
            ->value('fcm_token');

        if (! $token) {
            return;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            Log::warning('FCM access token could not be resolved.');
            return;
        }

        $projectId = (string) config('services.firebase.project_id');
        if ($projectId === '') {
            Log::warning('Firebase project ID is missing.');
            return;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($url, [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => 'حجز جديد',
                        'body' => 'تم استلام طلب حجز جديد يحتاج للمراجعة.',
                    ],
                    'data' => [
                        'type' => 'new_booking',
                        'booking_id' => (string) $booking->id,
                        'flight_id' => (string) $booking->flight_id,
                    ],
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'booking_alerts',
                            'sound' => 'default',
                            'default_vibrate_timings' => true,
                            'notification_priority' => 'PRIORITY_MAX',
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return;
        }

        if ($this->isInvalidTokenError($response->json())) {
            UserDeviceToken::where('fcm_token', $token)->delete();
            return;
        }

        Log::warning('FCM send failed.', [
            'status' => $response->status(),
            'body' => $response->json(),
            'booking_id' => $booking->id,
            'office_id' => $booking->office_id,
        ]);
    }

    private function getAccessToken(): ?string
    {
        [$clientEmail, $privateKey] = $this->resolveCredentials();
        $tokenUri = (string) config('services.firebase.token_uri', 'https://oauth2.googleapis.com/token');

        if ($clientEmail === '' || $privateKey === '') {
            Log::warning('Firebase credentials are missing or invalid.');
            return null;
        }

        $privateKey = str_replace('\n', "\n", $privateKey);
        $jwt = $this->buildJwt($clientEmail, $privateKey, $tokenUri);
        if (! $jwt) {
            return null;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            Log::warning('Unable to fetch Firebase access token.', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json('access_token');
    }

    private function resolveCredentials(): array
    {
        $clientEmail = trim((string) config('services.firebase.client_email'));
        $privateKey = (string) config('services.firebase.private_key');
        $credentialsPath = trim((string) config('services.firebase.credentials_path'));

        if ($this->looksLikePrivateKey($privateKey)) {
            return [$clientEmail, $privateKey];
        }

        if ($credentialsPath !== '') {
            $resolvedPath = $credentialsPath;
            if (! str_starts_with($credentialsPath, '/')) {
                $resolvedPath = base_path($credentialsPath);
            }

            if (is_file($resolvedPath) && is_readable($resolvedPath)) {
                $decoded = json_decode((string) file_get_contents($resolvedPath), true);
                if (is_array($decoded)) {
                    $jsonClientEmail = trim((string) ($decoded['client_email'] ?? ''));
                    $jsonPrivateKey = (string) ($decoded['private_key'] ?? '');
                    if ($jsonClientEmail !== '' && $this->looksLikePrivateKey($jsonPrivateKey)) {
                        return [$jsonClientEmail, $jsonPrivateKey];
                    }
                }
            }
        }

        return [$clientEmail, $privateKey];
    }

    private function looksLikePrivateKey(string $privateKey): bool
    {
        return str_contains($privateKey, 'BEGIN PRIVATE KEY');
    }

    private function buildJwt(string $clientEmail, string $privateKey, string $tokenUri): ?string
    {
        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];
        $claims = [
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedClaims = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $data = $encodedHeader.'.'.$encodedClaims;

        $signature = '';
        $signed = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            return null;
        }

        return $data.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function isInvalidTokenError(mixed $body): bool
    {
        if (! is_array($body)) {
            return false;
        }

        $status = data_get($body, 'error.status');
        if (in_array($status, ['UNREGISTERED', 'NOT_FOUND'], true)) {
            return true;
        }

        $details = data_get($body, 'error.details', []);
        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            $errorCode = data_get($detail, 'errorCode');
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                return true;
            }
        }

        return false;
    }
}
