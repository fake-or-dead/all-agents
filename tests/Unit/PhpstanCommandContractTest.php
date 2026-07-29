<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PhpstanCommandContractTest extends TestCase
{
    private const string Command = 'exec php -d memory_limit=1G vendor/bin/phpstan analyse --memory-limit=1G';

    public function test_phpstan_uses_the_reviewed_memory_limit_and_forwards_failures(): void
    {
        $script = base_path('bin/phpstan');

        self::assertFileExists($script);
        self::assertNotSame(0, fileperms($script) & 0o111);
        self::assertSame("#!/bin/sh\n\nset -eu\n\n".self::Command."\n", file_get_contents($script));
    }

    public function test_composer_uses_the_canonical_command(): void
    {
        /** @var array{scripts: array{analyse: list<string>}} $composer */
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['bin/phpstan'], $composer['scripts']['analyse']);
    }
}
