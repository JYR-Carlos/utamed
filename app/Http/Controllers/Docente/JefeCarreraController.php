<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Docente\JefeCarrera\ResolvesJefaturaCarrera;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Syllabus\SyllabusData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel del Jefe de Carrera: dashboard, seguimiento de syllabus y métricas,
 * más la previsualización y aprobación/rechazo de programas de la carrera.
 *
 * Todas las acciones quedan acotadas a la carrera sobre la que el usuario tiene
 * el rol "Jefe de Carrera" activo (ver trait ResolvesJefaturaCarrera). Las vistas
 * Inertia redirigen al dashboard del docente cuando no hay jefatura; los endpoints
 * JSON abortan con 403.
 */
class JefeCarreraController extends Controller
{
    use ResolvesJefaturaCarrera;

    /** Paleta determinística para avatares de docentes. */
    private const DOCENTE_COLORS = [
        '#6366F1', '#F59E0B', '#10B981', '#3B82F6', '#EC4899', '#8B5CF6', '#EF4444',
    ];

    /**
     * Dashboard de la carrera: estados de syllabus, métricas y alertas del período vigente.
     *
     * GET docente/jefe-carrera/dashboard
     */
    public function dashboard(): RedirectResponse|Response
    {
        $jefatura = $this->jefaturaOrRedirect();

        if ($jefatura instanceof RedirectResponse) {
            return $jefatura;
        }

        $carreraId = $jefatura['carrera_id'];

        // Período vigente y cursos del período
        $periodo = $this->resolvePeriodoVigente($carreraId);
        $cursosPeriodo = $this->cursosCarreraQuery($carreraId)
            ->when($periodo, fn($q) => $q
                ->where('agno_real', $periodo['ano'])
                ->where('semestre_real', $periodo['sem']))
            ->with(['asignacionPlan.asignatura', 'docenteTitular.usuario', 'programas'])
            ->get();

        // Estados de syllabus
        $resumen = ['no_iniciado' => 0, 'en_revision' => 0, 'aprobado' => 0];
        $syllabusEntregados = 0;
        foreach ($cursosPeriodo as $curso) {
            $estado = $this->estadoSyllabusUi($curso);
            if ($estado === 'NO_INICIADO') {
                $resumen['no_iniciado']++;
            } elseif ($estado === 'EN_REVISION') {
                $resumen['en_revision']++;
            } elseif ($estado === 'APROBADO') {
                $resumen['aprobado']++;
                $syllabusEntregados++;
            }
        }

        $cursoIds = $cursosPeriodo->pluck('id_curso')->all();
        $estudiantesMatriculados = empty($cursoIds) ? 0
            : (int) DB::connection('pgsql')->table('curso.inscripcion_curso')
                ->whereIn('id_curso', $cursoIds)
                ->distinct()
                ->count('id_estudiante');

        $metricas = $this->metricasResumen($cursoIds);

        $alertas = $this->construirAlertas($resumen, $metricas['alumnos_en_riesgo']);

        return Inertia::render('jefe-carrera/Dashboard', [
            'carrera' => [
                'nombre' => $jefatura['carrera_nombre'] ?? 'Carrera',
                'semestre' => $periodo ? ($periodo['sem'] === 1 ? 'Primero' : 'Segundo')
                    : (now()->month <= 6 ? 'Primero' : 'Segundo'),
                'ano' => $periodo['ano'] ?? (int) now()->year,
            ],
            'stats' => [
                'syllabus_entregados' => $syllabusEntregados,
                'syllabus_total' => $cursosPeriodo->count(),
                'cursos_activos' => $cursosPeriodo->count(),
                'cursos_tendencia' => $this->cursosTendencia($carreraId),
                'estudiantes_matriculados' => $estudiantesMatriculados,
            ],
            'resumen_estados' => $resumen,
            'alertas' => $alertas,
            'metricas_resumen' => [
                'asistencia_promedio' => $metricas['asistencia_promedio'],
                'avance_evaluacion' => $metricas['avance_evaluacion'],
                'alumnos_en_riesgo' => $metricas['alumnos_en_riesgo'],
                'carga_docente' => $metricas['carga_docente'],
            ],
        ]);
    }

