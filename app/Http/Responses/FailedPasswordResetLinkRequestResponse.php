<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Respuesta cuando no se pudo enviar el enlace para recuperar la contraseña.
 *
 * La de Fortify devuelve el texto del broker en inglés (el proyecto no trae
 * `lang/es`) y no dice si el correo no es de ninguna cuenta o si ya se pidió
 * un enlace hace menos de `throttle` segundos. La pantalla necesita saberlo
 * para explicar qué pasó y, en la pausa, mostrar cuánto falta; por eso, además
 * del error del campo, deja en la sesión `recuperarAviso` con el motivo.
 *
 * El comportamiento no cambia: los mismos casos fallan y el error sigue
 * colgando del campo `email`.
 */
class FailedPasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponseContract
{
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        $enPausa = $this->status === Password::RESET_THROTTLED;
        $mensaje = $enPausa
            ? 'Ya te enviamos un enlace hace un momento.'
            : 'No encontramos una cuenta con ese correo.';

        if ($request->wantsJson()) {
            throw ValidationException::withMessages([Fortify::email() => [$mensaje]]);
        }

        return back()
            ->withInput($request->only(Fortify::email()))
            ->withErrors([Fortify::email() => $mensaje])
            ->with('recuperarAviso', $enPausa
                ? ['codigo' => 'pausa', 'segundos' => $this->segundosDePausa($request)]
                : ['codigo' => 'no_registrado']);
    }

    /**
     * Lo que falta para poder pedir otro enlace, contado desde el último que
     * guardó el broker. Si no se encuentra, se usa la pausa completa.
     */
    private function segundosDePausa(Request $request): int
    {
        $config = config('auth.passwords.'.config('fortify.passwords'));
        $pausa = (int) ($config['throttle'] ?? 60);

        $creado = DB::table($config['table'])
            ->where('email', $request->input(Fortify::email()))
            ->value('created_at');

        if ($creado === null) {
            return $pausa;
        }

        $transcurridos = (int) floor(abs(Carbon::parse($creado)->diffInSeconds(now())));

        return max(1, $pausa - $transcurridos);
    }
}
