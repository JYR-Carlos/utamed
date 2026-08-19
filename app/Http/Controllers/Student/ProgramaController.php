<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Services\Student\StudentSyllabusPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista del programa/syllabus de un curso para el estudiante.
 *
 * Sólo muestra programas visibles para alumnos (estado `APROBADO`, con
 * preferencia sobre `BASICO_COMPLETO`) y exige inscripción activa en el curso.
 */
class ProgramaController extends Controller
{
    /**
     * Muestra el programa visible del curso (o un aviso si no existe).
     *
     * GET estudiante/cursos/{curso}/programa
     */
    public function show(Curso $curso): RedirectResponse|Response
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

        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
        ]);

        $cursoData = [
            'id_curso'   => $curso->id_curso,
            'nombre'     => $curso->nombre,
            'cod_curso'  => $curso->cod_curso,
            'asignatura' => $curso->asignacionPlan?->asignatura,
            'carrera'    => $curso->asignacionPlan?->plan?->carrera,
        ];

        return Inertia::render('student/Courses/Syllabus', array_merge(
            ['curso' => $cursoData],
            StudentSyllabusPresenter::build($curso, $user->estudiante)
        ));
    }

}
