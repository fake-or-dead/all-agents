<?php

namespace Database\Seeders;

use App\Modules\People\Infrastructure\ApplicationContextEvidenceCipher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalApplicationContextEvidenceSeeder extends Seeder
{
    private const string EvidenceId = '20000000-0000-4000-8000-000000000004';

    private const string PersonId = '20000000-0000-4000-8000-000000000001';

    public function run(ApplicationContextEvidenceCipher $cipher): void
    {
        if (
            ! app()->environment(['local', 'testing'])
            || config('identity-access.verification_adapter') !== 'deterministic-fake'
        ) {
            throw new RuntimeException(
                'Application context evidence fixtures require local deterministic-fake mode.',
            );
        }

        if (! DB::table('people')->where('id', self::PersonId)->exists()) {
            throw new RuntimeException('Application context evidence fixture person is missing.');
        }

        $facts = [
            'personId' => self::PersonId,
            'version' => 1,
            'birthDate' => '1990-06-15',
            'approvedCategory' => 'female',
            'layMonasticCategory' => 'lay',
            'provenance' => 'local-deterministic-fixture',
            'effectiveAt' => '2026-07-29T00:00:00+00:00',
            'staleAt' => '2027-07-29T00:00:00+00:00',
        ];
        $existing = DB::table('person_application_context_evidence')
            ->where('id', self::EvidenceId)
            ->first();

        if ($existing !== null) {
            $storedFacts = $cipher->decrypt(
                (string) $existing->encryption_key_version,
                (string) $existing->facts_encrypted,
            );
            if (
                $storedFacts !== $facts
                || (string) $existing->person_id !== self::PersonId
                || (int) $existing->version !== 1
                || ! CarbonImmutable::parse((string) $existing->effective_at)
                    ->equalTo(CarbonImmutable::parse($facts['effectiveAt']))
                || ! CarbonImmutable::parse((string) $existing->stale_at)
                    ->equalTo(CarbonImmutable::parse($facts['staleAt']))
            ) {
                throw new RuntimeException(
                    'Existing application context evidence fixture does not match.',
                );
            }

            return;
        }

        DB::table('person_application_context_evidence')->insert([
            'id' => self::EvidenceId,
            'person_id' => self::PersonId,
            'version' => 1,
            'facts_encrypted' => $cipher->encrypt($facts),
            'encryption_key_version' => $cipher->currentKeyVersion(),
            'effective_at' => CarbonImmutable::parse($facts['effectiveAt']),
            'stale_at' => CarbonImmutable::parse($facts['staleAt']),
            'created_at' => CarbonImmutable::parse('2026-07-29T00:00:00Z'),
        ]);
    }
}
