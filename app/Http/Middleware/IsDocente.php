<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
/**
 * Middleware para validar que el usuario autenticado es docente.
 * 
 * Verifica que el usuario posea perfil Docente.
 * Redirige a usuarios sin permiso al dashboard con mensaje de error.
 */
class IsDocente
{
    /**
     * Verifica que el usuario autenticado sea docente.
     *
     * Valida que el usuario tenga CUALQUIERA de estos roles:
     * - Docente Titular
     * - Docente Titular Restringido
     * - Docente Componente
     * Si tiene el rol pero no una instancia Docente asociada (ficha
     * incompleta), lo manda al panel dedicado en vez de un dashboard vacío o
     * un simple mensaje de error — es un caso de administración, no de
     * permisos.
     *
     * @param  Request  $request  Solicitud HTTP
     * @param  Closure  $next     Siguiente middleware/controlador
     * @return Response  Respuesta o redirección si no autorizado
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Usuario\Usuario|null $user */
        $user = Auth::user();

        // Si no hay usuario autenticado, redirigir a login
        if (!$user) {
            return redirect('/login');
        }

        $tieneRolDocente = $user->hasAnyRole([
            'Docente Titular',
            'Docente Titular Restringido',
            'Docente Componente',
        ]);

        if (!$tieneRolDocente) {
            return redirect('/dashboard')->with('error', 'No tienes permisos para acceder a esta sección. Acceso restringido a docentes.');
        }

        if (!$user->docente) {
            return redirect()->route('docente.perfil-incompleto');
        }

        return $next($request);
    }
}
