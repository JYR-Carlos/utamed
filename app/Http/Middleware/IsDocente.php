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
     * Y que tenga una instancia Docente asociada.
     * Redirige a dashboard si falla la validación.
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

        // Verificar que sea docente (cualquiera de estos roles) y tenga perfil Docente
        $esDocente = $user->hasAnyRole([
            'Docente Titular',
            'Docente Titular Restringido',
            'Docente Componente'
        ]) && $user->docente;

        if (!$esDocente) {
            return redirect('/dashboard')->with('error', 'No tienes permisos para acceder a esta sección. Acceso restringido a docentes.');
        }
    
        return $next($request);
    }
}
