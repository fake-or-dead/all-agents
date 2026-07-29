<?php

namespace Tests\Unit\People;

use App\Modules\People\Infrastructure\ApplicationContextEvidenceCipher;
use Tests\TestCase;

final class ApplicationContextEvidenceCipherTest extends TestCase
{
    public function test_identical_canonical_facts_use_randomized_authenticated_encryption(): void
    {
        $cipher = $this->app->make(ApplicationContextEvidenceCipher::class);
        $facts = [
            'personId' => '20000000-0000-4000-8000-000000000001',
            'version' => 1,
            'birthDate' => '1990-06-15',
            'approvedCategory' => 'female',
            'layMonasticCategory' => 'lay',
            'provenance' => 'cipher-test',
            'effectiveAt' => '2026-07-29T00:00:00+00:00',
            'staleAt' => null,
        ];

        $first = $cipher->encrypt($facts);
        $second = $cipher->encrypt($facts);

        self::assertNotSame($first, $second);
        self::assertSame(
            $facts,
            $cipher->decrypt($cipher->currentKeyVersion(), $first),
        );
        self::assertSame(
            $facts,
            $cipher->decrypt($cipher->currentKeyVersion(), $second),
        );
    }
}
