<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador para confirmar contraseña del usuario autenticado.
 * 
 * Proporciona formulario de re-confirmación de contraseña para operaciones sensibles.
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Muestra página para confirmar contraseña.
     * 
     * Renderiza formulario donde usuario debe ingresar su contraseña actual.
     * 
     * @return Response  Vista Inertia con formulario
     */
    public function show(): Response
    {
        return Inertia::render('auth/ConfirmPassword');
    }

    /**
     * Valida contraseña actual del usuario y marca sesión como contraseña confirmada.
     * 
     * Valida que contraseña ingresada coincida con la del usuario autenticado.
     * Si es válida, registra timestamp 'auth.password_confirmed_at' en sesión.
     * 
     * @param  Request  $request  Datos: password
     * @return RedirectResponse  Redirección a dashboard o página intencionada
     * @throws ValidationException  Si contraseña no coincide
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
