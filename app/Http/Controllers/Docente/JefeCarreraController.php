<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class JefeCarreraController extends Controller
{
    public function dashboard()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->docente) {
            return redirect('/docente/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $jefatura = $this->resolveJefatura($user);

        if (!$jefatura) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        return Inertia::render('jefe-carrera/Dashboard', [
            'carrera' => [
                'nombre' => $jefatura['carrera_nombre'] ?? 'Diseño Multimedia',
                'semestre' => now()->month <= 6 ? 'Primero' : 'Segundo',
                'ano' => (int) now()->year,
            ],
        ]);
    }

    public function seguimiento()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->docente) {
            return redirect('/docente/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $jefatura = $this->resolveJefatura($user);

        if (!$jefatura) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        return Inertia::render('jefe-carrera/Seguimiento', [
            'carrera' => [
                'nombre' => $jefatura['carrera_nombre'] ?? 'Diseño Multimedia',
            ],
        ]);
    }

    public function aprobarPrograma(int $programaId)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$this->resolveJefatura($user)) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        return back()->with('success', "Solicitud de aprobación enviada para el programa #{$programaId}.");
    }

    public function rechazarPrograma(Request $request, int $programaId)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$this->resolveJefatura($user)) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        $request->validate([
            'notas' => ['nullable', 'string', 'max:3000'],
        ]);

        return back()->with('success', "Solicitud de cambios enviada para el programa #{$programaId}.");
    }

    private function resolveJefatura(Usuario $user): ?array
    {
        $asignacion = UsuarioRolAsignacion::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->where('nombre', 'Jefe de Carrera'))
            ->whereHas('contexto.tipoContexto', fn($q) => $q->where('categoria', 'carrera'))
            ->latest('id_ura')
            ->first();

        if (!$asignacion) {
            return null;
        }

        $carrera = Carrera::query()
            ->select('id_carrera', 'nombre', 'id_contexto')
            ->where('id_contexto', $asignacion->id_contexto)
            ->first();

        return [
            'id_contexto' => $asignacion->id_contexto,
            'carrera_id' => $carrera?->id_carrera,
            'carrera_nombre' => $carrera?->nombre,
        ];
    }
}
