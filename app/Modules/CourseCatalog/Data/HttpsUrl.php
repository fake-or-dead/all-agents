<?php

namespace App\Modules\CourseCatalog\Data;

final readonly class HttpsUrl
{
    private function __construct(public string $value) {}

    public static function fromUntrusted(string $value): ?self
    {
        $parts = parse_url($value);
        if (
            ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return new self($value);
    }
}
