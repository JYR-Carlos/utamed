<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador para gestionar solicitudes de reset de contraseña.
 * 
 * Permite a usuarios solicitar un enlace de recuperación de contraseña
 * que será enviado por correo electrónico si la cuenta existe.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Muestra la página para solicitar enlace de reset de contraseña.
     * 
     * Renderiza formulario donde usuario ingresa su email.
     * 
     * @param  Request  $request  Solicitud HTTP (puede contener mensaje de estado)
     * @return Response  Vista Inertia con formulario
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Procesa solicitud de reset: valida email y envía enlace de recuperación.
     * 
     * Valida que email exista en BD y envía enlace de reset.
     * Por seguridad, retorna mensaje genérico sin revelar si email existe.
     * 
     * @param  Request  $request  Datos: email
     * @return RedirectResponse  Redirección con mensaje genérico
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink(
            $request->only('email')
        );

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
