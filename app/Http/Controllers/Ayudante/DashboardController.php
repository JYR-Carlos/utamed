<?php

namespace App\Http\Controllers\Ayudante;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
class DashboardController extends Controller
{
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Get available courses where they are assistant
        $contextosAsignados = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereIn('nombre', ['Ayudante', 'ayudante'])
            ->pluck('id_contexto');

        $ayudanteCourses = \App\Models\Curso\Curso::whereIn('id_contexto', $contextosAsignados)
            ->with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera'])
            ->get()
            ->map(function ($curso) {
                return [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => [
                'usuarios' => \App\Models\Usuario\Usuario::count(),
                'cursos_total' => \App\Models\Curso\Curso::count(),
                'cursos_pendientes' => \App\Models\Curso\Curso::where('estado_interno', 'ABIERTO')
                    ->where(function ($query) {
                        $query->where('estado_acta', '!=', 'ENVIADO')
                            ->orWhereNull('estado_acta');
                    })
                    ->count(),
                'facultades' => \App\Models\Administrativo\Facultad::count(),
                'carreras' => \App\Models\Administrativo\Carrera::count(),
            ],
            'ayudanteCourses' => $ayudanteCourses
        ]);
        // Note: We might want a specific 'ayudante/Dashboard' view later, 
        // but for now reusing 'Dashboard' with role detection is consistent with current app.
        // Actually, the plan said "Create frontend views", so maybe we should use a specific one 
        // OR rely on Dashboard.svelte handling 'isAyudante'. 
        // Dashboard.svelte ALREADY has <DashboardAyudante> component import.
        // Let's stick to Dashboard.svelte for now it seems to handle roles.
    }
}
