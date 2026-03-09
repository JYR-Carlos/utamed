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
     * Valida que el usuario autenticado tenga instancia Docente asociada.
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

        // Un usuario debe ser docente para acceder a estas rutas
        if (!$user->hasRole('Docente')) {
            return redirect('/dashboard')->with('error', 'No tienes permisos para acceder a esta sección. Acceso restringido a docentes.');
        }
    
        return $next($request);
    }
}
