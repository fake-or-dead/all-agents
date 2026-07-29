<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__).'/app/Modules';
$allowedImports = [
    'Audit' => [],
    'DocumentsConsent' => [],
    'IdentityAccess' => ['DocumentsConsent', 'People'],
    'People' => [],
    'PlatformOperations' => ['Audit', 'IdentityAccess'],
];
$ownedTables = [
    'DocumentsConsent' => [
        'consent_documents',
        'consent_document_versions',
        'consent_acceptances',
    ],
    'People' => ['people', 'person_identifiers'],
];
$violations = [];
$discoveredModules = [];

foreach (new DirectoryIterator($moduleRoot) as $moduleDirectory) {
    if ($moduleDirectory->isDot() || ! $moduleDirectory->isDir()) {
        continue;
    }

    $module = $moduleDirectory->getFilename();
    $discoveredModules[] = $module;

    if (! array_key_exists($module, $allowedImports)) {
        $violations[] = "{$module} has no explicit architecture policy";

        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleDirectory->getPathname(), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        foreach (array_keys($allowedImports) as $importedModule) {
            if (
                $importedModule !== $module
                && ! in_array($importedModule, $allowedImports[$module], true)
                && str_contains($contents, "App\\Modules\\{$importedModule}\\")
            ) {
                $violations[] = "{$file->getPathname()} imports forbidden module {$importedModule}";
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

foreach (array_keys($allowedImports) as $module) {
    if (! in_array($module, $discoveredModules, true)) {
        $violations[] = "{$module} architecture policy has no module directory";
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, $violations).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Architecture boundaries passed for every module.\n");
