<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Support\Rut;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Pedir el enlace para restablecer la contraseña (POST /forgot-password).
 *
 * La persona escribe su RUT, como en el login, y el enlace sale al correo
 * institucional de su cuenta (`usuario.email`). No escribe el correo, y si el
 * pedido trae uno, no se usa: nunca sale hacia un correo personal.
 *
 * Reemplaza al `store` de Fortify, que pedía el correo. La ruta está en
 * routes/web.php con el mismo camino y nombre (`password.email`), y como se
 * carga después de las de Fortify, es la que atiende.
 *
 * Si el enlace no sale, la pantalla recibe el motivo en `recuperarAviso`: RUT
 * sin cuenta, cuenta sin correo institucional, o pedido muy seguido, con los
 * segundos que faltan según el broker. Si sale, recibe en `recuperarEnviado`
 * el correo con una parte oculta, para que la persona sepa dónde buscarlo.
 */
class PasswordResetLinkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'rut' => ['required', 'string', function (string $campo, mixed $valor, Closure $falla) {
                if (!Rut::esValido($valor)) {
                    $falla('El RUT está incompleto. Escribe los números y el dígito verificador, por ejemplo 12345678-5.');
                }
            }],
        ], [
            'rut.required' => 'Escribe tu RUT.',
        ]);

        $usuario = Usuario::porRut($request->input('rut'));

        if ($usuario === null) {
            return $this->rechazo($request, 'No encontramos una cuenta con ese RUT.', ['codigo' => 'no_registrado']);
        }

        if (blank($usuario->getEmailForPasswordReset())) {
            return $this->rechazo($request, 'Tu cuenta no tiene un correo institucional registrado.', ['codigo' => 'sin_correo']);
        }

        // Por id y no por correo: el enlace es para esta cuenta y sale a su
        // correo institucional, el único que conoce la notificación.
        $estado = Password::broker(config('fortify.passwords'))
            ->sendResetLink(['id_usuario' => $usuario->id_usuario]);

        return match ($estado) {
            Password::RESET_LINK_SENT => back()->with('recuperarEnviado', $this->ocultar($usuario->getEmailForPasswordReset())),
            Password::RESET_THROTTLED => $this->rechazo($request, 'Ya te enviamos un enlace hace un momento.', [
                'codigo' => 'pausa',
                'segundos' => $this->segundosDePausa($usuario),
            ]),
            default => $this->rechazo($request, 'No encontramos una cuenta con ese RUT.', ['codigo' => 'no_registrado']),
        };
    }

    /** Vuelve al formulario con el RUT escrito, el error del campo y el motivo. */
    private function rechazo(Request $request, string $mensaje, array $aviso): RedirectResponse
    {
        return back()
            ->withInput($request->only('rut'))
            ->withErrors(['rut' => $mensaje])
            ->with('recuperarAviso', $aviso);
    }

    /**
     * Lo que falta para poder pedir otro enlace, contado desde el último que
     * guardó el broker. Si no se encuentra, se usa la pausa completa.
     */
    private function segundosDePausa(Usuario $usuario): int
    {
        $config = config('auth.passwords.'.config('fortify.passwords'));
        $pausa = (int) ($config['throttle'] ?? 60);

        $creado = DB::table($config['table'])
            ->where('email', $usuario->getEmailForPasswordReset())
            ->value('created_at');

        if ($creado === null) {
            return $pausa;
        }

        $transcurridos = (int) floor(abs(Carbon::parse($creado)->diffInSeconds(now())));

        return max(1, $pausa - $transcurridos);
    }

    /** «maria.ejemplo@alumnos.uta.cl» → «ma•••@alumnos.uta.cl». */
    private function ocultar(string $correo): string
    {
        [$nombre, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($nombre, 0, 2).'•••'.($dominio !== '' ? '@'.$dominio : '');
    }
}
