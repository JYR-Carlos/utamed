<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
class CourseController extends Controller
{
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Obtener inscripciones del estudiante
        $inscripciones = $estudiante->inscripcionCursos()
            ->where('estado_inscripcion', 'INSCRITO') // Asumiendo estado para cursos activos
            ->with(['curso.asignacionPlan.asignatura', 'curso.asignacionPlan.plan.carrera'])
            ->get();

        $cursosData = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;
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

        return Inertia::render('student/Courses/Index', [
            'cursos' => $cursosData,
        ]);
    }

    public function show(Curso $curso)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Verificar que el estudiante está inscrito en este curso
        $inscripcion = $estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->first();

        if (!$inscripcion) {
            abort(403, 'No estás inscrito en este curso');
        }

        // Cargar curso con relaciones
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'secciones.tipoSeccion',
            'secciones.docente'
        ]);

        // Formatear datos del curso
        $cursoData = [
            'id_curso' => $curso->id_curso,
            'nombre' => $curso->nombre,
            'cod_curso' => $curso->cod_curso,
            'fecha_inicio' => $curso->fecha_inicio,
            'fecha_fin' => $curso->fecha_fin,
            'asignatura' => [
                'id_asignatura' => $curso->asignacionPlan?->asignatura?->id_asignatura,
                'nombre' => $curso->asignacionPlan?->asignatura?->nombre,
                'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura,
                'descripcion' => $curso->asignacionPlan?->asignatura?->descripcion,
                'creditos_sct' => $curso->asignacionPlan?->asignatura?->creditos_sct,
            ],
            'carrera' => [
                'id_carrera' => $curso->asignacionPlan?->plan?->carrera?->id_carrera,
                'nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre,
            ],
            'secciones' => $curso->secciones->map(function ($seccion) {
                return [
                    'id_seccion' => $seccion->id_seccion,
                    'tipo_seccion' => [
                        'id_tipo_seccion' => $seccion->tipoSeccion?->id_tipo_seccion,
                        'tipo' => $seccion->tipoSeccion?->tipo,
                    ],
                    'docente' => $seccion->docente ? [
                        'id_docente' => $seccion->docente->id_docente,
                        'nombre_completo' => $seccion->docente->usuario?->nombre1 . ' ' . $seccion->docente->usuario?->apellido1,
                    ] : null,
                ];
            })->values(),
        ];

        return Inertia::render('student/Courses/Show', [
            'curso' => $cursoData
        ]);
    }
}
