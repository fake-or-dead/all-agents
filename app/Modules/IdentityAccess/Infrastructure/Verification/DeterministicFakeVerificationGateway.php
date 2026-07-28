<?php

namespace App\Modules\IdentityAccess\Infrastructure\Verification;

use App\Modules\IdentityAccess\Contracts\LocalVerificationMailbox;
use App\Modules\IdentityAccess\Contracts\VerificationGateway;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DeterministicFakeVerificationGateway implements LocalVerificationMailbox, VerificationGateway
{
    public function __construct(
        private ConnectionInterface $database,
        private Encrypter $encrypter,
    ) {}

    public function issueVerificationCode(string $email, string $challengeId): string
    {
        return (string) config('identity-access.deterministic_code');
    }

    public function deliverRecoveryLink(string $email, string $token): void
    {
        $now = CarbonImmutable::now();
        $this->database->table('local_verification_deliveries')->insert([
            'id' => (string) Str::uuid(),
            'kind' => 'recovery',
            'recipient_digest' => $this->recipientDigest($email),
            'payload_encrypted' => $this->encrypter->encrypt("/recover/{$token}"),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function latestRecoveryPathFor(string $email): ?string
    {
        $payload = $this->database
            ->table('local_verification_deliveries')
            ->where('kind', 'recovery')
            ->where('recipient_digest', $this->recipientDigest($email))
            ->latest('created_at')
            ->value('payload_encrypted');

        return is_string($payload) ? $this->encrypter->decrypt($payload) : null;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function recipientDigest(string $email): string
    {
        return hash_hmac(
            'sha256',
            'email:'.$this->normalizeEmail($email),
            (string) config('identity-access.identifier_key'),
        );
    }
}
