<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador para reenviar notificación de verificación de email.
 * 
 * Envía (o reenvía) enlace de verificación al email del usuario autenticado.
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * Envía nueva notificación de verificación de email al usuario.
     * 
     * Si email ya está verificado, redirige a dashboard sin hacer nada.
     * Si no está verificado, envía nueva notificación de verificación.
     * 
     * @param  Request  $request  Solicitud HTTP
     * @return RedirectResponse  Redirección con mensaje de resultado
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
