<?php

namespace Tests\Feature\People;

use App\Modules\People\Contracts\ApplicationContextEvidenceResolver;
use App\Modules\People\Infrastructure\ApplicationContextEvidenceCipher;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalApplicationContextEvidenceSeeder;
use Database\Seeders\LocalIdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ApplicationContextEvidenceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_person_without_application_context_evidence_resolves_as_missing(): void
    {
        $personId = (string) Str::uuid();
        DB::table('people')->insert([
            'id' => $personId,
            'given_name' => 'ไม่มี',
            'family_name' => 'หลักฐาน',
            'created_at' => CarbonImmutable::parse('2026-07-29T00:00:00Z'),
            'updated_at' => CarbonImmutable::parse('2026-07-29T00:00:00Z'),
        ]);

        $resolution = $this->app
            ->make(ApplicationContextEvidenceResolver::class)
            ->resolveForPerson(
                $personId,
                CarbonImmutable::parse('2026-08-01T00:00:00Z'),
            );

        self::assertSame('missing', $resolution->status->value);
        self::assertSame('no-evidence', $resolution->reason?->value);
        self::assertNull($resolution->evidence);
    }

    public function test_local_fixture_resolves_authenticated_canonical_facts_at_effective_boundary(): void
    {
        $this->seed(LocalIdentityAccessSeeder::class);
        $this->seed(LocalApplicationContextEvidenceSeeder::class);

        $resolution = $this->app
            ->make(ApplicationContextEvidenceResolver::class)
            ->resolveForPerson(
                '20000000-0000-4000-8000-000000000001',
                CarbonImmutable::parse('2026-07-29T00:00:00Z'),
            );

        self::assertSame('resolved', $resolution->status->value);
        self::assertNull($resolution->reason);
        self::assertNotNull($resolution->evidence);
        self::assertSame(
            '20000000-0000-4000-8000-000000000001',
            $resolution->evidence->personId,
        );
        self::assertSame(1, $resolution->evidence->version);
        self::assertSame('1990-06-15', $resolution->evidence->birthDate->toDateString());
        self::assertSame('female', $resolution->evidence->approvedCategory);
        self::assertSame('lay', $resolution->evidence->layMonasticCategory);
        self::assertSame('local-deterministic-fixture', $resolution->evidence->provenance);
        self::assertSame(
            '2026-07-29T00:00:00+00:00',
            $resolution->evidence->effectiveAt->toIso8601String(),
        );
        self::assertSame(
            '2027-07-29T00:00:00+00:00',
            $resolution->evidence->staleAt?->toIso8601String(),
        );
    }

    public function test_resolution_uses_the_highest_effective_version_and_exact_stale_boundary(): void
    {
        $personId = $this->createPerson();
        $this->insertEvidence(
            personId: $personId,
            version: 1,
            birthDate: '1990-01-01',
            effectiveAt: '2026-01-01T00:00:00+00:00',
            staleAt: null,
        );
        $this->insertEvidence(
            personId: $personId,
            version: 2,
            birthDate: '1991-02-02',
            effectiveAt: '2026-06-01T00:00:00+00:00',
            staleAt: '2026-09-01T00:00:00+00:00',
        );
        $resolver = $this->app->make(ApplicationContextEvidenceResolver::class);

        $before = $resolver->resolveForPerson(
            $personId,
            CarbonImmutable::parse('2025-12-31T23:59:59Z'),
        );
        self::assertSame('missing', $before->status->value);
        self::assertSame('no-evidence', $before->reason?->value);

        $first = $resolver->resolveForPerson(
            $personId,
            CarbonImmutable::parse('2026-05-31T23:59:59Z'),
        );
        self::assertSame('resolved', $first->status->value);
        self::assertSame(1, $first->evidence?->version);

        $second = $resolver->resolveForPerson(
            $personId,
            CarbonImmutable::parse('2026-06-01T00:00:00Z'),
        );
        self::assertSame('resolved', $second->status->value);
        self::assertSame(2, $second->evidence?->version);

        $stale = $resolver->resolveForPerson(
            $personId,
            CarbonImmutable::parse('2026-09-01T00:00:00Z'),
        );
        self::assertSame('stale', $stale->status->value);
        self::assertSame('evidence-expired', $stale->reason?->value);
        self::assertNull($stale->evidence);
    }

    public function test_tampered_authenticated_facts_fail_closed_with_a_bounded_reason(): void
    {
        $personId = $this->createPerson();
        DB::table('person_application_context_evidence')->insert([
            'id' => (string) Str::uuid(),
            'person_id' => $personId,
            'version' => 1,
            'facts_encrypted' => 'not-a-valid-aead-payload',
            'encryption_key_version' => 'v1',
            'effective_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
            'stale_at' => null,
            'created_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
        ]);

        $resolution = $this->app
            ->make(ApplicationContextEvidenceResolver::class)
            ->resolveForPerson(
                $personId,
                CarbonImmutable::parse('2026-02-01T00:00:00Z'),
            );

        self::assertSame('stale', $resolution->status->value);
        self::assertSame('unreadable-evidence', $resolution->reason?->value);
        self::assertNull($resolution->evidence);
    }

    public function test_noncanonical_authenticated_facts_fail_closed(): void
    {
        $personId = $this->createPerson();
        $cipher = $this->app->make(ApplicationContextEvidenceCipher::class);
        $facts = [
            'personId' => $personId,
            'version' => 1,
            'birthDate' => '1990-02-30',
            'approvedCategory' => 'female',
            'layMonasticCategory' => 'lay',
            'provenance' => 'metadata-test',
            'effectiveAt' => '2026-01-01T00:00:00+00:00',
            'staleAt' => null,
        ];
        DB::table('person_application_context_evidence')->insert([
            'id' => (string) Str::uuid(),
            'person_id' => $personId,
            'version' => 1,
            'facts_encrypted' => $cipher->encrypt($facts),
            'encryption_key_version' => $cipher->currentKeyVersion(),
            'effective_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
            'stale_at' => null,
            'created_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
        ]);

        $resolution = $this->app
            ->make(ApplicationContextEvidenceResolver::class)
            ->resolveForPerson(
                $personId,
                CarbonImmutable::parse('2026-02-01T00:00:00Z'),
            );

        self::assertSame('stale', $resolution->status->value);
        self::assertSame('invalid-evidence', $resolution->reason?->value);
        self::assertNull($resolution->evidence);
    }

    public function test_missing_configured_key_fails_closed_instead_of_returning_facts(): void
    {
        $personId = $this->createPerson();
        $this->insertEvidence(
            personId: $personId,
            version: 1,
            birthDate: '1990-01-01',
            effectiveAt: '2026-01-01T00:00:00Z',
            staleAt: null,
        );
        config()->set('people.context_evidence_keys', []);

        $resolution = $this->app
            ->make(ApplicationContextEvidenceResolver::class)
            ->resolveForPerson(
                $personId,
                CarbonImmutable::parse('2026-02-01T00:00:00Z'),
            );

        self::assertSame('stale', $resolution->status->value);
        self::assertSame('unreadable-evidence', $resolution->reason?->value);
        self::assertNull($resolution->evidence);
    }

    private function createPerson(): string
    {
        $personId = (string) Str::uuid();
        DB::table('people')->insert([
            'id' => $personId,
            'given_name' => 'ทดสอบ',
            'family_name' => 'หลักฐาน',
            'created_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
            'updated_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
        ]);

        return $personId;
    }

    private function insertEvidence(
        string $personId,
        int $version,
        string $birthDate,
        string $effectiveAt,
        ?string $staleAt,
    ): string {
        $cipher = $this->app->make(ApplicationContextEvidenceCipher::class);
        $facts = [
            'personId' => $personId,
            'version' => $version,
            'birthDate' => $birthDate,
            'approvedCategory' => 'female',
            'layMonasticCategory' => 'lay',
            'provenance' => 'resolver-test',
            'effectiveAt' => CarbonImmutable::parse($effectiveAt)->utc()->toIso8601String(),
            'staleAt' => $staleAt === null
                ? null
                : CarbonImmutable::parse($staleAt)->utc()->toIso8601String(),
        ];
        $ciphertext = $cipher->encrypt($facts);

        DB::table('person_application_context_evidence')->insert([
            'id' => (string) Str::uuid(),
            'person_id' => $personId,
            'version' => $version,
            'facts_encrypted' => $ciphertext,
            'encryption_key_version' => $cipher->currentKeyVersion(),
            'effective_at' => CarbonImmutable::parse($effectiveAt),
            'stale_at' => $staleAt === null ? null : CarbonImmutable::parse($staleAt),
            'created_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
        ]);

        return $ciphertext;
    }
}
