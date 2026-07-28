<?php

namespace App\Modules\IdentityAccess\Data;

final readonly class IdentityAccessResult
{
    /**
     * @param  array<string, scalar|null>  $data
     */
    private function __construct(
        public bool $successful,
        public string $code,
        public array $data = [],
    ) {}

    /**
     * @param  array<string, scalar|null>  $data
     */
    public static function success(string $code, array $data = []): self
    {
        return new self(true, $code, $data);
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public static function failure(string $code, array $data = []): self
    {
        return new self(false, $code, $data);
    }
}