    /**
     * Listado paginado y filtrable de cursos de la carrera con su estado de syllabus.
     *
     * GET docente/jefe-carrera/seguimiento
     */
    public function seguimiento(Request $request): RedirectResponse|Response
    {
        $jefatura = $this->jefaturaOrRedirect();

        if ($jefatura instanceof RedirectResponse) {
            return $jefatura;
        }

        $carreraId = $jefatura['carrera_id'];

        $q = trim((string) $request->query('q', ''));
        $semestre = (string) $request->query('semestre', '');
        $agno = (string) $request->query('agno', '');
        $estadoUi = (string) $request->query('estado', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;

        $query = $this->cursosCarreraQuery($carreraId)
            ->with(['asignacionPlan.asignatura', 'docenteTitular.usuario', 'programas']);

        // Filtro: año
        if ($agno !== '') {
            $query->where('agno_real', (int) $agno);
        }

        // Filtro: semestre (Primero=1, Segundo=2)
        if ($semestre !== '') {
            $semNum = $semestre === 'Primero' ? 1 : ($semestre === 'Segundo' ? 2 : null);
            if ($semNum !== null) {
                $query->where('semestre_real', $semNum);
            }
        }

        // Filtro: búsqueda por código/nombre de asignatura o nombre del docente
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereHas('asignacionPlan.asignatura', function ($a) use ($q) {
                    $a->where('nombre', 'ilike', "%{$q}%")
                        ->orWhere('cod_asignatura', 'ilike', "%{$q}%");
                })->orWhereHas('docenteTitular.usuario', function ($u) use ($q) {
                    $u->where('nombre1', 'ilike', "%{$q}%")
                        ->orWhere('apellido1', 'ilike', "%{$q}%");
                });
            });
        }

        // Materializar y mapear (el estado de syllabus se deriva en PHP)
        $cursos = $query->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->get()
            ->map(fn($curso) => $this->mapCursoSeguimiento($curso));

        // Filtro por estado de syllabus (post-map, ya que es derivado)
        if ($estadoUi !== '') {
            $cursos = $cursos->where('estado_syllabus', $estadoUi)->values();
        }

        $total = $cursos->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $paged = $cursos->forPage($page, $perPage)->values();

        // Años disponibles (todos los cursos de la carrera)
        $agnosDisponibles = $this->cursosCarreraQuery($carreraId)
            ->select('agno_real')
            ->whereNotNull('agno_real')
            ->distinct()
            ->orderByDesc('agno_real')
            ->pluck('agno_real')
            ->map(fn($a) => (int) $a)
            ->all();

        return Inertia::render('jefe-carrera/Seguimiento', [
            'cursos' => $paged,
            'semestres_disponibles' => ['Primero', 'Segundo'],
            'agnos_disponibles' => $agnosDisponibles,
            'filters' => [
                'q' => $q,
                'semestre' => $semestre,
                'agno' => $agno,
                'estado' => $estadoUi,
            ],
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
            ],
            'carrera' => [
                'nombre' => $jefatura['carrera_nombre'] ?? 'Carrera',
            ],
        ]);
    }

    /**
     * Métricas de la carrera por curso: asistencia, avance de evaluación, riesgo y carga docente.
     *
     * GET docente/jefe-carrera/metricas
     */
    public function metricas(): RedirectResponse|Response
    {
        $jefatura = $this->jefaturaOrRedirect();

        if ($jefatura instanceof RedirectResponse) {
            return $jefatura;
        }

        $carreraId = $jefatura['carrera_id'];
        $periodo = $this->resolvePeriodoVigente($carreraId);

        $cursos = $this->cursosCarreraQuery($carreraId)
            ->when($periodo, fn($q) => $q
                ->where('agno_real', $periodo['ano'])
                ->where('semestre_real', $periodo['sem']))
            ->with(['asignacionPlan.asignatura', 'docenteTitular.usuario'])
            ->get();

        $cursoIds = $cursos->pluck('id_curso')->all();

        // Mapa id_curso -> info de presentación
        $info = [];
        foreach ($cursos as $curso) {
            $info[$curso->id_curso] = [
                'asignatura' => $curso->asignacionPlan?->asignatura?->nombre ?? $curso->nombre,
                'cod' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? $curso->cod_curso,
                'docente' => $this->nombreDocente($curso),
            ];
        }

        // ── Asistencia por curso ──
        $asistenciaRows = empty($cursoIds) ? collect() : collect(DB::connection('pgsql')->select(
            'SELECT c.id_curso,
                    COUNT(DISTINCT (a.dia, a.hora_inicio, a.hora_fin)) AS sesiones,
                    AVG(CASE WHEN a.esta_presente THEN 1.0 ELSE 0.0 END) * 100 AS porcentaje
             FROM curso.asistencia a
             JOIN curso.inscripcion_componente ic ON ic.id_inscripcion_componente = a.id_inscripcion_componente
             JOIN curso.componente c ON c.id_componente = ic.id_componente
             WHERE c.id_curso IN (' . $this->placeholders($cursoIds) . ')
             GROUP BY c.id_curso',
            $cursoIds
        ));
        $asistenciaPorCurso = $asistenciaRows->map(fn($r) => [
            'id_curso' => (int) $r->id_curso,
            'asignatura' => $info[$r->id_curso]['asignatura'] ?? 'Curso',
            'cod' => $info[$r->id_curso]['cod'] ?? '',
            'docente' => $info[$r->id_curso]['docente'] ?? 'Sin asignar',
            'porcentaje' => round((float) $r->porcentaje, 1),
            'sesiones' => (int) $r->sesiones,
        ])->values()->all();

        // ── Avance de evaluación por curso ──
        $avanceRows = empty($cursoIds) ? collect() : collect(DB::connection('pgsql')->select(
            'SELECT c.id_curso,
                    COUNT(aag.id_actividad_asignada_grupo) AS total,
                    COUNT(aag.nota) AS calificadas
             FROM agenda.actividad_asignada_grupo aag
             JOIN agenda.actividad act ON act.id_actividad = aag.id_actividad
             JOIN curso.componente c ON c.id_componente = act.id_componente
             WHERE c.id_curso IN (' . $this->placeholders($cursoIds) . ')
             GROUP BY c.id_curso',
            $cursoIds
        ));
        $avancePorCurso = $avanceRows->map(function ($r) use ($info) {
            $total = (int) $r->total;
            $calificadas = (int) $r->calificadas;
            return [
                'id_curso' => (int) $r->id_curso,
                'asignatura' => $info[$r->id_curso]['asignatura'] ?? 'Curso',
                'cod' => $info[$r->id_curso]['cod'] ?? '',
                'total_actividades' => $total,
                'calificadas' => $calificadas,
                'porcentaje' => $total > 0 ? round($calificadas / $total * 100, 1) : 0,
            ];
        })->values()->all();

        // ── Alumnos en riesgo por curso ──
        $riesgoRows = empty($cursoIds) ? collect() : collect(DB::connection('pgsql')->select(
            'SELECT id_curso,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE promedio_parcial IS NOT NULL AND promedio_parcial < 4.0) AS en_riesgo
             FROM curso.inscripcion_curso
             WHERE id_curso IN (' . $this->placeholders($cursoIds) . ')
             GROUP BY id_curso',
            $cursoIds
        ));
        $riesgoPorCurso = $riesgoRows->map(fn($r) => [
            'id_curso' => (int) $r->id_curso,
            'asignatura' => $info[$r->id_curso]['asignatura'] ?? 'Curso',
            'cod' => $info[$r->id_curso]['cod'] ?? '',
            'en_riesgo' => (int) $r->en_riesgo,
            'total' => (int) $r->total,
        ])->filter(fn($x) => $x['en_riesgo'] > 0)->values()->all();
        $riesgoTotal = array_sum(array_column($riesgoPorCurso, 'en_riesgo'));

        // ── Carga docente ──
        $cargaDocente = $this->cargaDocente($cursos, $cursoIds);

        return Inertia::render('jefe-carrera/Metricas', [
            'carrera' => ['nombre' => $jefatura['carrera_nombre'] ?? 'Carrera'],
            'asistencia_por_curso' => $asistenciaPorCurso,
            'avance_por_curso' => $avancePorCurso,
            'alumnos_en_riesgo' => ['total' => $riesgoTotal, 'por_curso' => $riesgoPorCurso],
            'carga_docente' => $cargaDocente,
        ]);
    }

    /**
     * Devuelve los datos del syllabus de un programa en JSON para la previsualización.
     * Valida que el programa pertenezca a un curso de la carrera del jefe.
     */
    public function programaPreview(int $programaId): JsonResponse
    {
        // JSON endpoint (previsualización) → abort(403) es correcto aquí (no es vista Inertia).
        $jefatura = $this->jefaturaOrAbort();

        $programa = Programa::with(['curso.asignacionPlan.asignatura', 'curso.docenteTitular.usuario'])
            ->findOrFail($programaId);

        $curso = $programa->curso;
        $perteneceCarrera = $curso && Curso::where('id_curso', $curso->id_curso)
            ->whereHas('asignacionPlan.plan', fn($q) => $q->where('id_carrera', $jefatura['carrera_id']))
            ->exists();

        if (!$perteneceCarrera) {
            abort(403, 'El programa no pertenece a tu carrera');
        }

        $syllabus = SyllabusData::fromArray($programa->data_syllabus ?? []);
        $secciones = $syllabus->secciones;

        $secIX = $secciones->seccionIX();
        $secVI = $secciones->seccionVI();
        $secIV = $secciones->seccionIV();

        // "Objetivos" no tiene una sección 1:1 en el contrato; se aproxima con los
        // títulos de competencias (IV, solo COMPLETO) o, si no hay, con los
        // resultados de aprendizaje consolidados de las unidades (VI).
        $objetivos = $secIV
            ? array_map(fn ($t) => $t->titulo, [...$secIV->competenciasEspecificas, ...$secIV->competenciasGenericas])
            : array_map(
                fn ($r) => $r->resultado,
                array_merge([], ...array_map(fn ($u) => $u->resultadosAprendizaje, $secVI?->unidades ?? []))
            );

        $unidades = array_map(fn ($u) => [
            'numero' => $u->numero,
            'titulo' => $u->titulo,
            'horas' => 0,
            'contenidos' => $u->contenidosItems,
        ], $secVI?->unidades ?? []);

        $evaluaciones = array_map(fn ($c) => [
            'descripcion' => $c->componente,
            'ponderacion' => $c->porcentaje . '%',
            'semana' => '',
        ], $secIX?->tablaComponentes ?? []);

        $syllabusOut = [
            'titulo' => $curso->asignacionPlan?->asignatura?->nombre ?? $curso->nombre,
            'codigo' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? $curso->cod_curso,
            'docente' => $this->nombreDocente($curso),
            'descripcion' => $secIX?->descripcion ?? '',
            'objetivos' => $objetivos,
            'unidades' => $unidades,
            'evaluaciones' => $evaluaciones,
            'completud' => method_exists($programa, 'getCompletenessPercentage')
                ? (int) $programa->getCompletenessPercentage() : 0,
        ];

        return response()->json(['syllabus' => $syllabusOut]);
    }

    /**
     * Aprueba un programa de la carrera (COMPLETO|BASICO_COMPLETO → APROBADO).
     *
     * PUT docente/jefe-carrera/programas/{programaId}/aprobar
     */
    public function aprobarPrograma(int $programaId): RedirectResponse
    {
        $jefaturaCheck = $this->jefaturaOrRedirect();

        if ($jefaturaCheck instanceof RedirectResponse) {
            return $jefaturaCheck;
        }

        /** @var Usuario $user */
        $user = Auth::user();

        $programa = Programa::findOrFail($programaId);
        $this->authorize('approve', $programa);

        $estadosPermitidos = ['COMPLETO', 'BASICO_COMPLETO'];
        if (!in_array($programa->estado, $estadosPermitidos)) {
            return back()->with('error', "No se puede aprobar un programa en estado {$programa->estado}.");
        }

        DB::transaction(function () use ($programa, $user) {
            DB::statement("SELECT set_config('app.accion_tipo', 'APROBACION', true)");
            DB::statement("SELECT set_config('app.actor_id', ?, true)", [(string) $user->id_usuario]);

            $programa->update([
                'estado' => 'APROBADO',
                'revisado_por' => $user->id_usuario,
            ]);
        });

        return back()->with('success', "El programa #{$programaId} fue aprobado.");
    }

    /**
     * Devuelve un programa a borrador con observaciones (solicitud de cambios).
     *
     * PUT docente/jefe-carrera/programas/{programaId}/rechazar
     */
    public function rechazarPrograma(Request $request, int $programaId): RedirectResponse
    {
        $jefaturaCheck = $this->jefaturaOrRedirect();

        if ($jefaturaCheck instanceof RedirectResponse) {
            return $jefaturaCheck;
        }

        /** @var Usuario $user */
        $user = Auth::user();

        $request->validate([
            'notas' => ['nullable', 'string', 'max:3000'],
        ]);

        $programa = Programa::findOrFail($programaId);

        // Autorizar usando la Policy (admins y jefes de carrera pueden rechazar)
        $this->authorize('reject', $programa);

        $estadosPermitidos = ['COMPLETO', 'APROBADO', 'BASICO_COMPLETO'];
        if (!in_array($programa->estado, $estadosPermitidos)) {
            return back()->with('error', "No se puede devolver un programa en estado {$programa->estado}.");
        }

        $razonRechazo = trim($request->input('notas', 'Sin observaciones'));
        $estadoOrigen = $programa->estado;

        DB::transaction(function () use ($programa, $razonRechazo, $user) {
            DB::statement("SELECT set_config('app.accion_tipo',   'RECHAZO', true)");
            DB::statement("SELECT set_config('app.razon_rechazo', ?, true)", [$razonRechazo]);
            DB::statement("SELECT set_config('app.actor_id',      ?, true)", [(string) $user->id_usuario]);

            $programa->update([
                'estado'           => 'BORRADOR',
                'fecha_aprobacion' => null,
                'revisado_por'     => null,
            ]);
        });

        Log::info('Programa devuelto a revisión por Jefe de Carrera', [
            'id_programa'   => $programa->id_programa,
            'estado_origen' => $estadoOrigen,
            'rechazado_por' => $user->id_usuario,
            'razon_rechazo' => $razonRechazo,
        ]);

        return back()->with('warning', "Solicitud de cambios enviada. El programa #{$programaId} fue devuelto a borrador.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Query base de cursos de una carrera (no eliminados).
     */
    private function cursosCarreraQuery(int $carreraId)
    {
        return Curso::whereHas('asignacionPlan.plan', fn($q) => $q->where('id_carrera', $carreraId))
            ->whereNull('fecha_eliminacion');
    }

    /**
     * Período vigente = par (agno_real, semestre_real) más reciente con cursos.
     * @return array{ano:int,sem:int}|null
     */
    private function resolvePeriodoVigente(int $carreraId): ?array
    {
        $row = $this->cursosCarreraQuery($carreraId)
            ->whereNotNull('agno_real')
            ->whereNotNull('semestre_real')
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->first(['agno_real', 'semestre_real']);

        if (!$row) {
            return null;
        }

        return ['ano' => (int) $row->agno_real, 'sem' => (int) $row->semestre_real];
    }

    /**
     * Conteo de cursos por los últimos hasta 6 períodos (cronológico ascendente).
     * @return int[]
     */
    private function cursosTendencia(int $carreraId): array
    {
        $rows = $this->cursosCarreraQuery($carreraId)
            ->whereNotNull('agno_real')
            ->whereNotNull('semestre_real')
            ->selectRaw('agno_real, semestre_real, COUNT(*) as total')
            ->groupBy('agno_real', 'semestre_real')
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->limit(6)
            ->get();

        // Vienen desc; invertir para que el sparkline lea antiguo→reciente
        return $rows->reverse()->pluck('total')->map(fn($t) => (int) $t)->values()->all();
    }

    /**
     * Mapea el estado DB del programa actual de un curso al estado de UI.
     */
    private function estadoSyllabusUi(Curso $curso): string
    {
        $programa = $this->programaActual($curso);
        if (!$programa) {
            return 'NO_INICIADO';
        }

        return match ($programa->estado) {
            'BORRADOR', 'BASICO_COMPLETO' => 'BORRADOR',
            'COMPLETO' => 'EN_REVISION',
            'APROBADO', 'PUBLICADO' => 'APROBADO',
            default => 'NO_INICIADO',
        };
    }

    /**
     * Devuelve el programa actual (es_actual=true) de un curso desde la relación cargada.
     */
    private function programaActual(Curso $curso): ?Programa
    {
        if ($curso->relationLoaded('programas')) {
            return $curso->programas->firstWhere('es_actual', true);
        }

        return Programa::where('id_curso', $curso->id_curso)->where('es_actual', true)->first();
    }

    /**
     * Construye la fila de seguimiento para un curso.
     */
    private function mapCursoSeguimiento(Curso $curso): array
    {
        $programa = $this->programaActual($curso);
        $estado = $this->estadoSyllabusUi($curso);

        $completud = 0;
        if ($programa && method_exists($programa, 'getCompletenessPercentage')) {
            $completud = (int) $programa->getCompletenessPercentage();
        }

        return [
            'id_curso' => (int) $curso->id_curso,
            'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? $curso->cod_curso ?? '—',
            'nombre_asignatura' => $curso->asignacionPlan?->asignatura?->nombre ?? $curso->nombre ?? 'Asignatura',
            'seccion' => $curso->cod_curso ?? '—',
            'docente' => [
                'nombre' => $this->nombreDocente($curso),
                'inicial' => $this->inicialDocente($curso),
                'color' => $this->colorDocente($curso->id_docente_titular),
            ],
            'estado_syllabus' => $estado,
            'fecha_actualizacion' => null,
            'completud' => $completud,
            'id_programa' => $programa?->id_programa,
        ];
    }

    private function nombreDocente(Curso $curso): string
    {
        $usuario = $curso->docenteTitular?->usuario;
        if (!$usuario) {
            return 'Sin asignar';
        }
        $nombre = trim("{$usuario->nombre1} {$usuario->apellido1}");
        return $nombre !== '' ? "Prof. {$nombre}" : 'Sin asignar';
    }

    private function inicialDocente(Curso $curso): string
    {
        $usuario = $curso->docenteTitular?->usuario;
        if (!$usuario) {
            return 'NA';
        }
        $a = mb_substr(trim((string) $usuario->nombre1), 0, 1);
        $b = mb_substr(trim((string) $usuario->apellido1), 0, 1);
        $ini = mb_strtoupper($a . $b);
        return $ini !== '' ? $ini : 'NA';
    }

    private function colorDocente(?int $idDocente): string
    {
        if ($idDocente === null) {
            return '#94A3B8'; // slate-400 para "sin asignar"
        }
        return self::DOCENTE_COLORS[$idDocente % count(self::DOCENTE_COLORS)];
    }

    /**
     * Métricas agregadas (resumen) sobre un conjunto de cursos.
     * @return array{asistencia_promedio:?float,avance_evaluacion:?float,alumnos_en_riesgo:int,carga_docente:array}
     */
    private function metricasResumen(array $cursoIds): array
    {
        if (empty($cursoIds)) {
            return [
                'asistencia_promedio' => null,
                'avance_evaluacion' => null,
                'alumnos_en_riesgo' => 0,
                'carga_docente' => ['docentes' => 0, 'cursos' => 0, 'promedio' => 0],
            ];
        }

        $ph = $this->placeholders($cursoIds);

        $asis = DB::connection('pgsql')->selectOne(
            "SELECT AVG(CASE WHEN a.esta_presente THEN 1.0 ELSE 0.0 END) * 100 AS pct
             FROM curso.asistencia a
             JOIN curso.inscripcion_componente ic ON ic.id_inscripcion_componente = a.id_inscripcion_componente
             JOIN curso.componente c ON c.id_componente = ic.id_componente
             WHERE c.id_curso IN ($ph)",
            $cursoIds
        );

        $avance = DB::connection('pgsql')->selectOne(
            "SELECT COUNT(aag.id_actividad_asignada_grupo) AS total, COUNT(aag.nota) AS calificadas
             FROM agenda.actividad_asignada_grupo aag
             JOIN agenda.actividad act ON act.id_actividad = aag.id_actividad
             JOIN curso.componente c ON c.id_componente = act.id_componente
             WHERE c.id_curso IN ($ph)",
            $cursoIds
        );

        $riesgo = DB::connection('pgsql')->selectOne(
            "SELECT COUNT(*) FILTER (WHERE promedio_parcial IS NOT NULL AND promedio_parcial < 4.0) AS en_riesgo
             FROM curso.inscripcion_curso
             WHERE id_curso IN ($ph)",
            $cursoIds
        );

        $avanceTotal = (int) ($avance->total ?? 0);
        $avancePct = $avanceTotal > 0 ? round((int) $avance->calificadas / $avanceTotal * 100, 1) : null;

        // Carga docente (resumen)
        $docentes = DB::connection('pgsql')->table('curso.curso')
            ->whereIn('id_curso', $cursoIds)
            ->whereNotNull('id_docente_titular')
            ->distinct()
            ->count('id_docente_titular');

        return [
            'asistencia_promedio' => $asis && $asis->pct !== null ? round((float) $asis->pct, 1) : null,
            'avance_evaluacion' => $avancePct,
            'alumnos_en_riesgo' => (int) ($riesgo->en_riesgo ?? 0),
            'carga_docente' => [
                'docentes' => (int) $docentes,
                'cursos' => count($cursoIds),
                'promedio' => $docentes > 0 ? round(count($cursoIds) / $docentes, 1) : 0,
            ],
        ];
    }

    /**
     * Carga docente detallada: cursos y estudiantes por docente titular.
     */
    private function cargaDocente($cursos, array $cursoIds): array
    {
        if ($cursos->isEmpty()) {
            return [];
        }

        // Estudiantes por curso
        $estudiantesPorCurso = empty($cursoIds) ? collect() : collect(DB::connection('pgsql')->select(
            'SELECT id_curso, COUNT(DISTINCT id_estudiante) AS total
             FROM curso.inscripcion_curso
             WHERE id_curso IN (' . $this->placeholders($cursoIds) . ')
             GROUP BY id_curso',
            $cursoIds
        ))->keyBy('id_curso');

        $agrupado = [];
        foreach ($cursos as $curso) {
            $idDoc = $curso->id_docente_titular;
            $key = $idDoc ?? 'sin';
            if (!isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'docente' => $this->nombreDocente($curso),
                    'inicial' => $this->inicialDocente($curso),
                    'color' => $this->colorDocente($idDoc),
                    'cursos' => 0,
                    'estudiantes' => 0,
                ];
            }
            $agrupado[$key]['cursos']++;
            $agrupado[$key]['estudiantes'] += (int) ($estudiantesPorCurso[$curso->id_curso]->total ?? 0);
        }

        return array_values($agrupado);
    }

    /**
     * Genera alertas reales para el dashboard.
     */
    private function construirAlertas(array $resumen, int $alumnosEnRiesgo): array
    {
        $alertas = [];
        $id = 1;

        if ($resumen['no_iniciado'] > 0) {
            $n = $resumen['no_iniciado'];
            $alertas[] = [
                'id' => $id++,
                'tipo' => 'critica',
                'titulo' => "{$n} curso" . ($n !== 1 ? 's' : '') . ' del período aún no '
                    . ($n !== 1 ? 'tienen' : 'tiene') . ' programa creado',
                'count' => $n,
                'accion_label' => 'Ver cursos',
                'accion_url' => '/docente/jefe-carrera/seguimiento?estado=NO_INICIADO',
            ];
        }

        if ($resumen['en_revision'] > 0) {
            $n = $resumen['en_revision'];
            $alertas[] = [
                'id' => $id++,
                'tipo' => 'advertencia',
                'titulo' => "{$n} programa" . ($n !== 1 ? 's' : '') . ' pendiente' . ($n !== 1 ? 's' : '') . ' de aprobación',
                'count' => $n,
                'accion_label' => 'Revisar',
                'accion_url' => '/docente/jefe-carrera/seguimiento?estado=EN_REVISION',
            ];
        }

        if ($alumnosEnRiesgo > 0) {
            $alertas[] = [
                'id' => $id++,
                'tipo' => 'info',
                'titulo' => "{$alumnosEnRiesgo} alumno" . ($alumnosEnRiesgo !== 1 ? 's' : '') . ' con promedio bajo 4.0',
                'count' => $alumnosEnRiesgo,
                'accion_label' => 'Ver métricas',
                'accion_url' => '/docente/jefe-carrera/metricas',
            ];
        }

        return $alertas;
    }

    /** Placeholders posicionales para un IN(...) parametrizado. */
    private function placeholders(array $items): string
    {
        return implode(',', array_fill(0, count($items), '?'));
    }
}

