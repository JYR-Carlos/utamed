<?php

namespace App\Api\V1\Controllers\Auth;

use App\Api\V1\Middleware\IsStudentApi;
use App\Api\V1\Requests\Auth\LoginRequest;
use App\Api\V1\Resources\Auth\StudentAuthResource;
use App\Models\Usuario\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController
{
    public function __invoke(LoginRequest $request): JsonResponse|StudentAuthResource
    {
        $usuario = Usuario::where('rut', $request->validated('rut'))->first();

        if (! $usuario || ! Hash::check($request->validated('password'), (string) $usuario->getAuthPassword())) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        if (! IsStudentApi::esEstudianteActivo($usuario)) {
            return response()->json(['message' => IsStudentApi::MENSAJE], 403);
        }

        $token = $usuario->createToken('mobile-student-app', ['role:student'])->plainTextToken;

        return new StudentAuthResource($usuario, $token);
    }
}
