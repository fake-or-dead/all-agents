<?php

namespace App\Console\Commands;

use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

final class ApproveLocalPersonAccountLink extends Command
{
    protected $signature = 'people:local-account-link-proof';

    protected $description = 'Approve a one-use local account link from identity type and number on stdin';

    public function handle(PersonIdentityDirectory $people): int
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The local person-link harness is disabled.');
        }

        $type = fgets(STDIN);
        $identifier = fgets(STDIN);

        if (! is_string($type) || ! is_string($identifier)) {
            throw new RuntimeException('Identity type and identifier are required on stdin.');
        }

        $token = $people->approveAccountLink(
            IdentityClaim::fromInput(trim($type), trim($identifier)),
            CarbonImmutable::now()->addMinutes(15),
        );

        if ($token === null) {
            return self::FAILURE;
        }

        $this->line($token);

        return self::SUCCESS;
    }
}
