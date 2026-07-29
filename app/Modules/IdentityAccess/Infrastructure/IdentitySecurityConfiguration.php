<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final readonly class IdentitySecurityConfiguration
{
    public function __construct(
        private Application $application,
        private Repository $config,
    ) {}

    public function assertSafe(): void
    {
        $adapter = $this->config->get('identity-access.verification_adapter');

        if ($adapter === 'disabled') {
            return;
        }

        if ($adapter === 'deterministic-fake' && ! $this->application->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'The deterministic identity verification adapter is restricted to local/testing.',
            );
        }

        $keySets = [
            'people lookup' => $this->keys(
                'people.identifier_lookup_key_version',
                'people.identifier_lookup_keys',
            ),
            'account lookup' => $this->keys(
                'identity-access.account_lookup_key_version',
                'identity-access.account_lookup_keys',
            ),
            'rate limit' => $this->keys(
                'identity-access.rate_limit_key_version',
                'identity-access.rate_limit_keys',
            ),
        ];
        $appKey = (string) $this->config->get('app.key');
        $allKeys = [];

        foreach ($keySets as $name => $keys) {
            foreach ($keys as $version => $key) {
                if (! is_string($version) || $version === '' || ! is_string($key) || strlen($key) < 32) {
                    throw new RuntimeException("Missing or unsafe versioned key: {$name}.");
                }
                if ($appKey !== '' && hash_equals($appKey, $key)) {
                    throw new RuntimeException("The {$name} key must not equal APP_KEY.");
                }
                $allKeys["{$name}:{$version}"] = $key;
            }
        }

        if (count(array_unique(array_values($allKeys))) !== count($allKeys)) {
            throw new RuntimeException('Identity security keys must be distinct.');
        }

        $this->assertSupportedBcryptCosts();

        if (
            $adapter === 'deterministic-fake'
            && preg_match('/^\d{6}$/', (string) $this->config->get(
                'identity-access.deterministic_code',
            )) !== 1
        ) {
            throw new RuntimeException('A six-digit local deterministic code is required.');
        }
    }

    /** @return array<array-key, mixed> */
    private function keys(string $versionPath, string $keysPath): array
    {
        $version = $this->config->get($versionPath);
        $keys = $this->config->get($keysPath);

        if (! is_string($version) || ! is_array($keys)) {
            throw new RuntimeException("Missing or unsafe versioned key: {$keysPath}.");
        }

        $current = $keys[$version] ?? null;

        if (! is_string($current) || strlen($current) < 32) {
            throw new RuntimeException("Missing or unsafe versioned key: {$keysPath}.");
        }

        return $keys;
    }

    private function assertSupportedBcryptCosts(): void
    {
        $configuredCost = $this->config->get('hashing.bcrypt.rounds');
        $costs = $this->config->get('identity-access.supported_bcrypt_costs');
        $hashes = $this->config->get('identity-access.bcrypt_dummy_hashes');

        if (! is_numeric($configuredCost) || ! is_array($costs) || ! is_array($hashes)) {
            throw new RuntimeException('Supported bcrypt costs are not configured.');
        }
        $configuredCost = (int) $configuredCost;

        $supportedCosts = array_values(array_unique(array_filter(
            $costs,
            static fn (mixed $cost): bool => is_int($cost) && $cost >= 4 && $cost <= 31,
        )));

        if ($supportedCosts === [] || ! in_array($configuredCost, $supportedCosts, true)) {
            throw new RuntimeException("Unsupported configured bcrypt cost {$configuredCost}.");
        }

        foreach ($supportedCosts as $cost) {
            $hash = $hashes[$cost] ?? null;
            $info = is_string($hash) ? password_get_info($hash) : null;

            if (
                ! is_array($info)
                || ($info['algoName'] ?? null) !== 'bcrypt'
                || ($info['options']['cost'] ?? null) !== $cost
            ) {
                throw new RuntimeException("Invalid dummy bcrypt hash for cost {$cost}.");
            }
        }
    }
}
