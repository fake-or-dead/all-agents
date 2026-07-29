<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ArchitectureBoundariesTest extends TestCase
{
    public function test_every_module_requires_an_explicit_rule(): void
    {
        $root = $this->fixtureRoot();
        mkdir("{$root}/Unowned/Infrastructure", 0777, true);
        file_put_contents("{$root}/Unowned/Infrastructure/Adapter.php", '<?php');

        self::assertNotSame(0, $this->check($root));
    }

    public function test_course_catalog_cannot_bypass_application_or_document_ports(): void
    {
        $root = $this->fixtureRoot();
        foreach (['ApplicationWorkflow', 'Audit', 'CourseCatalog', 'DocumentsConsent', 'IdentityAccess', 'PlatformOperations', 'ReferenceData'] as $module) {
            mkdir("{$root}/{$module}/Infrastructure", 0777, true);
            file_put_contents("{$root}/{$module}/Infrastructure/Placeholder.php", '<?php');
        }
        file_put_contents(
            "{$root}/CourseCatalog/Infrastructure/Forbidden.php",
            '<?php use App\\Modules\\DocumentsConsent\\Infrastructure\\Persistence\\DatabasePublicCourseDocuments;',
        );

        self::assertNotSame(0, $this->check($root));
    }

    private function fixtureRoot(): string
    {
        $root = sys_get_temp_dir().'/tapoda-architecture-'.bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        return $root;
    }

    private function check(string $root): int
    {
        $command = escapeshellarg(PHP_BINARY).' '
            .escapeshellarg(dirname(__DIR__, 2).'/tools/architecture-check.php').' '
            .escapeshellarg($root).' 2>&1';
        exec($command, $output, $exitCode);

        return $exitCode;
    }
}
