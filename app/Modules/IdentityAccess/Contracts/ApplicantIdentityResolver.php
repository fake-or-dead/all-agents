<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Modules\IdentityAccess\Data\ApplicantIdentity;
use Illuminate\Http\Request;

interface ApplicantIdentityResolver
{
    public function resolve(Request $request): ?ApplicantIdentity;
}
