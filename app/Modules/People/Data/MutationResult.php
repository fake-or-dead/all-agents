<?php

namespace App\Modules\People\Data;

final readonly class MutationResult
{
    /** @param array<string, mixed> $value */
    private function __construct(
        public bool $successful,
        public string $code,
        public array $value,
    ) {}

    /** @param array<string, mixed> $value */
    public static function success(array $value): self
    {
        return new self(true, 'ok', $value);
    }

    /** @param array<string, mixed> $value */
    public static function replay(array $value): self
    {
        return new self(true, 'idempotent-replay', $value);
    }

    public static function idempotencyConflict(): self
    {
        return new self(false, 'idempotency-conflict', []);
    }

    public static function stale(): self
    {
        return new self(false, 'stale', []);
    }

    /** @param array<string, mixed> $value */
    public static function invalidReference(array $value): self
    {
        return new self(false, 'invalid-reference', $value);
    }

    public static function denied(): self
    {
        return new self(false, 'denied', []);
    }
}
