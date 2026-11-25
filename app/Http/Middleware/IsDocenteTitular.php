<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionValidator;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para validar capacidades de gobierno de curso (Docente Titular / gestión de curso)
 * mediante permisos efectivos y ficha Docente asociada.
 */
class IsDocenteTitular
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Usuario\Usuario|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $validator = app(PermissionValidator::class);
        $tienePermisoGobiernoCurso = $validator->isSuperAdmin($user)
            || !empty($validator->getContextsFromPermission($user, Permissions::CURSOS_PROGRAMAS_AGREGAR))
            || !empty($validator->getContextsFromPermission($user, Permissions::CURSOS_UNIDADES_CREAR));

        if (!$user->docente || !$tienePermisoGobiernoCurso) {
            return redirect('/dashboard')->with('error', 'No tienes permisos de gobierno de curso (Docente Titular).');
        }

        return $next($request);
    }
}
