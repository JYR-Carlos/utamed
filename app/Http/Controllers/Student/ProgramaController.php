<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProgramaController extends Controller
{
    /**
     * Ver programa aprobado de un curso
     */
    public function show(Curso $curso)
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que estudiante está inscrito en este curso
        if (!$user->estudiante) {
            return redirect()->route('estudiante.dashboard')
                ->with('error', 'No tienes un perfil de estudiante');
        }

        $inscripcion = InscripcionCurso::where('id_estudiante', $user->estudiante->id_estudiante)
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->first();

        if (!$inscripcion) {
            return redirect()->route('estudiante.cursos.index')
                ->with('error', 'No estás inscrito en este curso');
        }

        // Obtener programa aprobado
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('estado', 'APROBADO')
            ->where('es_actual', true)
            ->first();

        // Si no hay programa, mostrar página con aviso en lugar de 404
        if (!$programa) {
            // Cargar relaciones para mostrar la información del curso
            $curso->load([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ]);

            $cursoData = [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura' => $curso->asignacionPlan?->asignatura,
                'carrera' => $curso->asignacionPlan?->plan?->carrera,
            ];

            return Inertia::render('student/Courses/Programa', [
                'programa' => null,
                'curso' => $cursoData,
            ]);
        }

        // Cargar relaciones
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera'
        ]);
        $programa->load('autor');

        // Formatear datos
        $programaData = [
            'id_programa' => $programa->id_programa,
            'id_curso' => $programa->id_curso,
            'version' => $programa->version_programa,
            'estado' => $programa->estado,
            'secciones' => $programa->data_syllabus['secciones'] ?? [],
            'creado_por' => $programa->autor?->nombre,
            'fecha_creacion' => $programa->fecha_creacion,
        ];

        $cursoData = [
            'id_curso' => $curso->id_curso,
            'nombre' => $curso->nombre,
            'cod_curso' => $curso->cod_curso,
            'asignatura' => $curso->asignacionPlan?->asignatura,
            'carrera' => $curso->asignacionPlan?->plan?->carrera,
        ];

        return Inertia::render('student/Courses/Programa', [
            'programa' => $programaData,
            'curso' => $cursoData,
        ]);
    }
}
