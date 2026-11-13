<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ForcePasswordChange;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Usuario\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage the authenticated user's password settings.
 */
class PasswordController extends Controller
{
    /** Clave de sesión con el motivo del cambio obligatorio recién cumplido. */
    private const CAMBIO_OBLIGATORIO_LISTO = 'cambioObligatorioListo';

    /**
     * Show the user's password settings page.
     *
     * Son dos pantallas distintas según el estado de la clave:
     *
     * - Cambio obligatorio (primer ingreso o clave vencida): una página sin el
     *   layout de la aplicación. Mientras la clave no cumpla la política,
     *   {@see ForcePasswordChange} trae aquí al usuario desde cualquier otra
     *   ruta, así que mostrar la barra lateral y el menú sería ofrecer enlaces
     *   que sólo llevan de vuelta a esta misma pantalla.
     * - Cambio voluntario: la página de configuración de siempre.
     *
     * El motivo viaja como prop de la página en vez de ir en los datos
     * compartidos de Inertia: sólo esta pantalla lo usa, y calcularlo en cada
     * respuesta de la aplicación para que lo lea una sola vista sería trabajo
     * tirado.
     *
     * Justo después del cambio obligatorio el motivo ya no existe, pero la
     * pantalla se muestra una vez más con el aviso de que la contraseña se
     * cambió (`cambiada`); lo marca update() en la sesión con el motivo que
     * había, y al recargar se pierde, como cualquier aviso.
     */
    public function edit(Request $request): Response
    {
        /** @var Usuario $user */
        $user = $request->user();
        $motivo = $user->motivoCambioPasswordObligatorio();
        $motivoCumplido = $motivo === null ? $request->session()->get(self::CAMBIO_OBLIGATORIO_LISTO) : null;

        if ($motivo !== null || $motivoCumplido !== null) {
            return Inertia::render('auth/CambioPasswordObligatorio', [
                'motivo' => $motivo ?? $motivoCumplido,
                'vigenciaMeses' => Usuario::VIGENCIA_PASSWORD_MESES,
                'cambiada' => $motivo === null,
            ]);
        }

        // Quien sólo es estudiante cambia la clave dentro de su portal (T16):
        // la página de ajustes genérica trae además pestañas de perfil que el
        // alumno no debe usar. El formulario envía al mismo update().
        if ($user->esSoloEstudiante()) {
            return Inertia::render('student/CambiarPassword');
        }

        return Inertia::render('settings/Password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        // Mensajes en español a mano: el proyecto no trae `lang/es`, así que sin
        // esto la pantalla —la primera que ve cualquier usuario nuevo— hablaría
        // en inglés justo al reclamar. Las reglas de Password::defaults()
        // (largo, letras, mayúscula, números, signos) se cubren con el comodín `password.*`.
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.*' => 'La nueva contraseña debe tener al menos 8 caracteres, con una mayúscula, números y algún signo.',
        ]);

        /** @var Usuario $user */
        $user = $request->user();

        // Se consulta antes de cambiar la clave, porque después del cambio el
        // motivo desaparece y ya no se sabría de qué pantalla venía el usuario.
        $motivo = $user->motivoCambioPasswordObligatorio();

        // `cambiarPassword()` y no `update(['password' => ...])`: la tabla guarda
        // el hash en `passhash`, así que 'password' no era ni columna ni campo
        // asignable y el cambio se perdía en silencio. Además el modelo deja
        // anotada la fecha, que es lo que hace que se libere la navegación.
        $user->cambiarPassword($validated['password']);

        // Cuando el cambio era obligatorio, el usuario vuelve a la misma
        // pantalla para ver el aviso de que la contraseña se cambió; desde ahí
        // entra al dashboard, que es adonde iba. La navegación ya está libre.
        if ($motivo !== null) {
            return redirect()
                ->route('user-password.edit')
                ->with(self::CAMBIO_OBLIGATORIO_LISTO, $motivo);
        }

        return back();
    }
}
