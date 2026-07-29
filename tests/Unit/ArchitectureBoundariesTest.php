<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ArchitectureBoundariesTest extends TestCase
{
    public function test_every_module_requires_an_explicit_rule(): void
    {
        $root = $this->fixtureRoot();
        $this->createExpectedModules($root);
        mkdir("{$root}/Unowned/Infrastructure", 0777, true);
        file_put_contents("{$root}/Unowned/Infrastructure/Adapter.php", '<?php');

        $result = $this->check($root);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'Unowned is missing an explicit architecture rule',
            $result['output'],
        );
    }

    public function test_course_catalog_cannot_import_application_workflow_infrastructure(): void
    {
        $root = $this->fixtureRoot();
        $this->createExpectedModules($root);
        file_put_contents(
            "{$root}/CourseCatalog/Infrastructure/Forbidden.php",
            '<?php use App\\Modules\\ApplicationWorkflow\\Infrastructure\\Persistence\\DatabaseApplicationFacts;',
        );

        $result = $this->check($root);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'bypasses the ApplicationWorkflow public port',
            $result['output'],
        );
    }

    public function test_course_catalog_cannot_import_documents_consent_infrastructure(): void
    {
        $root = $this->fixtureRoot();
        $this->createExpectedModules($root);
        file_put_contents(
            "{$root}/CourseCatalog/Infrastructure/Forbidden.php",
            '<?php use App\\Modules\\DocumentsConsent\\Infrastructure\\Persistence\\DatabasePublicCourseDocuments;',
        );

        $result = $this->check($root);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'bypasses the DocumentsConsent public port',
            $result['output'],
        );
    }

    public function test_course_catalog_cannot_read_application_workflow_owned_table(): void
    {
        $root = $this->fixtureRoot();
        $this->createExpectedModules($root);
        file_put_contents(
            "{$root}/CourseCatalog/Infrastructure/Forbidden.php",
            "<?php DB::table('application_workflow_facts')->first();",
        );

        $result = $this->check($root);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'accesses ApplicationWorkflow-owned table application_workflow_facts',
            $result['output'],
        );
    }

    public function test_course_catalog_cannot_read_documents_consent_owned_table(): void
    {
        $root = $this->fixtureRoot();
        $this->createExpectedModules($root);
        file_put_contents(
            "{$root}/CourseCatalog/Infrastructure/Forbidden.php",
            "<?php DB::table('document_publication_projections')->first();",
        );

        $result = $this->check($root);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'accesses DocumentsConsent-owned table document_publication_projections',
            $result['output'],
        );
    }

    public function test_application_workflow_cannot_read_course_catalog_owned_table(): void
    {
        $root = $this->fixtureRoot();
        $this->createExpectedModules($root);
        file_put_contents(
            "{$root}/ApplicationWorkflow/Infrastructure/Forbidden.php",
            "<?php DB::table('course_sessions')->first();",
        );

        $result = $this->check($root);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'accesses CourseCatalog-owned table course_sessions',
            $result['output'],
        );
    }

    public function test_course_catalog_cannot_read_reference_data_owned_table(): void
    {
        $root = $this->fixtureRoot();
        $this->createExpectedModules($root);
        file_put_contents(
            "{$root}/CourseCatalog/Infrastructure/Forbidden.php",
            "<?php DB::table('provinces')->first();",
        );

        $result = $this->check($root);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString(
            'accesses ReferenceData-owned table provinces',
            $result['output'],
        );
    }

    private function fixtureRoot(): string
    {
        $root = sys_get_temp_dir().'/tapoda-architecture-'.bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        return $root;
    }

    private function createExpectedModules(string $root): void
    {
        foreach ([
            'ApplicationWorkflow',
            'Audit',
            'CourseCatalog',
            'DocumentsConsent',
            'IdentityAccess',
            'People',
            'PlatformOperations',
            'ReferenceData',
        ] as $module) {
            mkdir("{$root}/{$module}/Infrastructure", 0777, true);
            file_put_contents("{$root}/{$module}/Infrastructure/Placeholder.php", '<?php');
        }
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function check(string $root): array
    {
        $command = escapeshellarg(PHP_BINARY).' '
            .escapeshellarg(dirname(__DIR__, 2).'/tools/architecture-check.php').' '
            .escapeshellarg($root).' 2>&1';
        exec($command, $output, $exitCode);

        return [
            'exitCode' => $exitCode,
            'output' => implode("\n", $output),
        ];
    }
}
