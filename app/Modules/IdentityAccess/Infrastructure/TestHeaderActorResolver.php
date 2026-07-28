<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Modules\IdentityAccess\Contracts\ActorResolver;
use App\Modules\IdentityAccess\Data\Actor;
use Illuminate\Http\Request;

final class TestHeaderActorResolver implements ActorResolver
{
    public function resolve(Request $request): ?Actor
    {
        $actorId = $request->header('X-Tapoda-Test-Actor');

        if (! is_string($actorId) || $actorId === '') {
            return null;
        }

        return new Actor('test-account', $actorId, ['platform.probe']);
    }
}
