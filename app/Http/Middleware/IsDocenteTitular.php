<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware para validar que el usuario autenticado es Docente Titular.
 * 
 * Verifica que el usuario tenga el rol "Docente Titular".
 * Redirige a usuarios sin permiso al dashboard con mensaje de error.
 */
class IsDocenteTitular
{
    /**
     * Verifica que el usuario autenticado sea docente titular.
     * 
     * Valida que:
     * 1. El usuario esté autenticado
     * 2. El usuario tenga rol "Docente Titular"
     * 3. El usuario tenga una instancia Docente asociada
     * 
     * Redirige a dashboard si falla la validación.
     * 
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Usuario\Usuario|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        // Validar que tenga rol "Docente Titular" y perfil Docente
        if (!$user->hasRole('Docente Titular') || !$user->docente) {
            return redirect('/dashboard')->with('error', 'No tienes permisos de Docente Titular. Este acceso es restringido a docentes con rol Docente Titular.');
        }

        return $next($request);
    }
}
