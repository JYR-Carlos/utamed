<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Log;
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

        Log::channel('single')->info('[LOGIN REDIRECT] Determinando redirección', [
            'id'               => $user->id_usuario,
            'username'         => $user->username,
            'tiene_docente'    => (bool) $user->docente,
            'tiene_estudiante' => (bool) $user->estudiante,
        ]);

        try {
            // Redirect based on role
            if ($user->docente) {
                Log::channel('single')->info('[LOGIN REDIRECT] → docente.dashboard');
                return redirect()->route('docente.dashboard');
            }

            // Logic for Admin (not docente, not estudiante)
            if (!$user->estudiante) {
                Log::channel('single')->info('[LOGIN REDIRECT] → admin.usuarios.index');
                return redirect()->route('admin.usuarios.index');
            }

            // Fallback to default dashboard
            Log::channel('single')->info('[LOGIN REDIRECT] → fortify.home fallback');
            return redirect()->intended(config('fortify.home'));
        } catch (\Throwable $e) {
            Log::channel('single')->error('[LOGIN REDIRECT] Excepción en redirección', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
