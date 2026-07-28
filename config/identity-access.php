<?php

return [
    'identifier_key' => env('IDENTITY_IDENTIFIER_KEY', env('APP_KEY')),
    'verification_adapter' => env('IDENTITY_VERIFICATION_ADAPTER', 'deterministic-fake'),
    'deterministic_code' => env('IDENTITY_DETERMINISTIC_CODE', '246810'),
    'dummy_password_hash' => env(
        'IDENTITY_DUMMY_PASSWORD_HASH',
        '$2y$12$vUHVIx4Uscjo9nuk8iu23uSSPfbslU8eOwr6P6POkHMKF4CDnHnaG',
    ),
    'verification_ttl_minutes' => 10,
    'recovery_ttl_minutes' => 30,
    'challenge_attempts' => 5,
    'registration_consent_version' => env(
        'IDENTITY_REGISTRATION_CONSENT_VERSION',
        'registration-consent-v1',
    ),
];
