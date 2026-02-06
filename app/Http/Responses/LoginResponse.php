<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $user = auth()->user();

        // Redirect based on role
        if ($user->docente) {
            return redirect()->route('docente.dashboard');
        }

        // Logic for Admin (not docente, not estudiante)
        if (!$user->estudiante) {
            return redirect()->route('admin.usuarios.index');
        }

        // Fallback to default dashboard
        return redirect()->intended(config('fortify.home'));
    }
}
