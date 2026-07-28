<?php

namespace App\Modules\IdentityAccess\Data;

final readonly class Actor
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $type,
        public string $id,
        private array $capabilities,
    ) {}

    public function can(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
