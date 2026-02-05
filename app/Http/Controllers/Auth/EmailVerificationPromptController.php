<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador invocable para verificación de email.
 * 
 * Verifica si usuario ya tiene email verificado; si no, muestra página de verificación.
 */
class EmailVerificationPromptController extends Controller
{
    /**
     * Muestra página de verificación de email si aún no está verificado.
     * 
     * Si email ya está verificado, redirige a dashboard.
     * Si no está verificado, muestra página con opción de reenviar enlace.
     * 
     * @param  Request  $request  Solicitud HTTP
     * @return RedirectResponse|Response  Redirección a dashboard o vista de verificación
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : Inertia::render('auth/VerifyEmail', ['status' => $request->session()->get('status')]);
    }
}
