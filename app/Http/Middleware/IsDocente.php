<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionValidator;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para validar que el usuario autenticado tiene acceso al portal docente
 * basado en permisos efectivos de docencia y ficha Docente asociada.
 */
class IsDocente
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Usuario\Usuario|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $validator = app(PermissionValidator::class);
        $tienePermisoDocente = $validator->isSuperAdmin($user)
            || !empty($validator->getContextsFromPermission($user, Permissions::CURSOS_VER))
            || !empty($validator->getContextsFromPermission($user, Permissions::COMPONENTES_VER))
            || !empty($validator->getContextsFromPermission($user, Permissions::ACTIVIDADES_VER));

        if (!$tienePermisoDocente) {
            return redirect('/dashboard')->with('error', 'No tienes permisos para acceder a esta sección. Acceso restringido a docentes.');
        }

        if (!$user->docente) {
            return redirect()->route('docente.perfil-incompleto');
        }

        return $next($request);
    }
}
