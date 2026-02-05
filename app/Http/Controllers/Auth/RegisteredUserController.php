<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador para gestionar el registro de nuevos usuarios (no utilizado en UTAMED).
 * 
 * Proporciona formulario de auto-registro y procesamiento de nuevas cuentas.
 * NOTA: En UTAMED, la creación de usuarios es gestionada por administradores
 * a través de UsuarioController, no mediante registro público.
 */
class RegisteredUserController extends Controller
{
    /**
     * Muestra la página de registro de usuario.
     * 
     * Renderiza formulario para que visitantes se registren.
     * 
     * @return Response  Vista Inertia con formulario de registro
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Procesa solicitud de registro: crea usuario, dispara evento Registered, autentica.
     * 
     * Valida datos (nombre, email único, contraseña con reglas por defecto),
     * crea usuario en tabla users, dispara evento para notificaciones, autentica.
     * 
     * @param  Request  $request  Datos: name, email, password, password_confirmation
     * @return RedirectResponse  Redirección a dashboard
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
