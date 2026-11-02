<?php

namespace App\Api\V1\Controllers\Auth;

use App\Api\V1\Requests\Auth\ChangeExpiredPasswordRequest;
use App\Api\V1\Resources\Auth\StudentAuthResource;
use App\Models\Usuario\Usuario;

class ChangeExpiredPasswordController
{
    public function __invoke(ChangeExpiredPasswordRequest $request): StudentAuthResource
    {
        /** @var Usuario $user */
        $user = $request->user();

        $user->cambiarPassword($request->validated('password'));

        return (new StudentAuthResource($user))
            ->additional(['message' => 'Contraseña actualizada correctamente.']);
    }
}
