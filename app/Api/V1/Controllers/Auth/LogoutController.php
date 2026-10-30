<?php

namespace App\Api\V1\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
