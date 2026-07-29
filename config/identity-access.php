<?php

$accountLookupVersion = (string) env('IDENTITY_ACCOUNT_LOOKUP_KEY_VERSION', 'v1');
$accountLookupPreviousVersion = (string) env('IDENTITY_ACCOUNT_LOOKUP_PREVIOUS_VERSION', '');
$rateLimitVersion = (string) env('IDENTITY_RATE_LIMIT_KEY_VERSION', 'v1');

return [
    'account_lookup_key_version' => $accountLookupVersion,
    'account_lookup_previous_version' => $accountLookupPreviousVersion,
    'account_lookup_previous_key' => env('IDENTITY_ACCOUNT_LOOKUP_PREVIOUS_KEY'),
    'account_lookup_keys' => array_filter([
        $accountLookupVersion => env('IDENTITY_ACCOUNT_LOOKUP_KEY'),
        $accountLookupPreviousVersion => env('IDENTITY_ACCOUNT_LOOKUP_PREVIOUS_KEY'),
    ], static fn (mixed $value, string $key): bool => $key !== '' && is_string($value) && $value !== '', ARRAY_FILTER_USE_BOTH),
    'rate_limit_key_version' => $rateLimitVersion,
    'rate_limit_keys' => array_filter([
        $rateLimitVersion => env('IDENTITY_RATE_LIMIT_KEY'),
    ], static fn (mixed $value, string $key): bool => $key !== '' && is_string($value) && $value !== '', ARRAY_FILTER_USE_BOTH),
    // The verifier has reviewed dummy work only for these costs. Boot rejects
    // any other BCRYPT_ROUNDS value instead of creating an account oracle.
    'supported_bcrypt_costs' => [4, 10, 12],
    'bcrypt_dummy_hashes' => [
        4 => '$2y$04$9M1oyGYDQCPavZBB4MZmkuzsBXytNFQL674iSLMZBY8AK5/DZbXXK',
        10 => '$2y$10$c9oRhtixexvMsLliUUP1he3lrOFEIlgvdlP4MhAtfgzfFhw0qS0zq',
        12 => '$2y$12$V/lS4PPR.KfqMrQ7ZkZTnec56ho6tvpp3a/9Y3/Spb7KpcXwKiy4O',
    ],
    'verification_adapter' => env('IDENTITY_VERIFICATION_ADAPTER', 'disabled'),
    'deterministic_code' => env('IDENTITY_DETERMINISTIC_CODE'),
    'verification_ttl_minutes' => 10,
    'recovery_ttl_minutes' => 30,
    'challenge_attempts' => 5,
];
