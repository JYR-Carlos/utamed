<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
     * `motivoCambioObligatorio` viaja como prop de la página en vez de ir en los
     * datos compartidos de Inertia: sólo esta pantalla lo usa, y calcularlo en
     * cada respuesta de la aplicación para que lo lea una sola vista sería
     * trabajo tirado.
     */
    public function edit(Request $request): Response
    {
        /** @var Usuario $user */
        $user = $request->user();

        return Inertia::render('settings/Password', [
            'motivoCambioObligatorio' => $user->motivoCambioPasswordObligatorio(),
            'vigenciaMeses' => Usuario::VIGENCIA_PASSWORD_MESES,
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        /** @var Usuario $user */
        $user = $request->user();

        // `cambiarPassword()` y no `update(['password' => ...])`: la tabla guarda
        // el hash en `passhash`, así que 'password' no era ni columna ni campo
        // asignable y el cambio se perdía en silencio. Además el modelo deja
        // anotada la fecha, que es lo que hace que se libere la navegación.
        $user->cambiarPassword($validated['password']);

        return back();
    }
}
