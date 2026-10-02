<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage the authenticated user's profile settings (view, update). The account cannot be self-deleted.
 *
 * El estudiante no usa esta pantalla: sus datos institucionales (nombre,
 * correo, RUT…) vienen de la Intranet y son de sólo lectura (T12). Su ficha
 * vive en /estudiante/perfil.
 */
class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        if ($request->user()->esSoloEstudiante()) {
            return to_route('estudiante.perfil');
        }

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Defensa en la API, no sólo en la interfaz: sin esto un alumno podía
        // cambiar su correo institucional con un PATCH directo.
        abort_if(
            $request->user()->esSoloEstudiante(),
            403,
            'Tus datos institucionales vienen de la Intranet y no se pueden editar.',
        );

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }
}
