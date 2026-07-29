<?php

namespace App\Modules\People\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Encryption\Encrypter;
use JsonException;
use RuntimeException;

final readonly class ApplicationContextEvidenceCipher
{
    private const string Cipher = 'aes-256-gcm';

    public function __construct(
        private Repository $config,
    ) {}

    public function currentKeyVersion(): string
    {
        $version = $this->config->get('people.context_evidence_key_version');

        if (! is_string($version) || $version === '') {
            throw new RuntimeException('Missing People application context evidence key version.');
        }

        return $version;
    }

    /** @param array<string, int|string|null> $facts */
    public function encrypt(array $facts): string
    {
        return $this->encrypter($this->currentKeyVersion())->encryptString(
            json_encode($facts, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function decrypt(string $keyVersion, string $ciphertext): array
    {
        $decoded = json_decode(
            $this->encrypter($keyVersion)->decryptString($ciphertext),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (! is_array($decoded)) {
            throw new JsonException('Application context evidence is not an object.');
        }

        return $decoded;
    }

    private function encrypter(string $keyVersion): Encrypter
    {
        $keys = $this->config->get('people.context_evidence_keys');
        $configured = is_array($keys) ? ($keys[$keyVersion] ?? null) : null;

        if (! is_string($configured) || $configured === '') {
            throw new RuntimeException(
                "Missing People application context evidence key {$keyVersion}.",
            );
        }

        $key = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : $configured;

        if (! is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException(
                "People application context evidence key {$keyVersion} must contain 32 bytes.",
            );
        }

        return new Encrypter($key, self::Cipher);
    }
}
