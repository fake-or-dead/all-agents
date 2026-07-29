<?php

namespace Tests\Unit;

use Tests\TestCase;

final class ArchitectureCheckTest extends TestCase
{
    public function test_direct_cross_module_person_account_link_proof_access_fails(): void
    {
        $fixture = base_path('tests/Fixtures/Architecture/IdentityAccess/DirectPeopleProofAccess.php');
        $command = 'ARCHITECTURE_CHECK_FIXTURE='.escapeshellarg($fixture).' php tools/architecture-check.php 2>&1';
        exec($command, $output, $exitCode);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString(
            'accesses People-owned table person_account_link_proofs',
            implode("\n", $output),
        );
    }
}
