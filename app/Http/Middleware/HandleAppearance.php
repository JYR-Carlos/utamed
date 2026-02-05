<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para compartir configuración de apariencia (theme) con vistas.
 * 
 * Lee preferencia de apariencia desde cookie del cliente (system, light, dark)
 * y la comparte con todas las vistas Inertia.js para aplicar theme consistente.
 */
class HandleAppearance
{
    /**
     * Procesa request y comparte configuración de apariencia con vistas.
     * 
     * Obtiene valor de cookie 'appearance' (default: 'system') y lo disponibiliza
     * en todas las vistas a través de View::share().
     * 
     * @param  Request  $request  Solicitud HTTP
     * @param  Closure  $next     Siguiente middleware/controlador
     * @return Response  Respuesta HTTP
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');

        return $next($request);
    }
}
