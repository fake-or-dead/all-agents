<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;

final readonly class ConstantWorkPasswordVerifier
{
    public function __construct(private Repository $config) {}

    public function verify(?object $credential, string $password): bool
    {
        $actualCost = $this->supportedCost($credential);
        $matched = false;

        foreach ($this->supportedCosts() as $cost) {
            $hash = $actualCost === $cost
                ? (string) $credential->password_hash
                : $this->dummyHash($cost);
            $candidateMatched = password_verify($password, $hash);

            if ($actualCost === $cost) {
                $matched = $candidateMatched;
            }
        }

        return $actualCost !== null && $matched;
    }

    private function supportedCost(?object $credential): ?int
    {
        if (
            $credential === null
            || ! in_array($credential->algorithm ?? null, ['current', 'legacy_bcrypt'], true)
            || ! is_string($credential->password_hash ?? null)
        ) {
            return null;
        }

        $info = password_get_info($credential->password_hash);
        $cost = $info['options']['cost'] ?? null;

        return is_int($cost) && in_array($cost, $this->supportedCosts(), true)
            ? $cost
            : null;
    }

    /** @return list<int> */
    private function supportedCosts(): array
    {
        $costs = $this->config->get('identity-access.supported_bcrypt_costs');

        if (! is_array($costs) || $costs === []) {
            throw new RuntimeException('Supported bcrypt costs are not configured.');
        }

        $normalized = array_values(array_unique(array_map('intval', $costs)));
        $configured = (int) $this->config->get('hashing.bcrypt.rounds', 12);

        if (! in_array($configured, $normalized, true)) {
            throw new RuntimeException("Unsupported configured bcrypt cost {$configured}.");
        }

        foreach ($normalized as $cost) {
            $this->dummyHash($cost);
        }

        return $normalized;
    }

    private function dummyHash(int $cost): string
    {
        $hashes = $this->config->get('identity-access.bcrypt_dummy_hashes');
        $hash = is_array($hashes) ? ($hashes[$cost] ?? null) : null;

        if (! is_string($hash) || $hash === '') {
            throw new RuntimeException("Missing dummy bcrypt hash for cost {$cost}.");
        }

        return $hash;
    }
}
