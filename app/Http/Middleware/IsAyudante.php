<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAyudante
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect('/login');
        }

        // Check if user has ANY active role assignment with 'Ayudante' role
        // We need to check rolesAsignados relationship
        $isAyudante = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', function ($query) {
                $query->whereIn('nombre', ['Ayudante', 'ayudante']);
            })
            ->exists();

        if (!$isAyudante) {
            return redirect('/dashboard')->with('error', 'No tienes permisos de Ayudante.');
        }

        return $next($request);
    }
}
