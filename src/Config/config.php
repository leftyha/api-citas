<?php

declare(strict_types=1);

return [
    'app' => [
        'timezone' => 'America/Caracas',
        'base_path' => '/ws/dashboard-usd/api',
        'allowed_channels' => ['web', 'call-center', 'store', 'admin', 'internal'],
        'cors' => [
            'allowed_origins' => array_values(
                array_filter(
                    array_map('trim', explode(',', (string) (getenv('BOOKING_CORS_ALLOWED_ORIGINS') ?: '*')))
                )
            ),
        ],
    ],
    'database' => [
        'driver' => 'sqlsrv',
    ],
    'booking' => [
        'default_duration_minutes' => 30,
        'active_statuses' => ['pending', 'confirmed'],
        'token_ttl_seconds' => 60 * 60 * 24 * 90,
    ],
    'security' => [
        'appointment_token' => [
            'secret' => getenv('BOOKING_TOKEN_SECRET') ?: 'change-me-in-production',
            'key_id' => getenv('BOOKING_TOKEN_KEY_ID') ?: 'booking-token-2026-01',
            'prefix' => 'apt_',
        ],
        'admin' => [
            'token' => getenv('BOOKING_ADMIN_TOKEN') ?: 'change-me-in-production',
        ],
        'rate_limit' => [
            'max_attempts' => (int) (getenv('BOOKING_RATE_LIMIT_MAX_ATTEMPTS') ?: 60),
            'window_seconds' => (int) (getenv('BOOKING_RATE_LIMIT_WINDOW_SECONDS') ?: 60),
        ],
    ],
];
