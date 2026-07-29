<?php

declare(strict_types=1);

$expectedCommand = 'exec php -d memory_limit=1G vendor/bin/phpstan analyse --memory-limit=1G';
$expectedScript = "#!/bin/sh\n\nset -eu\n\n{$expectedCommand}\n";
$files = [
    'bin/phpstan' => $expectedScript,
    'composer.json' => "\"analyse\": [\n            \"bin/phpstan\"\n        ]",
    '.github/workflows/ci.yml' => 'docker run --rm tapoda-next:test bin/phpstan',
    'README.md' => 'docker compose --profile tools run --rm test bin/phpstan',
];

foreach ($files as $path => $expectedContent) {
    $content = file_get_contents($path);

    if ($content === false || ! str_contains($content, $expectedContent)) {
        fwrite(STDERR, "PHPStan command contract failed: {$path} must contain the canonical command.\n");
        exit(1);
    }
}

$ci = file_get_contents('.github/workflows/ci.yml');
$canonicalCiCommand = 'docker run --rm tapoda-next:test bin/phpstan';

if (
    $ci === false
    || str_contains($ci, 'vendor/bin/phpstan')
    || count(array_filter(explode("\n", $ci), static fn (string $line): bool => trim($line) === $canonicalCiCommand)) !== 1
) {
    fwrite(STDERR, "PHPStan command contract failed: CI must invoke bin/phpstan only.\n");
    exit(1);
}

echo "PHPStan command contract passed.\n";
