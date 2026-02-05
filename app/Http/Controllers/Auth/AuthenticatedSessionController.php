<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador para gestionar sesiones autenticadas (login).
 * 
 * Gestiona el flujo de autenticación:
 * - Mostrar formulario de login (crear sesión)
 * - Procesar credenciales y crear sesión autenticada (guardar sesión)
 * - Cierre de sesión y limpieza de tokens (destruir sesión)
 * 
 * Usa Inertia.js para renderizar vistas frontend.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Muestra la página de login.
     * 
     * Renderiza el formulario de autenticación con opciones de recuperación de contraseña.
     * 
     * @param  Request  $request  Solicitud HTTP (puede contener mensaje de estado)
     * @return Response  Vista Inertia con formulario de login
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Procesa y autentica una solicitud de login.
     * 
     * Valida credenciales usando LoginRequest, regenera sesión por seguridad,
     * y redirige al dashboard o página intencionada.
     * 
     * @param  LoginRequest  $request  Request validado con credenciales
     * @return RedirectResponse  Redirección a dashboard
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Cierra la sesión autenticada actual.
     * 
     * Logout del usuario, invalida sesión y regenera token CSRF para seguridad.
     * 
     * @param  Request  $request  Solicitud HTTP
     * @return RedirectResponse  Redirección a página principal
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
