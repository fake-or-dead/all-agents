<?php

namespace App\Console\Commands;

use App\Modules\IdentityAccess\Contracts\LocalVerificationMailbox;
use Illuminate\Console\Command;
use RuntimeException;

final class ShowLocalRecoveryPath extends Command
{
    protected $signature = 'identity:local-recovery-path';

    protected $description = 'Read one recovery recipient from stdin and print its local fake path';

    public function handle(LocalVerificationMailbox $mailbox): int
    {
        if (
            ! app()->environment(['local', 'testing'])
            || config('identity-access.verification_adapter') !== 'deterministic-fake'
        ) {
            throw new RuntimeException('The local recovery harness is disabled.');
        }

        $email = fgets(STDIN);

        if (! is_string($email) || trim($email) === '') {
            throw new RuntimeException('A recovery recipient is required on stdin.');
        }

        $path = $mailbox->latestRecoveryPathFor(trim($email));

        if ($path === null) {
            return self::FAILURE;
        }

        $this->line($path);

        return self::SUCCESS;
    }
}
