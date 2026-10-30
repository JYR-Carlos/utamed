<?php

namespace App\Api\V1\Controllers\Auth;

use App\Api\V1\Resources\Auth\StudentAuthResource;
use Illuminate\Http\Request;

class MeController
{
    public function __invoke(Request $request): StudentAuthResource
    {
        return new StudentAuthResource($request->user());
    }
}
