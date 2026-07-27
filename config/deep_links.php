<?php

return [
    'domain' => env('DEEP_LINK_DOMAIN', 'amrochic.com'),
    'android_package' => env('ANDROID_APP_LINK_PACKAGE', 'com.safriat.safriat'),
    'android_store_url' => env(
        'ANDROID_APP_LINK_STORE_URL',
        'https://play.google.com/store/apps/details?id='
        .env('ANDROID_APP_LINK_PACKAGE', 'com.safriat.safriat')
    ),
    'android_sha256_fingerprints' => array_values(array_filter(array_map(
        static fn (?string $value): string => trim((string) $value),
        explode(',', (string) env('ANDROID_APP_LINK_SHA256_CERT_FINGERPRINTS', ''))
    ))),
    'ios_app_id' => env('IOS_APP_LINK_APP_ID', ''),
    'custom_scheme' => env('CUSTOM_DEEP_LINK_SCHEME', 'safriat'),
];
