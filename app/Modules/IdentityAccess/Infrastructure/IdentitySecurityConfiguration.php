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

        $keys = [
            'people lookup' => $this->currentKey(
                'people.identifier_lookup_key_version',
                'people.identifier_lookup_keys',
            ),
            'account lookup' => $this->currentKey(
                'identity-access.account_lookup_key_version',
                'identity-access.account_lookup_keys',
            ),
            'rate limit' => $this->currentKey(
                'identity-access.rate_limit_key_version',
                'identity-access.rate_limit_keys',
            ),
        ];
        $appKey = (string) $this->config->get('app.key');

        foreach ($keys as $name => $key) {
            if ($appKey !== '' && hash_equals($appKey, $key)) {
                throw new RuntimeException("The {$name} key must not equal APP_KEY.");
            }
        }

        if (count(array_unique(array_values($keys))) !== count($keys)) {
            throw new RuntimeException('Identity security keys must be distinct.');
        }

        if (
            $adapter === 'deterministic-fake'
            && preg_match('/^\d{6}$/', (string) $this->config->get(
                'identity-access.deterministic_code',
            )) !== 1
        ) {
            throw new RuntimeException('A six-digit local deterministic code is required.');
        }
    }

    private function currentKey(string $versionPath, string $keysPath): string
    {
        $version = $this->config->get($versionPath);
        $keys = $this->config->get($keysPath);
        $key = is_string($version) && is_array($keys) ? ($keys[$version] ?? null) : null;

        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException("Missing or unsafe versioned key: {$keysPath}.");
        }

        return $key;
    }
}
