<?php

namespace App\Http\Middleware;

use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para validar que el usuario autenticado es administrador.
 *
 * Redirige a usuarios sin permiso al dashboard con mensaje de error.
 */
class IsAdmin
{
    /**
     * Verifica que el usuario autenticado sea administrador.
     *
     * El rol debe estar asignado **en el contexto global**: tenerlo en un contexto
     * acotado (una carrera, un curso) no habilita el área de administración. Sin
     * esta restricción, cualquier "Administrador" de un contexto local obtenía
     * acceso a todo el módulo admin.
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

        if (!$this->isAdmin($user)) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección. Acceso restringido a administradores.');
        }

        return $next($request);
    }

    /**
     * Admin = permiso wildcard global ('*') o rol administrativo en el contexto global.
     */
    private function isAdmin(Usuario $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasAnyRoleGlobally(Usuario::ROLES_ADMINISTRATIVOS);
    }
}
