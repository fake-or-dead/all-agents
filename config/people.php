<?php

$lookupVersion = (string) env('PEOPLE_IDENTIFIER_LOOKUP_KEY_VERSION', 'v1');
$previousVersion = (string) env('PEOPLE_IDENTIFIER_LOOKUP_PREVIOUS_VERSION', '');

return [
    'identifier_lookup_key_version' => $lookupVersion,
    'identifier_lookup_previous_version' => $previousVersion,
    'identifier_lookup_previous_key' => env('PEOPLE_IDENTIFIER_LOOKUP_PREVIOUS_KEY'),
    'identifier_lookup_keys' => array_filter([
        $lookupVersion => env('PEOPLE_IDENTIFIER_LOOKUP_KEY'),
        $previousVersion => env('PEOPLE_IDENTIFIER_LOOKUP_PREVIOUS_KEY'),
    ], static fn (mixed $value, string $key): bool => $key !== '' && is_string($value) && $value !== '', ARRAY_FILTER_USE_BOTH),
];
