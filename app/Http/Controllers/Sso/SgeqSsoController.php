<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Services\Sso\SgeqSsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entrada a SGEQ desde UTAmed.
 *
 * El botón «Ir a SGEQ» apunta acá y no directamente a SGEQ: el token se firma en
 * el momento del clic, así nunca queda escrito en el HTML de UTAmed ni sobrevive
 * en una pestaña abierta desde ayer. Lo que el navegador recibe es un redirect
 * hacia SGEQ con un token que dura menos de un minuto y se acepta una sola vez.
 *
 * @see SgeqSsoService Para quién puede pasar y con qué rol.
 */
class SgeqSsoController extends Controller
{
    public function __construct(protected SgeqSsoService $sso) {}

    public function redirigir(): RedirectResponse
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();

        $rol = $this->sso->resolverRol($usuario);

        if ($rol === null) {
            // Sin detalle en pantalla: quien no corresponde no necesita saber qué
            // condición falló. El motivo queda en el log para soporte.
            Log::info('[SSO SGEQ] Acceso denegado', [
                'id_usuario' => $usuario->id_usuario,
                'id_carrera' => $usuario->estudiante?->id_carrera,
            ]);

            abort(403, 'Tu cuenta no tiene acceso al sistema de préstamo de equipos.');
        }

        try {
            $token = $this->sso->emitirToken($usuario, $rol);
        } catch (Throwable $e) {
            // Falta de configuración o clave ilegible: es un problema del servidor,
            // no del usuario, y el mensaje real no debe salir a la pantalla.
            Log::error('[SSO SGEQ] No se pudo emitir el token', [
                'id_usuario' => $usuario->id_usuario,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'No se pudo abrir el sistema de préstamo de equipos.');
        }

        Log::info('[SSO SGEQ] Token emitido', [
            'id_usuario' => $usuario->id_usuario,
            'rol' => $rol,
        ]);

        return redirect()->away($this->sso->urlDeConsumo($token));
    }
}
