<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Controlador invocable para marcar email como verificado.
 * 
 * Procesa el enlace de verificación de email enviado al usuario.
 */
class VerifyEmailController extends Controller
{
    /**
     * Marca el email del usuario como verificado (procesa enlace de validación).
     * 
     * Valida que el request sea EmailVerificationRequest (token y hash correctos).
     * Marca email como verificado en BD y redirige a dashboard con flag verified=1.
     * 
     * @param  EmailVerificationRequest  $request  Request validado con token y email
     * @return RedirectResponse  Redirección a dashboard con parámetro verified=1
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        $request->fulfill();

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
