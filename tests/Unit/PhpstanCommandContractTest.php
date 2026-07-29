<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PhpstanCommandContractTest extends TestCase
{
    private const string Command = 'exec php -d memory_limit=1G vendor/bin/phpstan analyse --memory-limit=1G "$@"';

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

    public function test_phpstan_forwards_arbitrary_arguments_and_exit_code(): void
    {
        $directory = sys_get_temp_dir().'/tapoda-phpstan-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0o700));

        $fakePhp = $directory.'/php';
        file_put_contents($fakePhp, "#!/bin/sh\nprintf '%s\\n' \"\$@\"\nexit 23\n");
        chmod($fakePhp, 0o700);

        try {
            $process = proc_open(
                [base_path('bin/phpstan'), '--error-format=table', 'path with spaces', '--level=9'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                ['PATH' => $directory.':'.getenv('PATH')],
            );

            self::assertIsResource($process);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertSame(23, proc_close($process));
            self::assertSame(
                [
                    '-d',
                    'memory_limit=1G',
                    'vendor/bin/phpstan',
                    'analyse',
                    '--memory-limit=1G',
                    '--error-format=table',
                    'path with spaces',
                    '--level=9',
                ],
                explode("\n", rtrim($stdout)),
            );
        } finally {
            unlink($fakePhp);
            rmdir($directory);
        }
    }
}
