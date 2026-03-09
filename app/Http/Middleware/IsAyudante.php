<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware para validar que el usuario autenticado es ayudante.
 * 
 * Verifica que el usuario posea rol Ayudante activo.
 * Redirige a usuarios sin permiso al dashboard con mensaje de error.
 */
class IsAyudante
{
    /**
     * Verifica que el usuario autenticado sea ayudante.
     * 
     * Valida que el usuario autenticado tenga rol Ayudante activado y no eliminado.
     * Redirige a dashboard si falla la validación.
     * 
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Usuario|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $isAyudante = $user->hasRole('Ayudante');

        if (!$isAyudante) {
            return redirect('/dashboard')->with('error', 'No tienes permisos de Ayudante.');
        }

        return $next($request);
    }
}
