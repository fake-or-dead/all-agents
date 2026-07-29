<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Modules\IdentityAccess\Data\Actor;
use Illuminate\Http\Request;

interface ActorResolver
{
    public function resolve(Request $request): ?Actor;
}
