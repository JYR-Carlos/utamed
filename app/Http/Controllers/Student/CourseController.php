<?php

namespace App\Http\Controllers\Student;

use App\Enums\DB\TipoActividad;
use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Services\Student\BitacoraCursoEstudiante;
use App\Services\Student\MensajeriaCursoEstudiante;
use App\Services\Student\RendimientoCursoEstudiante;
use App\Services\Student\StudentSyllabusPresenter;
use App\Traits\ParsesSyllabus;
use Carbon\Carbon;
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
    use ParsesSyllabus;

    private const DEFAULT_COURSE_IMAGE = '/img/utamed.webp';


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

        // Parámetros desde la URL. Sin ellos se muestra el período vigente:
        // antes el semestre por defecto era siempre 1 y, en el segundo
        // semestre, «Mis Cursos» decía que no había cursos (T03).
        $semestre = (int) $request->input('semestre', now()->month <= 6 ? 1 : 2);
        $agno     = (int) $request->input('agno', now()->year);

        // Obtener inscripciones filtradas por Semestre y Año del Curso
        $inscripciones = $estudiante->inscripcionCursos()
            ->when($semestre, function ($query) use ($semestre) {
                $query->whereHas('curso', function ($query) use ($semestre) {
                    $query->where('semestre_real', $semestre);
                });
            })
            ->when($agno, function ($query) use ($agno) {
                $query->whereHas('curso', function ($query) use ($agno) {
                    $query->whereYear('fecha_inicio', $agno);
                });
            })
            ->with([
                'curso',
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                'curso.programas' => fn ($query) => $query->where('es_actual', true)
                    ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO']),
            ])
            ->get();

        $cursosEstudiante = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;
            return $this->formatCurso($curso, 'Estudiante');
        });

        $semestreActual = now()->month <= 6 ? 1 : 2;
        $agnoActual = now()->year;

        return Inertia::render('student/Courses/Index', [
            'cursos'   => $cursosEstudiante,
            'semestre' => $semestre,
            'agno'     => $agno,
            'periodoActual' => [
                'semestre' => $semestreActual,
                'agno' => $agnoActual,
            ],
        ]);
    }

    private function formatCurso(Curso $curso, string $rol): array
    {
        $tieneProg = $curso->relationLoaded('programas')
            ? $curso->programas->isNotEmpty()
            : Programa::where('id_curso', $curso->id_curso)
                ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
                ->where('es_actual', true)
                ->exists();
        $default_img = self::DEFAULT_COURSE_IMAGE;
        return [
            'id_curso'         => $curso->id_curso,
            'nombre'           => $curso->nombre,
            'cod_curso'        => $curso->cod_curso,
            'asignatura_nombre'=> $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            'etiqueta'         => $curso->etiqueta(),
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

        $actividadIds = $actividades->pluck('id_actividad');

        // Grupos asignados al estudiante para estas actividades
        $gruposDelEstudiante = ActividadAsignadaGrupo::whereIn('id_actividad', $actividadIds)
            ->whereHas('miembros', fn($q) => $q->where('id_estudiante', $estudiante->id_estudiante))
            ->get()
            ->keyBy('id_actividad');

        $idsGrupos = $gruposDelEstudiante->pluck('id_actividad_asignada_grupo');

        // Consultas en lote para evitar N+1 por cada actividad del curso:
        // 1. Última entrega vigente por grupo
        $entregasVigentesPorGrupo = $idsGrupos->isEmpty()
            ? collect()
            : Agenda::entregasVigentes()
                ->whereIn('id_actividad_asignada_grupo', $idsGrupos)
                ->select('id_actividad_asignada_grupo', DB::raw('MAX(fecha_envio) as max_fecha_envio'))
                ->groupBy('id_actividad_asignada_grupo')
                ->pluck('max_fecha_envio', 'id_actividad_asignada_grupo');

        // 2. Grupos evaluados (por nota individual en integrante o por evaluación en agenda)
        $gruposConEvaluacion = $idsGrupos->isEmpty()
            ? collect()
            : collect(
                IntegranteGrupo::whereIn('id_actividad_asignada_grupo', $idsGrupos)
                    ->whereNotNull('nota_individual')
                    ->pluck('id_actividad_asignada_grupo')
            )->merge(
                Agenda::whereIn('id_actividad_asignada_grupo', $idsGrupos)
                    ->whereHas('evaluacion')
                    ->pluck('id_actividad_asignada_grupo')
            )->unique()->flip();

        $actividadesData = $actividades->map(function (Actividad $actividad) use ($gruposDelEstudiante, $entregasVigentesPorGrupo, $gruposConEvaluacion) {
            /** @var mixed $grupo ActividadAsignadaGrupo|null */
            $grupo = $gruposDelEstudiante->get($actividad->id_actividad);
            $estado = $grupo ? $grupo->calcularEstadoGrupo($actividad) : $actividad->calcularEstadoBase();

            $fechaEnvio = $grupo ? $entregasVigentesPorGrupo->get($grupo->id_actividad_asignada_grupo) : null;
            $entregada = $fechaEnvio !== null;
            $yaEvaluada = $grupo !== null && ($grupo->nota !== null || $gruposConEvaluacion->has($grupo->id_actividad_asignada_grupo));

            return [
                'id_actividad'    => $actividad->id_actividad,
                'nombre'          => $actividad->nombre,
                'es_sumativa'     => $actividad->tipo_actividad === TipoActividad::SUMATIVA,
                // «Cómo se evalúa» cuenta las actividades de cada componente.
                'componente_tipo' => $actividad->componente?->tipoComponente?->tipo,
                'con_entrega'     => $actividad->tipo_entrega !== 'SIN_ENTREGA',
                'es_grupal'       => (bool) $actividad->es_grupal,
                'max_integrantes' => $actividad->max_integrantes ?? 1,
                'fecha_limite'    => $actividad->fecha_limite?->format('Y-m-d'),
                'visible'         => (bool) $actividad->visible,
                'estado'          => $estado,
                'ya_entregada'    => $entregada,
                // Día del último envío vigente; la tarjeta dice «Entregada el …».
                'fecha_entrega'   => $fechaEnvio ? Carbon::parse($fechaEnvio)->format('Y-m-d') : null,
                'ya_evaluada'     => $yaEvaluada,
            ];
        })->values();

        $syllabus = StudentSyllabusPresenter::build($curso, $estudiante);

        // Las pestañas «Programa» y «Biblioteca» muestran el documento íntegro,
        // igual que la página dedicada; la ficha ya no lo resume.
        $secciones = [];
        if ($syllabus['programa']) {
            $data = Programa::find($syllabus['programa']['id_programa'])?->data_syllabus;
            $data = is_array($data) ? $data : json_decode($data ?? '[]', true);
            $secciones = $this->parseSecciones($data ?? []);
        }

        return Inertia::render('student/Courses/Show', array_merge(
            [
                'secciones' => $secciones,
                // Panel «Rendimiento» (T30): se calcula sólo cuando el alumno
                // lo abre (router.reload con only: ['rendimiento']).
                'rendimiento' => Inertia::lazy(fn () => app(RendimientoCursoEstudiante::class)
                    ->calcular($curso, $estudiante, $syllabus['datos']['componentes'] ?? [])),
                // Pestañas «Mensajería» y «Bitácora»: igual que el rendimiento,
                // se cargan al abrirlas. Abrir la mensajería marca como leído.
                'mensajeria' => Inertia::lazy(fn () => app(MensajeriaCursoEstudiante::class)
                    ->bandeja($curso, (int) $user->id_usuario, request()->integer('componente_id') ?: null)),
                'bitacora' => Inertia::lazy(fn () => app(BitacoraCursoEstudiante::class)
                    ->compilar($curso, $estudiante)),
                'curso' => [
                    'id_curso'          => $curso->id_curso,
                    'nombre'            => $curso->nombre,
                    'cod_curso'         => $curso->cod_curso,
                    'cod_asignatura'    => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? '',
                    'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                    'etiqueta'          => $curso->etiqueta(),
                    'carrera_nombre'    => $curso->asignacionPlan?->plan?->carrera?->nombre ?? '',
                    'letra_grupo'       => $curso->letra_grupo,
                    'semestre_real'     => $curso->semestre_real,
                    'agno_real'         => $curso->agno_real,
                    'unidades'          => $curso->unidades_real,
                    // Créditos y horas: la ficha del encabezado los muestra junto
                    // al período, y el programa embebido los daba vacíos.
                    'asignatura'        => $curso->asignacionPlan?->asignatura,
                ],
                'actividades' => $actividadesData,
            ],
            $syllabus
        ));
    }
}
