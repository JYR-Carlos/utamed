<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
/**
 * Middleware for validating that the authenticated user is a student.
 */
class IsStudent
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $isStudent = $user->hasRole('Estudiante');

        if (!$isStudent) {
            return redirect('/dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        return $next($request);
    }
}
