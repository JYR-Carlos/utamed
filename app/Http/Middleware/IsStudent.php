<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
/**
 * Middleware para validar que el usuario autenticado es estudiante.
 * 
 * Verifica que el usuario posea perfil Estudiante.
 * Redirige a usuarios sin permiso al dashboard con mensaje de error.
 */
class IsStudent
{
    /**
     * Verifica que el usuario autenticado sea estudiante.
     * 
     * Valida que el usuario autenticado tenga instancia Estudiante asociada.
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

        $isStudent = $user->hasRole('Estudiante');

        if (!$isStudent) {
            return redirect('/dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        return $next($request);
    }
}
