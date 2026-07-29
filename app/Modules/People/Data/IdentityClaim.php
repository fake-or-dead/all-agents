<?php

namespace App\Modules\People\Data;

use InvalidArgumentException;

final readonly class IdentityClaim
{
    private function __construct(
        public string $type,
        public string $countryCode,
        public string $normalizedIdentifier,
    ) {}

    public static function fromInput(string $type, string $identifier): self
    {
        $normalized = mb_strtoupper(
            preg_replace('/[\s-]+/u', '', trim($identifier)) ?? '',
        );

        if ($type === 'personal_id' && preg_match('/^\d{13}$/', $normalized) === 1) {
            return new self($type, 'TH', $normalized);
        }

        if ($type === 'passport' && preg_match('/^[A-Z0-9]{6,20}$/', $normalized) === 1) {
            return new self($type, 'ZZ', $normalized);
        }

        throw new InvalidArgumentException('Invalid identity claim.');
    }
}
