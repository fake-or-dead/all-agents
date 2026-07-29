<?php

namespace Tests\Unit\People;

use PHPUnit\Framework\TestCase;

final class ApplicationContextEvidenceBootstrapEnvTest extends TestCase
{
    public function test_bootstrap_generates_the_dedicated_key_once_and_preserves_it(): void
    {
        $root = sys_get_temp_dir().'/tapoda-context-env-'.bin2hex(random_bytes(6));
        $bin = "{$root}/bin";
        mkdir($bin, 0777, true);
        copy(dirname(__DIR__, 3).'/.env.example', "{$root}/.env.example");
        copy(dirname(__DIR__, 3).'/bin/bootstrap-env', "{$bin}/bootstrap-env");
        $counter = "{$root}/docker-count";
        $fakeDocker = "{$bin}/docker";
        file_put_contents($fakeDocker, <<<'SH'
#!/bin/sh
count=0
if [ -f "$FAKE_DOCKER_COUNTER" ]; then
    count="$(cat "$FAKE_DOCKER_COUNTER")"
fi
count=$((count + 1))
printf '%s' "$count" > "$FAKE_DOCKER_COUNTER"
printf 'generated-%s' "$count"
SH);
        chmod($fakeDocker, 0755);

        try {
            $command = sprintf(
                'cd %s && PATH=%s:$PATH FAKE_DOCKER_COUNTER=%s sh bin/bootstrap-env',
                escapeshellarg($root),
                escapeshellarg($bin),
                escapeshellarg($counter),
            );
            exec($command, $firstOutput, $firstExit);
            self::assertSame(0, $firstExit, implode("\n", $firstOutput));
            $firstEnv = (string) file_get_contents("{$root}/.env");
            self::assertMatchesRegularExpression(
                '/^PEOPLE_CONTEXT_EVIDENCE_KEY=generated-4$/m',
                $firstEnv,
            );

            exec($command, $secondOutput, $secondExit);
            self::assertSame(0, $secondExit, implode("\n", $secondOutput));
            $secondEnv = (string) file_get_contents("{$root}/.env");
            self::assertSame($firstEnv, $secondEnv);
            self::assertSame(
                1,
                substr_count($secondEnv, 'PEOPLE_CONTEXT_EVIDENCE_KEY='),
            );
        } finally {
            foreach (glob("{$bin}/*") ?: [] as $file) {
                unlink($file);
            }
            foreach (glob("{$root}/*") ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            foreach (["{$root}/.env", "{$root}/.env.example"] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($bin);
            rmdir($root);
        }
    }
}
