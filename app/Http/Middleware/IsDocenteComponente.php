<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Curso\DocenteComponente;

/**
 * Middleware para validar que el usuario autenticado es Docente Componente.
 * 
 * Verifica que el usuario sea docente y tenga al menos un componente asignado.
 * Redirige a usuarios sin permiso al dashboard con mensaje de error.
 */
class IsDocenteComponente
{
    /**
     * Verifica que el usuario autenticado sea docente componente.
     * 
     * Valida que:
     * 1. El usuario esté autenticado
     * 2. El usuario tenga rol "Docente Componente"
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

        // Validar que tenga rol "Docente Componente" y perfil Docente
        if (!$user->hasRole('Docente Componente') || !$user->docente) {
            return redirect('/dashboard')->with('error', 'No tienes permisos de Docente Componente. Este acceso es restringido a docentes con rol Docente Componente.');
        }

        return $next($request);
    }
}
