<?php

namespace App\Http\Controllers;

use App\Modules\IdentityAccess\Contracts\ActorResolver;
use App\Modules\PlatformOperations\Contracts\PlatformProbes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PlatformProbeController
{
    public function __construct(
        private ActorResolver $actors,
        private PlatformProbes $probes,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $actor = $this->actors->resolve($request) ?? throw new AuthorizationException;
        $idempotencyKey = $request->header('Idempotency-Key');

        abort_unless(
            is_string($idempotencyKey) && preg_match('/\A[a-zA-Z0-9._:-]{8,128}\z/', $idempotencyKey) === 1,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'A valid Idempotency-Key header is required.',
        );

        return response()->json([
            'data' => $this->probes->request($actor, $idempotencyKey)->toArray(),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(Request $request, string $probeId): JsonResponse
    {
        $actor = $this->actors->resolve($request) ?? throw new AuthorizationException;
        $probe = $this->probes->find($actor, $probeId);

        abort_if($probe === null, Response::HTTP_NOT_FOUND);

        return response()->json(['data' => $probe->toArray()]);
    }
}
