<?php

namespace App\Modules\IdentityAccess\Application;

use App\Modules\IdentityAccess\Contracts\SecurityEventRecorder;
use App\Modules\IdentityAccess\Contracts\VerificationGateway;
use App\Modules\IdentityAccess\Data\IdentityAccessResult;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class IdentityAccessWorkflow
{
    private const UNKNOWN_RESOURCE_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private ConnectionInterface $database,
        private VerificationGateway $verification,
        private SecurityEventRecorder $securityEvents,
        private Encrypter $encrypter,
        private PersonIdentityDirectory $people,
    ) {}

    public function requestEmailVerification(string $email, string $correlationId): void
    {
        $normalized = $this->normalizeEmail($email);
        $digest = $this->digest('email', $normalized);
        $now = CarbonImmutable::now();
        $challengeId = (string) Str::uuid();

        $this->database->transaction(function () use (
            $challengeId,
            $correlationId,
            $digest,
            $normalized,
            $now,
        ): void {
            $this->database
                ->table('verification_challenges')
                ->where('purpose', 'registration')
                ->where('identifier_digest', $digest)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => $now,
                    'invalidated_reason' => 'resend',
                    'updated_at' => $now,
                ]);

            $code = $this->verification->issueVerificationCode($normalized, $challengeId);

            $this->database->table('verification_challenges')->insert([
                'id' => $challengeId,
                'purpose' => 'registration',
                'identifier_digest' => $digest,
                'secret_hash' => Hash::make($code),
                'attempts_remaining' => (int) config('identity-access.challenge_attempts'),
                'expires_at' => $now->addMinutes(
                    (int) config('identity-access.verification_ttl_minutes'),
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->securityEvents->record(
                actorType: 'visitor',
                actorId: 'anonymous',
                action: 'identity.verification.requested',
                resourceType: 'verification_challenge',
                resourceId: $challengeId,
                outcome: 'accepted',
                correlationId: $correlationId,
                context: ['purpose' => 'registration'],
            );
        });
    }

    public function verifyEmail(
        string $email,
        string $code,
        string $correlationId,
    ): IdentityAccessResult {
        $digest = $this->digest('email', $this->normalizeEmail($email));
        $now = CarbonImmutable::now();

        return $this->database->transaction(function () use (
            $code,
            $correlationId,
            $digest,
            $now,
        ): IdentityAccessResult {
            $challenge = $this->database
                ->table('verification_challenges')
                ->where('purpose', 'registration')
                ->where('identifier_digest', $digest)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($challenge === null) {
                $this->recordUnknownChallengeFailure('identity.verification.failed', $correlationId);

                return IdentityAccessResult::failure('invalid_challenge');
            }

            if (
                CarbonImmutable::parse($challenge->expires_at)->lessThanOrEqualTo($now)
                || (int) $challenge->attempts_remaining < 1
            ) {
                $this->invalidateChallenge($challenge->id, 'expired_or_exhausted', $now);
                $this->recordChallengeFailure(
                    $challenge->id,
                    'identity.verification.failed',
                    'expired_or_exhausted',
                    $correlationId,
                );

                return IdentityAccessResult::failure('invalid_challenge');
            }

            if (! is_string($challenge->secret_hash) || ! Hash::check($code, $challenge->secret_hash)) {
                $remaining = max(0, (int) $challenge->attempts_remaining - 1);
                $updates = [
                    'attempts_remaining' => $remaining,
                    'updated_at' => $now,
                ];

                if ($remaining === 0) {
                    $updates['invalidated_at'] = $now;
                    $updates['invalidated_reason'] = 'attempts_exhausted';
                }

                $this->database
                    ->table('verification_challenges')
                    ->where('id', $challenge->id)
                    ->update($updates);
                $this->recordChallengeFailure(
                    $challenge->id,
                    'identity.verification.failed',
                    'code_mismatch',
                    $correlationId,
                );

                return IdentityAccessResult::failure('invalid_challenge');
            }

            $registrationToken = Str::random(64);
            $this->database
                ->table('verification_challenges')
                ->where('id', $challenge->id)
                ->update([
                    'proof_digest' => hash('sha256', $registrationToken),
                    'verified_at' => $now,
                    'updated_at' => $now,
                ]);
            $this->securityEvents->record(
                actorType: 'visitor',
                actorId: 'anonymous',
                action: 'identity.verification.succeeded',
                resourceType: 'verification_challenge',
                resourceId: $challenge->id,
                outcome: 'succeeded',
                correlationId: $correlationId,
                context: ['purpose' => 'registration'],
            );

            return IdentityAccessResult::success('verified', [
                'registration_token' => $registrationToken,
                'expires_at' => CarbonImmutable::parse($challenge->expires_at)->toIso8601String(),
            ]);
        });
    }

    /**
     * @param  array{
     *   email:string,
     *   registration_token:string,
     *   identity_type:string,
     *   identity_number:string,
     *   given_name:string,
     *   family_name:string,
     *   password:string,
     *   consent_version:string
     * }  $input
     */
    public function register(array $input, string $correlationId): IdentityAccessResult
    {
        $email = $this->normalizeEmail($input['email']);
        $emailDigest = $this->digest('email', $email);
        $identityNumber = $this->normalizeIdentity(
            $input['identity_type'],
            $input['identity_number'],
        );
        $identityDigest = $this->digest($input['identity_type'], $identityNumber);
        $proofDigest = hash('sha256', $input['registration_token']);
        $now = CarbonImmutable::now();

        try {
            return $this->database->transaction(function () use (
                $correlationId,
                $email,
                $emailDigest,
                $identityDigest,
                $identityNumber,
                $input,
                $now,
                $proofDigest,
            ): IdentityAccessResult {
                $challenge = $this->database
                    ->table('verification_challenges')
                    ->where('purpose', 'registration')
                    ->where('identifier_digest', $emailDigest)
                    ->where('proof_digest', $proofDigest)
                    ->whereNotNull('verified_at')
                    ->whereNull('consumed_at')
                    ->whereNull('invalidated_at')
                    ->lockForUpdate()
                    ->first();

                if (
                    $challenge === null
                    || CarbonImmutable::parse($challenge->expires_at)->lessThanOrEqualTo($now)
                ) {
                    $this->recordChallengeFailure(
                        is_object($challenge) ? $challenge->id : self::UNKNOWN_RESOURCE_ID,
                        'account.registration',
                        'invalid_or_expired_proof',
                        $correlationId,
                    );

                    return IdentityAccessResult::failure('invalid_registration');
                }

                if (
                    $this->database->table('accounts')->where('email_digest', $emailDigest)->exists()
                    || $this->people->identifierExists($identityDigest)
                ) {
                    $this->recordChallengeFailure(
                        $challenge->id,
                        'account.registration',
                        'identifier_unavailable',
                        $correlationId,
                    );

                    return IdentityAccessResult::failure('invalid_registration');
                }

                $personId = $this->people->create(
                    $input['identity_type'],
                    $input['identity_type'] === 'personal_id' ? 'TH' : 'ZZ',
                    $identityNumber,
                    $identityDigest,
                    $input['given_name'],
                    $input['family_name'],
                );
                $accountId = (string) Str::uuid();
                $this->database->table('accounts')->insert([
                    'id' => $accountId,
                    'person_id' => $personId,
                    'email_digest' => $emailDigest,
                    'email_encrypted' => $this->encrypter->encrypt($email),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->database->table('credentials')->insert([
                    'account_id' => $accountId,
                    'password_hash' => Hash::make($input['password']),
                    'algorithm' => 'current',
                    'changed_at' => $now,
                ]);
                $this->database->table('consent_acceptances')->insert([
                    'id' => (string) Str::uuid(),
                    'person_id' => $personId,
                    'document_version' => $input['consent_version'],
                    'context' => 'registration',
                    'evidence' => json_encode([
                        'method' => 'explicit_checkbox',
                        'challenge_id' => $challenge->id,
                    ], JSON_THROW_ON_ERROR),
                    'accepted_at' => $now,
                ]);
                $this->database
                    ->table('verification_challenges')
                    ->where('id', $challenge->id)
                    ->update([
                        'consumed_at' => $now,
                        'updated_at' => $now,
                    ]);
                $this->securityEvents->record(
                    actorType: 'person',
                    actorId: $personId,
                    action: 'account.registered',
                    resourceType: 'account',
                    resourceId: $accountId,
                    outcome: 'succeeded',
                    correlationId: $correlationId,
                    context: ['consent_version' => $input['consent_version']],
                );

                return IdentityAccessResult::success('registered', [
                    'account_id' => $accountId,
                ]);
            }, 3);
        } catch (QueryException) {
            $this->recordChallengeFailure(
                self::UNKNOWN_RESOURCE_ID,
                'account.registration',
                'persistence_conflict',
                $correlationId,
            );

            return IdentityAccessResult::failure('invalid_registration');
        }
    }

    public function authenticate(
        string $identityType,
        string $identityNumber,
        string $password,
        string $correlationId,
    ): IdentityAccessResult {
        $identityDigest = $this->digest(
            $identityType,
            $this->normalizeIdentity($identityType, $identityNumber),
        );
        $personId = $this->people->personIdForIdentifier($identityDigest);
        $account = $this->database
            ->table('accounts')
            ->join('credentials', 'credentials.account_id', '=', 'accounts.id')
            ->where('accounts.person_id', $personId ?? self::UNKNOWN_RESOURCE_ID)
            ->where('accounts.status', 'active')
            ->select([
                'accounts.id',
                'accounts.person_id',
                'credentials.password_hash',
                'credentials.algorithm',
            ])
            ->first();

        $supported = $account !== null
            && in_array($account->algorithm, ['current', 'legacy_bcrypt'], true);
        $passwordHash = $supported
            ? (string) $account->password_hash
            : (string) config('identity-access.dummy_password_hash');
        $passwordMatches = Hash::check($password, $passwordHash);

        if (! $supported || ! $passwordMatches) {
            $this->securityEvents->record(
                actorType: 'visitor',
                actorId: 'anonymous',
                action: 'account.sign_in',
                resourceType: 'account',
                resourceId: self::UNKNOWN_RESOURCE_ID,
                outcome: 'denied',
                correlationId: $correlationId,
                context: ['reason' => 'invalid_credentials'],
            );

            return IdentityAccessResult::failure('invalid_credentials');
        }

        $rehashed = $account->algorithm === 'legacy_bcrypt'
            || Hash::needsRehash($account->password_hash);

        if ($rehashed) {
            $this->database->table('credentials')->where('account_id', $account->id)->update([
                'password_hash' => Hash::make($password),
                'algorithm' => 'current',
                'changed_at' => CarbonImmutable::now(),
            ]);
        }

        $this->securityEvents->record(
            actorType: 'account',
            actorId: $account->id,
            action: 'account.sign_in',
            resourceType: 'account',
            resourceId: $account->id,
            outcome: 'succeeded',
            correlationId: $correlationId,
            context: ['credential_rehashed' => $rehashed],
        );

        return IdentityAccessResult::success('authenticated', [
            'account_id' => $account->id,
        ]);
    }

    public function recordAuthenticatedSession(string $accountId, string $sessionId): void
    {
        $now = CarbonImmutable::now();
        $this->database->table('auth_sessions')->updateOrInsert(
            ['id' => $sessionId],
            [
                'account_id' => $accountId,
                'authenticated_at' => $now,
                'last_seen_at' => $now,
                'revoked_at' => null,
                'revoked_reason' => null,
            ],
        );
    }

    public function touchSession(string $accountId, string $sessionId): bool
    {
        return $this->database
            ->table('auth_sessions')
            ->where('id', $sessionId)
            ->where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->update(['last_seen_at' => CarbonImmutable::now()]) === 1;
    }

    public function signOut(
        string $accountId,
        string $sessionId,
        string $correlationId,
    ): void {
        $revoked = $this->database
            ->table('auth_sessions')
            ->where('id', $sessionId)
            ->where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => CarbonImmutable::now(),
                'revoked_reason' => 'sign_out',
            ]);

        if ($revoked === 0) {
            $this->database
                ->table('auth_sessions')
                ->where('account_id', $accountId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => CarbonImmutable::now(),
                    'revoked_reason' => 'sign_out',
                ]);
        }
        $this->securityEvents->record(
            actorType: 'account',
            actorId: $accountId,
            action: 'account.sign_out',
            resourceType: 'account',
            resourceId: $accountId,
            outcome: 'succeeded',
            correlationId: $correlationId,
        );
    }

    public function requestRecovery(string $email, string $correlationId): void
    {
        $normalized = $this->normalizeEmail($email);
        $digest = $this->digest('email', $normalized);
        $accountId = $this->database
            ->table('accounts')
            ->where('email_digest', $digest)
            ->where('status', 'active')
            ->value('id');

        $token = Str::random(72);
        $now = CarbonImmutable::now();
        $challengeId = (string) Str::uuid();

        $this->database->transaction(function () use (
            $accountId,
            $challengeId,
            $correlationId,
            $digest,
            $normalized,
            $now,
            $token,
        ): void {
            $this->database
                ->table('verification_challenges')
                ->where('purpose', 'recovery')
                ->where('identifier_digest', $digest)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => $now,
                    'invalidated_reason' => 'resend',
                    'updated_at' => $now,
                ]);
            $this->database->table('verification_challenges')->insert([
                'id' => $challengeId,
                'purpose' => 'recovery',
                'identifier_digest' => $digest,
                'token_digest' => hash('sha256', $token),
                'attempts_remaining' => 1,
                'expires_at' => $now->addMinutes(
                    (int) config('identity-access.recovery_ttl_minutes'),
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->verification->deliverRecoveryLink($normalized, $token);
            $this->securityEvents->record(
                actorType: is_string($accountId) ? 'account' : 'visitor',
                actorId: is_string($accountId) ? $accountId : 'anonymous',
                action: 'account.recovery.requested',
                resourceType: 'account',
                resourceId: is_string($accountId) ? $accountId : self::UNKNOWN_RESOURCE_ID,
                outcome: 'accepted',
                correlationId: $correlationId,
                context: ['delivery' => is_string($accountId) ? 'eligible' : 'sink'],
            );
        });
    }

    public function redeemRecovery(
        string $token,
        string $password,
        string $correlationId,
    ): IdentityAccessResult {
        $now = CarbonImmutable::now();
        $tokenDigest = hash('sha256', $token);

        return $this->database->transaction(function () use (
            $correlationId,
            $now,
            $password,
            $tokenDigest,
        ): IdentityAccessResult {
            $challenge = $this->database
                ->table('verification_challenges')
                ->where('purpose', 'recovery')
                ->where('token_digest', $tokenDigest)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->lockForUpdate()
                ->first();

            if (
                $challenge === null
                || CarbonImmutable::parse($challenge->expires_at)->lessThanOrEqualTo($now)
            ) {
                if ($challenge !== null) {
                    $this->invalidateChallenge($challenge->id, 'expired', $now);
                }
                $this->recordChallengeFailure(
                    is_object($challenge) ? $challenge->id : self::UNKNOWN_RESOURCE_ID,
                    'account.recovery.redeemed',
                    'invalid_or_expired_token',
                    $correlationId,
                );

                return IdentityAccessResult::failure('invalid_recovery');
            }

            $accountId = $this->database
                ->table('accounts')
                ->where('email_digest', $challenge->identifier_digest)
                ->where('status', 'active')
                ->value('id');

            if (! is_string($accountId)) {
                $this->invalidateChallenge($challenge->id, 'account_unavailable', $now);
                $this->recordChallengeFailure(
                    $challenge->id,
                    'account.recovery.redeemed',
                    'account_unavailable',
                    $correlationId,
                );

                return IdentityAccessResult::failure('invalid_recovery');
            }

            $this->database->table('credentials')->where('account_id', $accountId)->update([
                'password_hash' => Hash::make($password),
                'algorithm' => 'current',
                'changed_at' => $now,
            ]);
            $this->database
                ->table('auth_sessions')
                ->where('account_id', $accountId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revoked_reason' => 'credential_recovery',
                ]);
            $this->database
                ->table('verification_challenges')
                ->where('id', $challenge->id)
                ->update([
                    'consumed_at' => $now,
                    'updated_at' => $now,
                ]);
            $this->securityEvents->record(
                actorType: 'account',
                actorId: $accountId,
                action: 'account.recovery.redeemed',
                resourceType: 'account',
                resourceId: $accountId,
                outcome: 'succeeded',
                correlationId: $correlationId,
                context: ['sessions_revoked' => true],
            );

            return IdentityAccessResult::success('recovered');
        });
    }

    public function changePassword(
        string $accountId,
        string $currentPassword,
        string $newPassword,
        string $currentSessionId,
        string $correlationId,
    ): IdentityAccessResult {
        $credential = $this->database
            ->table('credentials')
            ->where('account_id', $accountId)
            ->first();

        if (
            $credential === null
            || ! in_array($credential->algorithm, ['current', 'legacy_bcrypt'], true)
            || ! Hash::check($currentPassword, $credential->password_hash)
        ) {
            $this->securityEvents->record(
                actorType: 'account',
                actorId: $accountId,
                action: 'account.password.changed',
                resourceType: 'account',
                resourceId: $accountId,
                outcome: 'denied',
                correlationId: $correlationId,
                context: ['reason' => 'invalid_current_credential'],
            );

            return IdentityAccessResult::failure('invalid_credentials');
        }

        $now = CarbonImmutable::now();
        $this->database->transaction(function () use (
            $accountId,
            $correlationId,
            $currentSessionId,
            $newPassword,
            $now,
        ): void {
            $this->database->table('credentials')->where('account_id', $accountId)->update([
                'password_hash' => Hash::make($newPassword),
                'algorithm' => 'current',
                'changed_at' => $now,
            ]);
            $this->database
                ->table('auth_sessions')
                ->where('account_id', $accountId)
                ->where('id', '!=', $currentSessionId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revoked_reason' => 'credential_change',
                ]);
            $this->securityEvents->record(
                actorType: 'account',
                actorId: $accountId,
                action: 'account.password.changed',
                resourceType: 'account',
                resourceId: $accountId,
                outcome: 'succeeded',
                correlationId: $correlationId,
                context: ['other_sessions_revoked' => true],
            );
        });

        return IdentityAccessResult::success('password_changed');
    }

    public function recordRateLimited(string $action, string $correlationId): void
    {
        $this->securityEvents->record(
            actorType: 'visitor',
            actorId: 'anonymous',
            action: $action,
            resourceType: 'request',
            resourceId: self::UNKNOWN_RESOURCE_ID,
            outcome: 'denied',
            correlationId: $correlationId,
            context: ['reason' => 'rate_limited'],
        );
    }

    private function invalidateChallenge(
        string $challengeId,
        string $reason,
        CarbonImmutable $now,
    ): void {
        $this->database
            ->table('verification_challenges')
            ->where('id', $challengeId)
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => $now,
                'invalidated_reason' => $reason,
                'updated_at' => $now,
            ]);
    }

    private function recordUnknownChallengeFailure(string $action, string $correlationId): void
    {
        $this->securityEvents->record(
            actorType: 'visitor',
            actorId: 'anonymous',
            action: $action,
            resourceType: 'verification_challenge',
            resourceId: self::UNKNOWN_RESOURCE_ID,
            outcome: 'denied',
            correlationId: $correlationId,
            context: ['reason' => 'unavailable'],
        );
    }

    private function recordChallengeFailure(
        string $challengeId,
        string $action,
        string $reason,
        string $correlationId,
    ): void {
        $this->securityEvents->record(
            actorType: 'visitor',
            actorId: 'anonymous',
            action: $action,
            resourceType: 'verification_challenge',
            resourceId: $challengeId,
            outcome: 'denied',
            correlationId: $correlationId,
            context: ['reason' => $reason],
        );
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizeIdentity(string $type, string $number): string
    {
        $normalized = mb_strtoupper(preg_replace('/[\s-]+/u', '', trim($number)) ?? '');

        if ($type === 'personal_id' && preg_match('/^\d{13}$/', $normalized) !== 1) {
            throw new RuntimeException('Invalid personal identity number.');
        }

        if ($type === 'passport' && preg_match('/^[A-Z0-9]{6,20}$/', $normalized) !== 1) {
            throw new RuntimeException('Invalid passport number.');
        }

        if (! in_array($type, ['personal_id', 'passport'], true)) {
            throw new RuntimeException('Unsupported identity type.');
        }

        return $normalized;
    }

    private function digest(string $namespace, string $value): string
    {
        $key = (string) config('identity-access.identifier_key');

        if ($key === '') {
            throw new RuntimeException('Identity identifier key is not configured.');
        }

        return hash_hmac('sha256', "{$namespace}:{$value}", $key);
    }
}
