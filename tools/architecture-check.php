<?php

declare(strict_types=1);

$moduleRoot = $argv[1] ?? dirname(__DIR__).'/app/Modules';
$allowedDependencies = [
    'ApplicationWorkflow' => [],
    'Audit' => [],
    'CourseCatalog' => ['ApplicationWorkflow', 'DocumentsConsent'],
    'DocumentsConsent' => [],
    'FormEngine' => [],
    'IdentityAccess' => ['DocumentsConsent', 'People'],
    'People' => ['ReferenceData'],
    'PlatformOperations' => ['Audit', 'IdentityAccess'],
    'ReferenceData' => [],
];
$ownedTables = [
    'ApplicationWorkflow' => [
        'application_workflow_facts',
    ],
    'CourseCatalog' => [
        'course_types',
        'centers',
        'courses',
        'course_sessions',
        'teachers',
        'course_session_teachers',
        'course_capacity_rules',
    ],
    'DocumentsConsent' => [
        'consent_documents',
        'consent_document_versions',
        'consent_acceptances',
        'document_publication_projections',
    ],
    'FormEngine' => [
        'form_definitions',
        'form_versions',
        'form_sections',
        'form_fields',
        'form_options',
        'form_assignments',
        'form_publication_events',
    ],
    'IdentityAccess' => [
        'accounts',
        'credentials',
        'verification_challenges',
        'verification_subject_locks',
        'auth_sessions',
        'local_verification_deliveries',
    ],
    'People' => [
        'people',
        'person_identifiers',
        'person_account_link_proofs',
        'person_contacts',
        'person_addresses',
        'person_training_experiences',
    ],
    'ReferenceData' => ['provinces', 'amphoes', 'tambons'],
];
$violations = [];
$modules = [];

foreach (new DirectoryIterator($moduleRoot) as $directory) {
    if ($directory->isDir() && ! $directory->isDot()) {
        $modules[] = $directory->getFilename();
    }
}
sort($modules);

foreach ($modules as $module) {
    if (! array_key_exists($module, $allowedDependencies)) {
        $violations[] = "{$module} is missing an explicit architecture rule";

        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator("{$moduleRoot}/{$module}", FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            $violations[] = "Could not read {$file->getPathname()}";

            continue;
        }

        preg_match_all(
            '/App\\\\Modules\\\\([A-Za-z0-9]+)\\\\([A-Za-z0-9\\\\]+)/',
            $contents,
            $matches,
            PREG_SET_ORDER,
        );
        foreach ($matches as $match) {
            $dependency = $match[1];
            if ($dependency === $module) {
                continue;
            }

            if (! in_array($dependency, $allowedDependencies[$module], true)) {
                $violations[] = "{$file->getPathname()} imports forbidden module {$dependency}";

                continue;
            }

            if (
                ! str_starts_with($match[2], 'Contracts\\')
                && ! str_starts_with($match[2], 'Data\\')
            ) {
                $violations[] = "{$file->getPathname()} bypasses the {$dependency} public port";
            }
        }

        if (
            preg_match('#/(Contracts|Data)/#', $file->getPathname()) === 1
            && preg_match(
                '/Illuminate\\\\(Database|Support\\\\Facades)|ConnectionInterface|Query\\\\Builder/',
                $contents,
            ) === 1
        ) {
            $violations[] = "{$file->getPathname()} couples a module contract or data type to persistence";
        }

        foreach ($ownedTables as $owner => $tables) {
            if ($module === $owner) {
                continue;
            }

            foreach ($tables as $table) {
                if (
                    preg_match(
                        "/(?:table|from|join)\\(\\s*['\"]".preg_quote($table, '/')."['\"]/",
                        $contents,
                    ) === 1
                ) {
                    $violations[] = "{$file->getPathname()} accesses {$owner}-owned table {$table}";
                }
            }
        }
    }
}

foreach (array_keys($allowedDependencies) as $expectedModule) {
    if (! in_array($expectedModule, $modules, true)) {
        $violations[] = "Architecture rule references missing module {$expectedModule}";
    }
}

$fixture = getenv('ARCHITECTURE_CHECK_FIXTURE');
if (is_string($fixture) && $fixture !== '' && is_file($fixture)) {
    $contents = file_get_contents($fixture);

    if (is_string($contents)) {
        foreach ($ownedTables as $owner => $tables) {
            if ($owner === 'IdentityAccess') {
                continue;
            }

            foreach ($tables as $table) {
                if (
                    preg_match(
                        "/(?:table|from|join)\\(\\s*['\"]".preg_quote($table, '/')."['\"]/",
                        $contents,
                    ) === 1
                ) {
                    $violations[] = "{$fixture} accesses {$owner}-owned table {$table}";
                }
            }
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, $violations).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Architecture boundaries passed for every module.\n");
