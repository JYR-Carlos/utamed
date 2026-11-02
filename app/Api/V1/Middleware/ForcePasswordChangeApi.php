<?php

namespace App\Api\V1\Middleware;

use App\Models\Usuario\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso a los endpoints de la API móvil si el usuario debe
 * actualizar obligatoriamente su contraseña (por vencimiento semestral o primer ingreso).
 */
class ForcePasswordChangeApi
{
    /**
     * Rutas exentas de bloqueo para permitir regularizar la clave o cerrar sesión.
     */
    private const RUTAS_PERMITIDAS = [
        'api.v1.auth.cambio-password-obligatorio',
        'api.v1.auth.logout',
        'api.v1.auth.me',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (! $user || ! $user->debeCambiarPassword()) {
            return $next($request);
        }

        $nombreRuta = $request->route()?->getName();

        if ($nombreRuta && in_array($nombreRuta, self::RUTAS_PERMITIDAS, true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Debes actualizar tu contraseña antes de continuar.',
            'error' => 'password_change_required',
            'motivo' => $user->motivoCambioPasswordObligatorio(),
        ], Response::HTTP_LOCKED);
    }
}
