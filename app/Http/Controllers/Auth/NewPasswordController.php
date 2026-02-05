<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador para completar el reset de contraseña.
 * 
 * Procesa el enlace de reset enviado por email y permite establecer nueva contraseña.
 */
class NewPasswordController extends Controller
{
    /**
     * Muestra la página para ingresar nueva contraseña.
     * 
     * Renderiza formulario con token de reset pre-poblado (obtenido de URL).
     * 
     * @param  Request  $request  Solicitud HTTP (contiene token y email en URL)
     * @return Response  Vista Inertia con formulario de nueva contraseña
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Procesa y valida reset de contraseña: genera nueva contraseña hasheada.
     * 
     * Valida token, email y nueva contraseña.
     * Si válido, actualiza password, marca sesión como reset, dispara evento PasswordReset.
     * 
     * @param  Request  $request  Datos: token, email, password, password_confirmation
     * @return RedirectResponse  Redirección a login o home según resultado
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status == Password::PasswordReset) {
            return to_route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
