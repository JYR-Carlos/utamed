<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\BitacoraCursoEstudiante;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitácora del curso (T41): las agendas de todas las actividades del alumno
 * en ese curso, en un solo flujo cronológico.
 */
class BitacoraController extends Controller
{
    /**
     * GET estudiante/cursos/{curso}/bitacora
     */
    public function show(Curso $curso, BitacoraCursoEstudiante $bitacora): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();
        $estudiante = $user->estudiante;

        abort_unless($estudiante, 403);

        $inscrito = $estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->exists();

        abort_unless($inscrito, 403, 'No estás inscrito en este curso.');

        $curso->load('asignacionPlan.asignatura');

        return Inertia::render('student/Courses/Bitacora', array_merge(
            [
                'curso' => [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? $curso->nombre,
                    'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura,
                ],
            ],
            $bitacora->compilar($curso, $estudiante),
        ));
    }
}
