<?php

namespace App\Http\Controllers\Ayudante;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
class CourseController extends Controller
{
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Obtener cursos donde es Ayudante
        $contextosAsignados = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereIn('nombre', ['ayudante'])
            ->pluck('id_contexto');

        $cursosInscritos = \App\Models\Curso\Curso::whereIn('id_contexto', $contextosAsignados)
            ->with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera'])
            ->get();

        $cursosData = $cursosInscritos->map(function ($curso) {
            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
            ];
        });

        return Inertia::render('ayudante/Courses/Index', [
            'cursos' => $cursosData,
        ]);
    }

    public function show(int $id)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Obtener el curso
        $curso = \App\Models\Curso\Curso::findOrFail($id);

        // Verificar que el usuario es ayudante en este curso
        $esAyudante = \App\Models\Usuario\UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->where('nombre', 'ayudante'))
            ->exists();

        if (!$esAyudante) {
            abort(403, 'No tienes permiso para ver este curso');
        }

        $cursoData = [
            'id_curso' => $curso->id_curso,
            'nombre' => $curso->nombre,
            'cod_curso' => $curso->cod_curso,
            'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
            'fecha_inicio' => $curso->fecha_inicio,
            'fecha_fin' => $curso->fecha_fin,
        ];

        $tienePrograma = \App\Models\Administrativo\Programa::where('id_curso', $id)->exists();

        return Inertia::render('ayudante/Courses/Show', [
            'id_curso' => $id,
            'curso' => $cursoData,
            'tiene_programa' => $tienePrograma
        ]);
    }
}
