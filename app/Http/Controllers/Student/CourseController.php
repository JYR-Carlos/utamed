<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Services\Student\StudentSyllabusPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cursos del estudiante: listado por período y detalle de un curso inscrito.
 *
 * Todas las acciones exigen un perfil de estudiante y, en el detalle, una
 * inscripción activa (`INSCRITO`) en el curso.
 */
class CourseController extends Controller
{
    private const DEFAULT_COURSE_IMAGE = '/images/default-course.png';


    /**
     * Lista los cursos del estudiante para un semestre/año.
     *
     * GET estudiante/cursos
     */
    public function index(Request $request): RedirectResponse|Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Parámetros desde la URL
        $semestre = (int) $request->input('semestre', 1);
        $agno     = (int) $request->input('agno', now()->year);

        // Obtener inscripciones filtradas por Semestre y Año del Curso
        $inscripciones = $estudiante->inscripcionCursos()
            ->whereHas('curso', function ($query) use ($semestre, $agno) {
                $query->where('semestre_real', $semestre)
                    ->whereYear('fecha_inicio', $agno); 
            })
            ->with([
                'curso',
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera'
            ])
            ->get();

        $cursosEstudiante = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;
            return $this->formatCurso($curso, 'Estudiante');
        });

        return Inertia::render('student/Courses/Index', [
            'cursos'   => $cursosEstudiante,
            'semestre' => $semestre,
            'agno'     => $agno,
        ]);
    }

    private function formatCurso(Curso $curso, string $rol): array
    {
        $tieneProg = Programa::where('id_curso', $curso->id_curso)
            ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
            ->where('es_actual', true)
            ->exists();
        $default_img = self::DEFAULT_COURSE_IMAGE;
        return [
            'id_curso'         => $curso->id_curso,
            'nombre'           => $curso->nombre,
            'cod_curso'        => $curso->cod_curso,
            'asignatura_nombre'=> $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            'carrera_nombre'   => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
            'fecha_inicio'     => $curso->fecha_inicio,
            'fecha_fin'        => $curso->fecha_fin,
            'semestre_real'    => $curso->semestre_real,
            'agno_real'        => $curso->agno_real,
            'letra_grupo'      => $curso->letra_grupo,
            'rol'              => $rol,
            'tiene_programa'   => $tieneProg,
            'imagen_url'       => $curso->imagen_url ?? $default_img,
        ];
    }

    /**
     * Muestra el detalle de un curso inscrito y sus actividades visibles.
     *
     * GET estudiante/cursos/{curso}
     */
    public function show(Curso $curso): RedirectResponse|Response
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
        ]);

        // Obtener IDs de componentes en los que está inscrito el alumno
        $componenteIds = DB::table('curso.inscripcion_componente as ic')
            ->join('curso.componente as c', 'c.id_componente', '=', 'ic.id_componente')
            ->where('ic.id_estudiante', $estudiante->id_estudiante)
            ->where('c.id_curso', $curso->id_curso)
            ->pluck('ic.id_componente');

        if ($componenteIds->isEmpty()) {
            $componenteIds = DB::table('curso.componente')
                ->where('id_curso', $curso->id_curso)
                ->pluck('id_componente');
        }

        // Obtener actividades visibles de los componentes del alumno
        $actividades = Actividad::whereIn('id_componente', $componenteIds)
            ->where('visible', true)
            ->with(['componente.tipoComponente', 'unidad'])
            ->orderBy('fecha_limite', 'asc')
            ->get();

        $actividadesData = $actividades->map(fn (Actividad $actividad) => [
            'id_actividad'    => $actividad->id_actividad,
            'nombre'          => $actividad->nombre,
            'es_sumativa'     => $actividad->tipo_actividad === 'SUMATIVA',
            'con_entrega'     => $actividad->tipo_entrega !== 'SIN_ENTREGA',
            'es_grupal'       => (bool) $actividad->es_grupal,
            'max_integrantes' => $actividad->max_integrantes ?? 1,
            'fecha_limite'    => $actividad->fecha_limite,
            'visible'         => (bool) $actividad->visible,
        ])->values();

        return Inertia::render('student/Courses/Show', array_merge(
            [
                'curso' => [
                    'id_curso'          => $curso->id_curso,
                    'nombre'            => $curso->nombre,
                    'cod_curso'         => $curso->cod_curso,
                    'cod_asignatura'    => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? '',
                    'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                    'semestre_real'     => $curso->semestre_real,
                    'agno_real'         => $curso->agno_real,
                    'unidades'          => $curso->unidades_real,
                ],
                'actividades' => $actividadesData,
            ],
            StudentSyllabusPresenter::build($curso, $estudiante)
        ));
    }
}
