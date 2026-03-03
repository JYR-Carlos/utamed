<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use App\Models\Usuario\Usuario;
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
        /** @var Usuario|null $user */
        $user = Auth::user();

        // Si no hay usuario autenticado, redirigir a login
        
        if (!$user) {
            return redirect('/login');
        }

        // Un usuario es admin si tiene rol SuperAdmin activo O tiene el permiso wildcard '*'
        $allRoles = $user->getAllRoles();
        \Illuminate\Support\Facades\Log::info('IsAdmin Middleware Check', [
            'user_id' => $user->id_usuario,
            'username' => $user->username,
            'all_roles' => $allRoles,
            'has_superadmin_role' => $user->hasRole('SuperAdmin'),
            'is_super_admin' => $user->isSuperAdmin(),
        ]);

        $isAdmin = $user->hasRole('SuperAdmin') || $user->isSuperAdmin();

        if (!$isAdmin) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección. Acceso restringido a administradores.');
        }

        return $next($request);
    }
}
