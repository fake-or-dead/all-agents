<?php

namespace Tests\Feature\IdentityAccess;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RegistrationJourneyTest extends TestCase
{
    use RefreshDatabase;

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
            'consent_version' => 'registration-consent-v1',
        ])->assertCreated()->assertJsonStructure(['redirect']);

        $this->assertAuthenticated();
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('person_identifiers', 1);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('credentials', 1);
        $this->assertDatabaseHas('consent_acceptances', [
            'document_version' => 'registration-consent-v1',
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
            'consent_version' => 'registration-consent-old',
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
            'consent_version' => 'registration-consent-v1',
        ];
        $this->postJson('/signup', $payload)->assertCreated();
        $this->postJson('/signup', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'account.registration',
            'outcome' => 'denied',
        ]);
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
