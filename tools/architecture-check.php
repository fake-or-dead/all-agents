<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__).'/app/Modules';
$forbiddenModuleImports = [
    'Audit' => ['IdentityAccess', 'PlatformOperations'],
    'IdentityAccess' => ['Audit', 'PlatformOperations'],
    'PlatformOperations' => [],
];
$violations = [];

foreach ($forbiddenModuleImports as $module => $forbiddenImports) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator("{$moduleRoot}/{$module}", FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        foreach ($forbiddenImports as $forbiddenImport) {
            if (str_contains($contents, "App\\Modules\\{$forbiddenImport}\\")) {
                $violations[] = "{$file->getPathname()} imports forbidden module {$forbiddenImport}";
            }
        }

        if (
            preg_match('#/(Contracts|Data)/#', $file->getPathname()) === 1
            && preg_match('/Illuminate\\\\(Database|Support\\\\Facades)/', $contents) === 1
        ) {
            $violations[] = "{$file->getPathname()} couples a module contract to persistence";
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, $violations).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Architecture boundaries passed.\n");
