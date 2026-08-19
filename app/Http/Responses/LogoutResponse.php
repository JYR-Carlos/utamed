<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Respuesta de cierre de sesión.
 *
 * Replica el comportamiento de Fortify (redirigir a `/`) y además invalida el
 * historial de Inertia.
 *
 * ¿POR QUÉ?
 * ---------
 * Inertia guarda los props de cada página visitada en `window.history.state`.
 * Al pulsar "atrás" después del logout el navegador restauraba esa entrada y
 * pintaba los datos del usuario anterior antes de que nada consultara al
 * servidor. `clearHistory()` borra la clave de cifrado del historial
 * (sessionStorage), con lo que Inertia ya no puede descifrar las entradas
 * previas: dispara una petición real y el middleware `auth` manda al login.
 *
 * ORDEN IMPORTANTE
 * ----------------
 * Fortify resuelve esta respuesta DESPUÉS de `session()->invalidate()`, así que
 * la marca `inertia.clear_history` se escribe en la sesión nueva y sobrevive al
 * redirect. Si se llamara antes, la invalidación la borraría.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        Inertia::clearHistory();

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect(Fortify::redirects('logout', '/'));
    }
}
