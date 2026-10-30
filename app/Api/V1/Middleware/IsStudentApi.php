<?php

namespace App\Api\V1\Middleware;

use App\Models\Usuario\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el usuario autenticado por Sanctum sea un estudiante activo.
 */
class IsStudentApi
{
    public const MENSAJE = 'Acceso exclusivo para estudiantes activos.';

    public static function esEstudianteActivo(?Usuario $user): bool
    {
        return $user !== null && $user->esta_activo && $user->hasRole('Estudiante') && $user->estudiante !== null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::esEstudianteActivo($request->user())) {
            return response()->json(['message' => self::MENSAJE], 403);
        }

        return $next($request);
    }
}
