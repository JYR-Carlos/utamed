<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para validar que el usuario autenticado es administrador.
 * 
 * Define administrador como usuario que NO tiene perfil de Docente ni Estudiante.
 * Redirige a usuarios sin permiso al dashboard con mensaje de error.
 */
class IsAdmin
{
    /**
     * Verifica que el usuario autenticado sea administrador.
     * 
     * Un usuario es administrador si NO posee instancia Docente ni Estudiante.
     * Redirige a dashboard si falla la validación.
     * 
     * @param  Request  $request  Solicitud HTTP
     * @param  Closure  $next     Siguiente middleware/controlador
     * @return Response  Respuesta o redirección si no autorizado
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Si no hay usuario autenticado, redirigir a login
        if (!$user) {
            return redirect('/login');
        }

        // Un usuario es admin si tiene rol Administrador o SuperAdmin activo
        $isAdmin = false;

        // Check roles via relationship if loaded, or query
        $roles = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->with('rol')
            ->get()
            ->pluck('rol.nombre')
            ->toArray();

        // Check if user has 'Administrador' or 'SuperAdmin' role
        // Also keep legacy check for now if roles aren't fully migrated, OR strictly enforce roles.
        // Given the task is to fix bypass, we should strictly enforce roles.
        // But for safety during transition, we might want to check the negative condition too?
        // No, let's stick to positive role check as requested.

        $adminRoles = ['Administrador', 'SuperAdmin', 'Super Admin'];
        if (count(array_intersect($adminRoles, $roles)) > 0) {
            $isAdmin = true;
        }

        if (!$isAdmin) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección. Acceso restringido a administradores.');
        }

        return $next($request);
    }
}
