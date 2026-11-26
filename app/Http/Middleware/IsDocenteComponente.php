<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionValidator;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para validar capacidades operativas de componente (Docente Componente)
 * mediante permisos efectivos y ficha Docente asociada.
 */
class IsDocenteComponente
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Usuario\Usuario|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $validator = app(PermissionValidator::class);
        $tienePermisoComponente = $validator->isSuperAdmin($user)
            || !empty($validator->getContextsFromPermission($user, Permissions::COMPONENTES_EDITAR))
            || !empty($validator->getContextsFromPermission($user, Permissions::ACTIVIDADES_EVALUAR));

        if (!$user->docente || !$tienePermisoComponente) {
            return redirect('/dashboard')->with('error', 'No tienes permisos operativos de componente (Docente Componente).');
        }

        return $next($request);
    }
}
