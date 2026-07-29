<?php

namespace Tests\Feature\IdentityAccess;

use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\IdentitySecurityConfiguration;
use App\Modules\IdentityAccess\Infrastructure\Verification\DeterministicFakeVerificationGateway;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

final class RegistrationJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const CONSENT_VERSION_ID = '10000000-0000-4000-8000-000000000002';

    public function test_email_verification_is_neutral_and_only_the_latest_challenge_can_be_redeemed(): void
    {
        $payload = ['email' => 'person@example.test'];

        $this->postJson('/auth/verification/request', $payload)
            ->assertAccepted()
            ->assertExactJson([
                'message' => 'หากข้อมูลถูกต้อง ระบบได้ส่งวิธียืนยันให้แล้ว',
            ]);
        $firstChallengeId = $this->getConnection()
            ->table('verification_challenges')
            ->value('id');

        $this->postJson('/auth/verification/request', $payload)
            ->assertAccepted()
            ->assertExactJson([
                'message' => 'หากข้อมูลถูกต้อง ระบบได้ส่งวิธียืนยันให้แล้ว',
            ]);

        $this->assertDatabaseHas('verification_challenges', [
            'id' => $firstChallengeId,
            'invalidated_reason' => 'resend',
        ]);

        $response = $this->postJson('/auth/verification/verify', [
            ...$payload,
            'code' => '246810',
        ])->assertOk();

        $response->assertJsonStructure(['registration_token', 'expires_at']);
        $this->assertNotEmpty($response->json('registration_token'));
    }

    public function test_registration_atomically_creates_account_person_credential_and_versioned_consent(): void
    {
        $token = $this->verifiedRegistrationToken('owner@example.test');

        $this->postJson('/signup', [
            'email' => 'Owner@Example.Test',
            'registration_token' => $token,
            'identity_type' => 'personal_id',
            'identity_number' => '1234567890123',
            'given_name' => 'สมชาย',
            'family_name' => 'ใจดี',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'consent_accepted' => true,
            'consent_version' => self::CONSENT_VERSION_ID,
        ])->assertCreated()->assertJsonStructure(['redirect']);

        $this->assertAuthenticated();
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('person_identifiers', 1);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('credentials', 1);
        $this->assertDatabaseHas('consent_acceptances', [
            'document_version_id' => self::CONSENT_VERSION_ID,
            'document_checksum' => hash(
                'sha256',
                'เอกสารตัวอย่างสำหรับการพัฒนาภายใน: Tapoda จะใช้ข้อมูลบัญชีและข้อมูลประจำตัวเพื่อยืนยันตัวบุคคล จัดการการสมัคร และรักษาความปลอดภัยของบัญชี เอกสารนี้ไม่ใช่ข้อความกฎหมายสำหรับระบบจริง',
            ),
            'context' => 'registration',
        ]);
        $this->assertDatabaseMissing('verification_challenges', [
            'purpose' => 'registration',
            'consumed_at' => null,
        ]);

        $databaseText = json_encode([
            DB::table('people')->first(),
            DB::table('person_identifiers')->first(),
            DB::table('accounts')->first(),
            DB::table('audit_events')->get()->all(),
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('1234567890123', $databaseText);
        $this->assertStringNotContainsString('owner@example.test', mb_strtolower($databaseText));
    }

    #[Group('service-integration')]
    public function test_recovery_epoch_bump_rejects_a_delayed_registration_session_on_postgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            if (getenv('REQUIRE_REAL_SERVICES') === '1') {
                self::fail('REQUIRE_REAL_SERVICES=1 requires PostgreSQL for the registration session epoch proof.');
            }

            self::markTestSkipped('The registration session epoch proof is PostgreSQL-specific.');
        }
        $email = 'delayed-session@example.test';
        $registrationToken = $this->verifiedRegistrationToken($email);
        $workflow = $this->app->make(IdentityAccessWorkflow::class);

        // This commits registration first, exactly before the controller's
        // subsequent session-ledger write / framework Auth::login sequence.
        $registration = $workflow->register([
            'email' => $email,
            'registration_token' => $registrationToken,
            'identity_type' => 'passport',
            'identity_number' => 'DELAY123',
            'given_name' => 'Late',
            'family_name' => 'Session',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'consent_accepted' => true,
            'consent_version' => self::CONSENT_VERSION_ID,
        ], (string) Str::uuid());
        $this->assertTrue($registration->successful);
        $accountId = (string) $registration->data['account_id'];
        $registrationEpoch = (int) $registration->data['credential_epoch'];
        $this->assertSame(1, $registrationEpoch);

        // A recovery commits a newer credential epoch while registration's
        // framework session issuance is deliberately delayed.
        $this->postJson('/forgot', ['email' => $email])->assertAccepted();
        $path = $this->app
            ->make(DeterministicFakeVerificationGateway::class)
            ->latestRecoveryPathFor($email);
        $this->assertNotNull($path);
        $this->postJson('/recover/'.basename($path), [
            'password' => 'recovered-password-456',
            'password_confirmation' => 'recovered-password-456',
        ])->assertOk();
        $this->assertSame(2, (int) DB::table('accounts')
            ->where('id', $accountId)
            ->value('credential_epoch'));

        // recordAuthenticatedSession() locks the PostgreSQL account row and
        // rechecks the expected epoch inside that transaction. It fails before
        // the controller can execute Auth::login().
        try {
            $workflow->recordAuthenticatedSession(
                $accountId,
                'delayed-registration-session',
                $registrationEpoch,
            );
            $this->fail('Delayed registration session must be rejected after recovery.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Credential generation changed before session issuance.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseMissing('auth_sessions', [
            'id' => 'delayed-registration-session',
        ]);
        $this->assertGuest();
    }

    #[Group('service-integration')]
    public function test_previous_account_and_people_keys_preserve_v0_ownership_after_v1_rotation_on_postgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            if (getenv('REQUIRE_REAL_SERVICES') === '1') {
                self::fail('REQUIRE_REAL_SERVICES=1 requires PostgreSQL for the lookup-key rotation proof.');
            }

            self::markTestSkipped('The lookup-key rotation proof is PostgreSQL-specific.');
        }

        $peopleV0 = str_repeat('p', 64);
        $peopleV1 = str_repeat('q', 64);
        $accountV0 = str_repeat('a', 64);
        $accountV1 = str_repeat('b', 64);
        config()->set([
            'people.identifier_lookup_key_version' => 'v0',
            'people.identifier_lookup_previous_version' => '',
            'people.identifier_lookup_previous_key' => null,
            'people.identifier_lookup_keys' => ['v0' => $peopleV0],
            'identity-access.account_lookup_key_version' => 'v0',
            'identity-access.account_lookup_previous_version' => '',
            'identity-access.account_lookup_previous_key' => null,
            'identity-access.account_lookup_keys' => ['v0' => $accountV0],
        ]);
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();

        $email = 'rotation-owner@example.test';
        $identityNumber = 'ROTATE123';
        $token = $this->verifiedRegistrationToken($email);
        $payload = [
            'email' => $email,
            'registration_token' => $token,
            'identity_type' => 'passport',
            'identity_number' => $identityNumber,
            'given_name' => 'Rotation',
            'family_name' => 'Owner',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'consent_accepted' => true,
            'consent_version' => self::CONSENT_VERSION_ID,
        ];
        $this->postJson('/signup', $payload)->assertCreated();
        $accountId = (string) DB::table('accounts')->value('id');
        $personId = (string) DB::table('people')->value('id');
        $this->assertDatabaseHas('accounts', [
            'id' => $accountId,
            'email_digest_key_version' => 'v0',
        ]);
        $this->assertDatabaseHas('person_identifiers', [
            'person_id' => $personId,
            'lookup_key_version' => 'v0',
        ]);

        config()->set([
            'people.identifier_lookup_key_version' => 'v1',
            'people.identifier_lookup_previous_version' => 'v0',
            'people.identifier_lookup_previous_key' => $peopleV0,
            'people.identifier_lookup_keys' => [
                'v1' => $peopleV1,
                'v0' => $peopleV0,
            ],
            'identity-access.account_lookup_key_version' => 'v1',
            'identity-access.account_lookup_previous_version' => 'v0',
            'identity-access.account_lookup_previous_key' => $accountV0,
            'identity-access.account_lookup_keys' => [
                'v1' => $accountV1,
                'v0' => $accountV0,
            ],
        ]);
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();

        $identity = IdentityClaim::fromInput('passport', $identityNumber);
        $this->assertSame(
            $personId,
            $this->app->make(PersonIdentityDirectory::class)->personIdForIdentity($identity),
        );
        $this->postJson('/forgot', ['email' => $email])->assertAccepted();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'account.recovery.requested',
            'resource_id' => $accountId,
            'outcome' => 'accepted',
        ]);

        $duplicateEmailToken = $this->verifiedRegistrationToken($email);
        $this->postJson('/signup', [
            ...$payload,
            'registration_token' => $duplicateEmailToken,
            'identity_number' => 'ROTATE456',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('people', 1);

        $duplicateIdentityToken = $this->verifiedRegistrationToken('second-rotation@example.test');
        $this->postJson('/signup', [
            ...$payload,
            'email' => 'second-rotation@example.test',
            'registration_token' => $duplicateIdentityToken,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('person_identifiers', 1);
    }

    public function test_registration_rejects_stale_consent_without_partial_account_creation(): void
    {
        $token = $this->verifiedRegistrationToken('consent@example.test');

        $this->postJson('/signup', [
            'email' => 'consent@example.test',
            'registration_token' => $token,
            'identity_type' => 'passport',
            'identity_number' => 'AB123456',
            'given_name' => 'มาลี',
            'family_name' => 'ใจงาม',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'consent_accepted' => true,
            'consent_version' => '20000000-0000-4000-8000-000000000002',
        ])->assertStatus(422);

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('person_identifiers', 0);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('credentials', 0);
        $this->assertDatabaseCount('consent_acceptances', 0);
    }

    public function test_verification_enforces_attempts_expiry_and_one_use_registration_proof(): void
    {
        $this->postJson('/auth/verification/request', [
            'email' => 'attempts@example.test',
        ])->assertAccepted();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/auth/verification/verify', [
                'email' => 'attempts@example.test',
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $this->postJson('/auth/verification/verify', [
            'email' => 'attempts@example.test',
            'code' => '246810',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('verification_challenges', [
            'purpose' => 'registration',
            'attempts_remaining' => 0,
            'invalidated_reason' => 'attempts_exhausted',
        ]);

        $this->postJson('/auth/verification/request', [
            'email' => 'expired@example.test',
        ])->assertAccepted();
        DB::table('verification_challenges')
            ->where('purpose', 'registration')
            ->whereNull('invalidated_at')
            ->update(['expires_at' => CarbonImmutable::now()->subSecond()]);
        $this->postJson('/auth/verification/verify', [
            'email' => 'expired@example.test',
            'code' => '246810',
        ])->assertUnprocessable();

        $token = $this->verifiedRegistrationToken('once@example.test');
        $payload = [
            'email' => 'once@example.test',
            'registration_token' => $token,
            'identity_type' => 'passport',
            'identity_number' => 'ZX987654',
            'given_name' => 'หนึ่ง',
            'family_name' => 'ครั้ง',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'consent_accepted' => true,
            'consent_version' => self::CONSENT_VERSION_ID,
        ];
        $this->postJson('/signup', $payload)->assertCreated();
        $this->postJson('/signup', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'account.registration',
            'outcome' => 'denied',
        ]);
    }

    public function test_existing_person_requires_one_use_people_owned_link_proof(): void
    {
        $people = $this->app->make(PersonIdentityDirectory::class);
        $identity = IdentityClaim::fromInput('passport', 'EX123456');
        $personId = $people->create($identity, 'Existing', 'Person');
        $registrationToken = $this->verifiedRegistrationToken('linked@example.test');
        $payload = [
            'email' => 'linked@example.test',
            'registration_token' => $registrationToken,
            'identity_type' => 'passport',
            'identity_number' => 'EX123456',
            'given_name' => 'Changed',
            'family_name' => 'Name',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'consent_accepted' => true,
            'consent_version' => self::CONSENT_VERSION_ID,
        ];

        $this->postJson('/signup', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('accounts', 0);

        $proof = $people->approveAccountLink(
            $identity,
            CarbonImmutable::now()->addMinutes(15),
        );
        $this->assertNotNull($proof);
        $this->postJson('/signup', [
            ...$payload,
            'person_link_token' => $proof,
        ])->assertCreated();

        $this->assertDatabaseHas('accounts', ['person_id' => $personId]);
        $this->assertDatabaseHas('person_account_link_proofs', [
            'person_id' => $personId,
        ]);
        $this->assertNotNull(
            DB::table('person_account_link_proofs')->value('consumed_at'),
        );

        $secondToken = $this->verifiedRegistrationToken('second-linked@example.test');
        $this->postJson('/signup', [
            ...$payload,
            'email' => 'second-linked@example.test',
            'registration_token' => $secondToken,
            'person_link_token' => $proof,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('accounts', 1);
    }

    private function verifiedRegistrationToken(string $email): string
    {
        $this->postJson('/auth/verification/request', ['email' => $email])->assertAccepted();

        return $this->postJson('/auth/verification/verify', [
            'email' => $email,
            'code' => '246810',
        ])->assertOk()->json('registration_token');
    }
}
