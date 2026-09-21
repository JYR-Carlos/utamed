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
     */
    public function edit(Request $request): Response
    {
        /** @var Usuario $user */
        $user = $request->user();
        $motivo = $user->motivoCambioPasswordObligatorio();

        if ($motivo !== null) {
            return Inertia::render('auth/CambioPasswordObligatorio', [
                'motivo' => $motivo,
                'vigenciaMeses' => Usuario::VIGENCIA_PASSWORD_MESES,
            ]);
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
        // (largo, letras, números) se cubren con el comodín `password.*`.
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.*' => 'La nueva contraseña debe tener al menos 8 caracteres, con letras y números.',
        ]);

        /** @var Usuario $user */
        $user = $request->user();

        // Se consulta antes de cambiar la clave, porque después del cambio el
        // motivo desaparece y ya no se sabría de qué pantalla venía el usuario.
        $eraObligatorio = $user->debeCambiarPassword();

        // `cambiarPassword()` y no `update(['password' => ...])`: la tabla guarda
        // el hash en `passhash`, así que 'password' no era ni columna ni campo
        // asignable y el cambio se perdía en silencio. Además el modelo deja
        // anotada la fecha, que es lo que hace que se libere la navegación.
        $user->cambiarPassword($validated['password']);

        // Cuando el cambio era obligatorio el usuario no venía de ninguna parte:
        // el middleware lo trajo desde el login. `back()` lo dejaría mirando la
        // pantalla de cambio, ahora sin motivo; lo natural es soltarlo en el
        // dashboard, que es adonde iba.
        if ($eraObligatorio) {
            return redirect()->route('dashboard');
        }

        return back();
    }
}
