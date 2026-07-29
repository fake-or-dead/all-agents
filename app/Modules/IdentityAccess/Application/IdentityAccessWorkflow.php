<?php

namespace App\Modules\IdentityAccess\Application;

use App\Modules\DocumentsConsent\Contracts\ConsentAcceptanceService;
use App\Modules\IdentityAccess\Contracts\SecurityEventRecorder;
use App\Modules\IdentityAccess\Contracts\VerificationGateway;
use App\Modules\IdentityAccess\Data\IdentityAccessResult;
use App\Modules\IdentityAccess\Infrastructure\ConstantWorkPasswordVerifier;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
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
        private ConstantWorkPasswordVerifier $passwordVerifier,
        private ConsentAcceptanceService $consents,
    ) {}

    public function requestEmailVerification(string $email, string $correlationId): void
    {
        $normalized = $this->normalizeEmail($email);
        [$digestVersion, $digest] = $this->currentAccountDigest($normalized);
        $digestCandidates = $this->accountDigestCandidates($normalized);
        $now = CarbonImmutable::now();
        $challengeId = (string) Str::uuid();

        $this->database->transaction(function () use (
            $challengeId,
            $correlationId,
            $digest,
            $digestCandidates,
            $digestVersion,
            $normalized,
            $now,
        ): void {
            ksort($digestCandidates);

            foreach ($digestCandidates as $candidateDigest) {
                $this->lockChallengeSubject('registration', $candidateDigest, $now);
            }

            $activeChallenges = $this->database
                ->table('verification_challenges')
                ->where('purpose', 'registration')
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at');
            $this->applyDigestCandidates($activeChallenges, $digestCandidates)
                ->update([
                    'invalidated_at' => $now,
                    'invalidated_reason' => 'resend',
                    'active_slot' => null,
                    'updated_at' => $now,
                ]);

            $code = $this->verification->issueVerificationCode($normalized, $challengeId);

            $this->database->table('verification_challenges')->insert([
                'id' => $challengeId,
                'purpose' => 'registration',
                'identifier_key_version' => $digestVersion,
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
        $emailDigests = $this->accountDigestCandidates($this->normalizeEmail($email));
        $now = CarbonImmutable::now();

        return $this->database->transaction(function () use (
            $code,
            $correlationId,
            $emailDigests,
            $now,
        ): IdentityAccessResult {
            $challengeQuery = $this->database
                ->table('verification_challenges')
                ->where('purpose', 'registration')
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->whereNull('verified_at');
            $this->applyDigestCandidates($challengeQuery, $emailDigests);
            $challenge = $challengeQuery
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
                    $updates['active_slot'] = null;
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
                    'secret_hash' => null,
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
     *   consent_version:string,
     *   person_link_token?:string|null
     * }  $input
     */
    public function register(array $input, string $correlationId): IdentityAccessResult
    {
        $email = $this->normalizeEmail($input['email']);
        [$emailDigestVersion, $emailDigest] = $this->currentAccountDigest($email);
        $emailDigestCandidates = $this->accountDigestCandidates($email);
        $identity = IdentityClaim::fromInput(
            $input['identity_type'],
            $input['identity_number'],
        );
        $proofDigest = hash('sha256', $input['registration_token']);
        $now = CarbonImmutable::now();

        try {
            return $this->database->transaction(function () use (
                $correlationId,
                $email,
                $emailDigest,
                $emailDigestCandidates,
                $emailDigestVersion,
                $identity,
                $input,
                $now,
                $proofDigest,
            ): IdentityAccessResult {
                $challengeQuery = $this->database
                    ->table('verification_challenges')
                    ->where('purpose', 'registration')
                    ->where('proof_digest', $proofDigest)
                    ->whereNotNull('verified_at')
                    ->whereNull('consumed_at')
                    ->whereNull('invalidated_at');
                $this->applyDigestCandidates($challengeQuery, $emailDigestCandidates);
                $challenge = $challengeQuery
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
                    $this->accountExistsForDigests($emailDigestCandidates)
                ) {
                    $this->recordChallengeFailure(
                        $challenge->id,
                        'account.registration',
                        'identifier_unavailable',
                        $correlationId,
                    );

                    return IdentityAccessResult::failure('invalid_registration');
                }

                $personId = $this->people->claimForAccount(
                    $identity,
                    $input['given_name'],
                    $input['family_name'],
                    $input['person_link_token'] ?? null,
                    $now,
                );

                if ($personId === null) {
                    $this->recordChallengeFailure(
                        $challenge->id,
                        'account.registration',
                        'ownership_proof_required',
                        $correlationId,
                    );

                    return IdentityAccessResult::failure('invalid_registration');
                }
                $accountId = (string) Str::uuid();
                $this->database->table('accounts')->insert([
                    'id' => $accountId,
                    'person_id' => $personId,
                    'email_digest_key_version' => $emailDigestVersion,
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
                $this->consents->acceptRegistration(
                    personId: $personId,
                    requestedVersionId: $input['consent_version'],
                    evidence: [
                        'method' => 'explicit_checkbox',
                        'challenge_id' => $challenge->id,
                    ],
                    acceptedAt: $now,
                );
                $this->database
                    ->table('verification_challenges')
                    ->where('id', $challenge->id)
                    ->update([
                        'consumed_at' => $now,
                        'active_slot' => null,
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
        } catch (QueryException|RuntimeException) {
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
        string $sessionId,
        string $correlationId,
    ): IdentityAccessResult {
        $identity = IdentityClaim::fromInput(
            $identityType,
            $identityNumber,
        );
        $personId = $this->people->personIdForIdentity($identity);

        return $this->database->transaction(function () use (
            $correlationId,
            $password,
            $personId,
            $sessionId,
        ): IdentityAccessResult {
            $account = $this->database
                ->table('accounts')
                ->where('person_id', $personId ?? self::UNKNOWN_RESOURCE_ID)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first(['id', 'person_id', 'credential_epoch']);
            $credential = $account === null
                ? null
                : $this->database
                    ->table('credentials')
                    ->where('account_id', $account->id)
                    ->lockForUpdate()
                    ->first();

            if (! $this->passwordVerifier->verify($credential, $password)) {
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

            $rehashed = $credential->algorithm === 'legacy_bcrypt'
                || Hash::needsRehash($credential->password_hash);
            $now = CarbonImmutable::now();

            if ($rehashed) {
                $this->database->table('credentials')->where('account_id', $account->id)->update([
                    'password_hash' => Hash::make($password),
                    'algorithm' => 'current',
                    'changed_at' => $now,
                ]);
            }

            $this->writeAuthenticatedSession(
                $account->id,
                $sessionId,
                (int) $account->credential_epoch,
                $now,
            );
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
                'credential_epoch' => (int) $account->credential_epoch,
            ]);
        });
    }

    public function recordAuthenticatedSession(string $accountId, string $sessionId): void
    {
        $this->database->transaction(function () use ($accountId, $sessionId): void {
            $epoch = $this->database
                ->table('accounts')
                ->where('id', $accountId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->value('credential_epoch');

            if (! is_numeric($epoch)) {
                throw new RuntimeException('Cannot record a session for an inactive account.');
            }

            $this->writeAuthenticatedSession(
                $accountId,
                $sessionId,
                (int) $epoch,
                CarbonImmutable::now(),
            );
        });
    }

    public function touchSession(string $accountId, string $sessionId): bool
    {
        return $this->database->transaction(function () use ($accountId, $sessionId): bool {
            $epoch = $this->database
                ->table('accounts')
                ->where('id', $accountId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->value('credential_epoch');

            if (! is_numeric($epoch)) {
                return false;
            }

            return $this->database
                ->table('auth_sessions')
                ->where('id', $sessionId)
                ->where('account_id', $accountId)
                ->where('credential_epoch', (int) $epoch)
                ->whereNull('revoked_at')
                ->update(['last_seen_at' => CarbonImmutable::now()]) === 1;
        });
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
        $digestCandidates = $this->accountDigestCandidates($normalized);
        $account = $this->accountForEmailDigests($digestCandidates);
        $accountId = $account?->id;
        [$digestVersion, $digest] = $account !== null
            ? [$account->email_digest_key_version, $account->email_digest]
            : $this->currentAccountDigest($normalized);

        $token = Str::random(72);
        $now = CarbonImmutable::now();
        $challengeId = (string) Str::uuid();

        $this->database->transaction(function () use (
            $accountId,
            $challengeId,
            $correlationId,
            $digest,
            $digestVersion,
            $normalized,
            $now,
            $token,
        ): void {
            $this->lockChallengeSubject('recovery', $digest, $now);
            $this->database
                ->table('verification_challenges')
                ->where('purpose', 'recovery')
                ->where('identifier_digest', $digest)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => $now,
                    'invalidated_reason' => 'resend',
                    'active_slot' => null,
                    'updated_at' => $now,
                ]);
            $this->database->table('verification_challenges')->insert([
                'id' => $challengeId,
                'purpose' => 'recovery',
                'identifier_key_version' => $digestVersion,
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

            $account = $this->database
                ->table('accounts')
                ->where('email_digest', $challenge->identifier_digest)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first(['id', 'credential_epoch']);

            if ($account === null) {
                $this->invalidateChallenge($challenge->id, 'account_unavailable', $now);
                $this->recordChallengeFailure(
                    $challenge->id,
                    'account.recovery.redeemed',
                    'account_unavailable',
                    $correlationId,
                );

                return IdentityAccessResult::failure('invalid_recovery');
            }
            $accountId = $account->id;
            $credential = $this->database
                ->table('credentials')
                ->where('account_id', $accountId)
                ->lockForUpdate()
                ->first();

            if ($credential === null) {
                $this->invalidateChallenge($challenge->id, 'credential_unavailable', $now);
                $this->recordChallengeFailure(
                    $challenge->id,
                    'account.recovery.redeemed',
                    'credential_unavailable',
                    $correlationId,
                );

                return IdentityAccessResult::failure('invalid_recovery');
            }
            $nextEpoch = (int) $account->credential_epoch + 1;

            $this->database->table('credentials')->where('account_id', $accountId)->update([
                'password_hash' => Hash::make($password),
                'algorithm' => 'current',
                'changed_at' => $now,
            ]);
            $this->database->table('accounts')->where('id', $accountId)->update([
                'credential_epoch' => $nextEpoch,
                'updated_at' => $now,
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
                    'active_slot' => null,
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
        $now = CarbonImmutable::now();

        return $this->database->transaction(function () use (
            $accountId,
            $correlationId,
            $currentPassword,
            $currentSessionId,
            $newPassword,
            $now,
        ): IdentityAccessResult {
            $account = $this->database
                ->table('accounts')
                ->where('id', $accountId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first(['id', 'credential_epoch']);
            $credential = $this->database
                ->table('credentials')
                ->where('account_id', $accountId)
                ->lockForUpdate()
                ->first();
            $currentSession = $this->database
                ->table('auth_sessions')
                ->where('id', $currentSessionId)
                ->where('account_id', $accountId)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if (
                $account === null
                || $currentSession === null
                || (int) $currentSession->credential_epoch !== (int) $account->credential_epoch
                || ! $this->passwordVerifier->verify($credential, $currentPassword)
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
            $nextEpoch = (int) $account->credential_epoch + 1;

            $this->database->table('credentials')->where('account_id', $accountId)->update([
                'password_hash' => Hash::make($newPassword),
                'algorithm' => 'current',
                'changed_at' => $now,
            ]);
            $this->database->table('accounts')->where('id', $accountId)->update([
                'credential_epoch' => $nextEpoch,
                'updated_at' => $now,
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
            $this->database
                ->table('auth_sessions')
                ->where('id', $currentSessionId)
                ->where('account_id', $accountId)
                ->update([
                    'credential_epoch' => $nextEpoch,
                    'last_seen_at' => $now,
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

            return IdentityAccessResult::success('password_changed', [
                'credential_epoch' => $nextEpoch,
            ]);
        });
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

    private function writeAuthenticatedSession(
        string $accountId,
        string $sessionId,
        int $credentialEpoch,
        CarbonImmutable $now,
    ): void {
        $this->database->table('auth_sessions')->updateOrInsert(
            ['id' => $sessionId],
            [
                'account_id' => $accountId,
                'credential_epoch' => $credentialEpoch,
                'authenticated_at' => $now,
                'last_seen_at' => $now,
                'revoked_at' => null,
                'revoked_reason' => null,
            ],
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
                'active_slot' => null,
                'updated_at' => $now,
            ]);
    }

    private function lockChallengeSubject(
        string $purpose,
        string $identifierDigest,
        CarbonImmutable $now,
    ): void {
        $lockKey = hash('sha256', "{$purpose}:{$identifierDigest}");
        $this->database->table('verification_subject_locks')->insertOrIgnore([
            'lock_key' => $lockKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->database
            ->table('verification_subject_locks')
            ->where('lock_key', $lockKey)
            ->lockForUpdate()
            ->first();
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

    /** @return array{string, string} */
    private function currentAccountDigest(string $email): array
    {
        $version = (string) config('identity-access.account_lookup_key_version');
        $keys = $this->accountLookupKeys();

        if (! array_key_exists($version, $keys)) {
            throw new RuntimeException('Current account lookup key is not configured.');
        }

        return [$version, hash_hmac('sha256', "email:{$email}", $keys[$version])];
    }

    /** @return array<string, string> */
    private function accountDigestCandidates(string $email): array
    {
        $digests = [];

        foreach ($this->accountLookupKeys() as $version => $key) {
            $digests[$version] = hash_hmac('sha256', "email:{$email}", $key);
        }

        return $digests;
    }

    /** @return array<string, string> */
    private function accountLookupKeys(): array
    {
        $keys = config('identity-access.account_lookup_keys');

        if (! is_array($keys) || $keys === []) {
            throw new RuntimeException('Account lookup keys are not configured.');
        }

        return $keys;
    }

    /** @param array<string, string> $digests */
    private function accountExistsForDigests(array $digests): bool
    {
        return $this->applyDigestCandidates(
            $this->database->table('accounts'),
            $digests,
            'email_digest_key_version',
            'email_digest',
        )->exists();
    }

    /** @param array<string, string> $digests */
    private function accountForEmailDigests(array $digests): ?object
    {
        return $this->applyDigestCandidates(
            $this->database->table('accounts')->where('status', 'active'),
            $digests,
            'email_digest_key_version',
            'email_digest',
        )->first(['id', 'email_digest_key_version', 'email_digest']);
    }

    /**
     * @param  array<string, string>  $digests
     */
    private function applyDigestCandidates(
        Builder $query,
        array $digests,
        string $versionColumn = 'identifier_key_version',
        string $digestColumn = 'identifier_digest',
    ): Builder {
        return $query->where(function ($candidates) use (
            $digestColumn,
            $digests,
            $versionColumn,
        ): void {
            foreach ($digests as $version => $digest) {
                $candidates->orWhere(function ($candidate) use (
                    $digest,
                    $digestColumn,
                    $version,
                    $versionColumn,
                ): void {
                    $candidate
                        ->where($versionColumn, $version)
                        ->where($digestColumn, $digest);
                });
            }
        });
    }
}
