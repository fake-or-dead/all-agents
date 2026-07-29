<?php

namespace Tests\Fixtures\Architecture\IdentityAccess;

use Illuminate\Database\ConnectionInterface;

final readonly class DirectPeopleProofAccess
{
    public function __construct(private ConnectionInterface $database) {}

    public function read(): void
    {
        $this->database->table('person_account_link_proofs')->get();
    }
}
