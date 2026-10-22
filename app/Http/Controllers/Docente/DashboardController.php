<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Auditoria\ProgramaHistorial;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\MensajeriaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador para el dashboard del docente.
 *
 * Tablas implicadas:
 * - usuario.usuario / usuario.docente: identidad y perfil del docente.
 * - curso.curso / curso.componente / curso.docente_componente: cursos donde
 *   es titular o donde imparte un componente.
 * - curso.programa: syllabus vigente de cada curso que dirige.
 * - auditoria.programa_historial: distingue "borrador nuevo" de "borrador
 *   devuelto por rechazo" (el estado por sí solo no alcanza — ver
 *   resolverEstadoSyllabus()).
 * - curso.mensaje: mensajería de nivel curso, agrupada por curso.
 * - usuario.usuario_rol_asignacion: puente a jefatura de carrera si aplica.
 */
class DashboardController extends Controller
{
    use ContaPendientesMensajes;

    /**
     * Muestra el dashboard del docente.
     *
     * Tres formas posibles de la misma ruta, todas honestas con lo que hay en
     * la base de datos hoy:
     * - Sin perfil de docente (rol asignado pero sin fila usuario.docente):
     *   panel de perfil incompleto en vez del dashboard.
     * - Sin cursos en el período vigente: estado "entre semestres".
     * - Con cursos: dashboard con alertas de syllabus, cursos como titular y
     *   como componente, y mensajería de curso.
     */
    public function index(MensajeriaService $mensajeria): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // El middleware is_docente ya exige perfil docente para llegar aquí;
        // esta comprobación es sólo defensiva.
        if (!$user->docente) {
            return $this->perfilIncompleto();
        }

        $docente = $user->docente;
        $idDocente = (int) $docente->id_docente;

        $asignacionJefatura = UsuarioRolAsignacion::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->where('nombre', 'Jefe de Carrera'))
            ->whereHas('contexto.tipoContexto', fn($q) => $q->where('categoria', 'carrera'))
            ->latest('id_ura')
            ->first();

        $carreraJefatura = $asignacionJefatura
            ? Carrera::query()
                ->select('id_carrera', 'nombre', 'id_contexto')
                ->where('id_contexto', $asignacionJefatura->id_contexto)
                ->first()
            : null;

        // Syllabus COMPLETO = enviado a revisión y esperando al jefe de
        // carrera (mismo criterio que JefeCarreraController::estadoSyllabusUi).
        $pendientesRevisionJefatura = $carreraJefatura
            ? Curso::whereHas('asignacionPlan.plan', fn($q) => $q->where('id_carrera', $carreraJefatura->id_carrera))
                ->where('es_plantilla', false)
                ->whereNull('fecha_eliminacion')
                ->whereHas('programas', fn($q) => $q->where('es_actual', true)->where('estado', 'COMPLETO'))
                ->count()
            : 0;

        // Período vigente = par (agno_real, semestre_real) más reciente con
        // cursos en todo el sistema (mismo criterio que el dashboard de
        // jefatura, pero sin acotar a una carrera).
        $periodo = $this->resolvePeriodoVigente();

        $cursosTitularQuery = Curso::where('id_docente_titular', $idDocente)
            ->where('es_plantilla', false)
            ->whereNull('fecha_eliminacion');
        $cursosComponenteQuery = Curso::whereHas(
            'componentes.docenteComponentes',
            fn($q) => $q->where('id_docente', $idDocente)
        )
            ->where('es_plantilla', false)
            ->where(function ($q) use ($idDocente) {
                $q->whereNull('id_docente_titular')->orWhere('id_docente_titular', '!=', $idDocente);
            })
            ->whereNull('fecha_eliminacion');

        if ($periodo) {
            $cursosTitularQuery->where('agno_real', $periodo['ano'])->where('semestre_real', $periodo['sem']);
            $cursosComponenteQuery->where('agno_real', $periodo['ano'])->where('semestre_real', $periodo['sem']);
        }

        $cursosTitular = $cursosTitularQuery
            ->with(['programas' => fn($q) => $q->where('es_actual', true)])
            ->orderBy('nombre')
            ->get();

        $cursosComponente = $cursosComponenteQuery
            ->with([
                'docenteTitular.usuario',
                'componentes' => fn($q) => $q->with([
                    'tipoComponente',
                    'docenteComponentes' => fn($dq) => $dq->where('id_docente', $idDocente),
                ]),
            ])
            ->orderBy('nombre')
            ->get();

        if ($cursosTitular->isEmpty() && $cursosComponente->isEmpty()) {
            return $this->entreSemestres($user, $idDocente);
        }

        $historialPorPrograma = $this->historialMasRecientePorPrograma(
            $cursosTitular->flatMap(fn($curso) => $curso->programas)->pluck('id_programa')
        );

        $todosLosCursoIds = $cursosTitular->pluck('id_curso')
            ->merge($cursosComponente->pluck('id_curso'))
            ->unique()
            ->values()
            ->all();

        $actividadesProximas = $this->actividadesProximas($todosLosCursoIds);
        $proximaEntregaPorCurso = $actividadesProximas
            ->groupBy(fn(Actividad $a) => $a->componente?->id_curso)
            ->map(fn(Collection $porCurso) => $porCurso->first()->fecha_limite?->format('Y-m-d'));

        $cursosTitularData = $cursosTitular->map(function (Curso $curso) use ($historialPorPrograma, $proximaEntregaPorCurso) {
            $programa = $curso->programas->first();
            $syllabus = $this->resolverEstadoSyllabus($programa, $historialPorPrograma);

            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'letra_grupo' => $curso->letra_grupo,
                'semestre_real' => $curso->semestre_real,
                'agno_real' => $curso->agno_real,
                'estado_syllabus' => $syllabus['estado'],
                'rechazo' => $syllabus['rechazo'],
                'proxima_entrega' => $proximaEntregaPorCurso->get($curso->id_curso),
            ];
        })->values();

        $componentesData = $cursosComponente->flatMap(function (Curso $curso) {
            $titular = $curso->docenteTitular?->usuario;
            $titularNombre = $titular ? trim("{$titular->nombre1} {$titular->apellido1}") : null;

            return $curso->componentes
                ->filter(fn($c) => $c->docenteComponentes->isNotEmpty())
                ->map(fn($c) => [
                    'id_curso' => $curso->id_curso,
                    'id_componente' => $c->id_componente,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'letra_grupo' => $curso->letra_grupo,
                    'tipo_componente' => $c->tipoComponente->tipo ?? 'N/A',
                    'titular_nombre' => $titularNombre,
                ]);
        })->values();

        $alertasSyllabus = $cursosTitularData
            ->filter(fn($c) => in_array($c['estado_syllabus'], ['RECHAZADO', 'BORRADOR', 'EN_REVISION'], true))
            ->sortBy(fn($c) => match ($c['estado_syllabus']) {
                'RECHAZADO' => 0,
                'BORRADOR' => 1,
                'EN_REVISION' => 2,
                default => 3,
            })
            ->values();

        $mensajeriaCursos = $this->mensajeriaPorCurso($mensajeria, $idDocente, (int) $user->id_usuario);

        $agendaPendiente = $this->agendaPendiente($todosLosCursoIds);
        $proximasFechasLimite = $this->proximasFechasLimite($actividadesProximas);

        $totalCursos = $cursosTitularData->count() + $componentesData->pluck('id_curso')->unique()->count();

        return Inertia::render('docente/Dashboard', [
            'docente' => [
                'id_docente' => $docente->id_docente,
                'grado' => $docente->grado,
                'titulo' => $docente->titulo,
                'cargo' => $docente->cargo,
                'id_usuario' => $user->id_usuario,
            ],
            'stats' => [
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
                'total_cursos' => $totalCursos,
                'total_titular' => $cursosTitularData->count(),
                'total_componente' => $componentesData->pluck('id_curso')->unique()->count(),
            ],
            'periodo' => $periodo,
            'cursosTitular' => $cursosTitularData,
            'componentes' => $componentesData,
            'alertasSyllabus' => $alertasSyllabus,
            'mensajeria' => [
                'no_leidos' => $mensajeriaCursos->sum('no_leidos'),
                'cursos' => $mensajeriaCursos,
            ],
            'agendaPendiente' => $agendaPendiente,
            'proximasFechasLimite' => $proximasFechasLimite,
            'jefatura' => [
                'has_access' => (bool) $asignacionJefatura,
                'id_contexto' => $asignacionJefatura?->id_contexto,
                'carrera' => $carreraJefatura ? [
                    'id_carrera' => $carreraJefatura->id_carrera,
                    'nombre' => $carreraJefatura->nombre,
                ] : null,
                'pendientes_revision' => $pendientesRevisionJefatura,
            ],
        ]);
    }

    /**
     * Panel de perfil incompleto: el usuario tiene un rol de docente asignado
     * pero no existe su fila usuario.docente, así que el sistema no puede
     * asociarle cursos, componentes ni syllabus.
     *
     * GET docente/perfil-incompleto — fuera del middleware is_docente a
     * propósito: es el destino cuando esa comprobación falla por falta de
     * perfil, no por falta de rol.
     */
    public function perfilIncompleto(): \Illuminate\Http\RedirectResponse|Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if ($user->docente) {
            return redirect()->route('docente.dashboard');
        }

        $rolesDocente = $user->rolesAsignados()
            ->wherePivot('esta_activo', true)
            ->wherePivot('fue_eliminado', false)
            ->get()
            ->pluck('nombre')
            ->filter(fn($nombre) => str_contains(mb_strtolower($nombre), 'docente'))
            ->values();

        if ($rolesDocente->isEmpty()) {
            return redirect('/dashboard');
        }

        return Inertia::render('docente/PerfilIncompleto', [
            'usuario' => [
                'id_usuario' => $user->id_usuario,
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
                'email' => $user->email,
                'roles' => $rolesDocente,
            ],
        ]);
    }

    /**
     * Dashboard cuando el docente no tiene cursos en el período vigente
     * (recién asignado con perfil pero sin cursos, o entre semestres).
     */
    private function entreSemestres(Usuario $user, int $idDocente): Response
    {
        $ultimoCurso = Curso::where(function ($q) use ($idDocente) {
            $q->where('id_docente_titular', $idDocente)
                ->orWhereHas('componentes.docenteComponentes', fn($dq) => $dq->where('id_docente', $idDocente));
        })
            ->where('es_plantilla', false)
            ->whereNull('fecha_eliminacion')
            ->whereNotNull('agno_real')
            ->whereNotNull('semestre_real')
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->orderByDesc('fecha_fin')
            ->first(['agno_real', 'semestre_real', 'fecha_fin']);

        return Inertia::render('docente/Dashboard', [
            'docente' => null,
            'stats' => [
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
                'total_cursos' => 0,
            ],
            'periodo' => null,
            'cursosTitular' => [],
            'componentes' => [],
            'alertasSyllabus' => [],
            'mensajeria' => ['no_leidos' => 0, 'cursos' => []],
            'agendaPendiente' => ['items' => [], 'total' => 0],
            'proximasFechasLimite' => [],
            'jefatura' => ['has_access' => false, 'id_contexto' => null, 'carrera' => null],
            'entreSemestres' => [
                'ultimo_semestre' => $ultimoCurso?->semestre_real,
                'ultimo_agno' => $ultimoCurso?->agno_real,
                'ultima_fecha_fin' => $ultimoCurso?->fecha_fin,
            ],
        ]);
    }

    /**
     * Período vigente = par (agno_real, semestre_real) más reciente con
     * cursos en todo el sistema. No hay tabla de calendario académico, así
     * que se aproxima con el dato real más reciente en vez de usar la fecha
     * de hoy (que puede no coincidir con el semestre cargado en el sistema).
     *
     * @return array{ano:int,sem:int,fecha_inicio:?string}|null
     */
    private function resolvePeriodoVigente(): ?array
    {
        $row = Curso::whereNull('fecha_eliminacion')
            ->where('es_plantilla', false)
            ->whereNotNull('agno_real')
            ->whereNotNull('semestre_real')
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->first(['agno_real', 'semestre_real']);

        if (!$row) {
            return null;
        }

        $fechaInicio = Curso::whereNull('fecha_eliminacion')
            ->where('es_plantilla', false)
            ->where('agno_real', $row->agno_real)
            ->where('semestre_real', $row->semestre_real)
            ->orderBy('fecha_inicio')
            ->value('fecha_inicio');

        return [
            'ano' => (int) $row->agno_real,
            'sem' => (int) $row->semestre_real,
            'fecha_inicio' => $fechaInicio,
        ];
    }

    /**
     * Último evento de auditoria.programa_historial por programa (el que
     * decide si un BORRADOR es "recién creado" o "devuelto por rechazo").
     *
     * @param  Collection<int,int>  $programaIds
     * @return Collection<int,ProgramaHistorial>  Indexada por id_programa.
     */
    private function historialMasRecientePorPrograma(Collection $programaIds): Collection
    {
        $ids = $programaIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ProgramaHistorial::whereIn('id_programa', $ids)
            ->with('usuario')
            ->orderByDesc('fecha_accion')
            ->orderByDesc('id_log')
            ->get()
            ->groupBy('id_programa')
            ->map(fn($eventos) => $eventos->first());
    }

    /**
     * Traduce el estado de un Programa al estado que ve el docente en su
     * dashboard. BORRADOR es ambiguo por sí solo (recién creado vs. devuelto
     * con observaciones), así que se desambigua con el último evento de
     * auditoria.programa_historial: si fue un RECHAZO, se muestra como
     * RECHAZADO con la razón, quién y cuándo.
     *
     * @return array{estado:string, rechazo:?array{razon:string,por:string,fecha:?string}}
     */
    private function resolverEstadoSyllabus(?Programa $programa, Collection $historialPorPrograma): array
    {
        if (!$programa) {
            return ['estado' => 'NO_INICIADO', 'rechazo' => null];
        }

        $estado = match ($programa->estado) {
            'BORRADOR', 'BASICO_COMPLETO' => 'BORRADOR',
            'COMPLETO' => 'EN_REVISION',
            'APROBADO', 'PUBLICADO' => 'APROBADO',
            default => 'NO_INICIADO',
        };

        if ($estado !== 'BORRADOR') {
            return ['estado' => $estado, 'rechazo' => null];
        }

        /** @var ProgramaHistorial|null $ultimoEvento */
        $ultimoEvento = $historialPorPrograma->get($programa->id_programa);

        if (!$ultimoEvento || $ultimoEvento->accion !== 'RECHAZO') {
            return ['estado' => 'BORRADOR', 'rechazo' => null];
        }

        $actor = $ultimoEvento->usuario;

        return [
            'estado' => 'RECHAZADO',
            'rechazo' => [
                'razon' => $ultimoEvento->observaciones,
                'por' => $actor ? trim("{$actor->nombre1} {$actor->apellido1}") : null,
                'fecha' => optional($ultimoEvento->fecha_accion)->format('d-m-Y'),
            ],
        ];
    }

    /**
     * Mensajería de nivel curso (curso.mensaje) sin leer, agrupada por curso.
     * Mismo criterio que Student\DashboardController::mensajesSinLeer, pero
     * con esStaff=true: el docente ve todo el canal del componente, no sólo
     * lo suyo.
     *
     * @return Collection<int,array{id_curso:int,nombre:string,cod_curso:string,no_leidos:int}>
     */
    private function mensajeriaPorCurso(MensajeriaService $mensajeria, int $idDocente, int $idUsuario): Collection
    {
        $componentes = $mensajeria->componentesDeDocente($idDocente);

        $noLeidos = $mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: true,
        );

        return $componentes
            ->map(fn($c) => [
                'id_curso' => (int) $c->id_curso,
                'nombre' => $c->curso_nombre,
                'cod_curso' => $c->cod_curso,
                'no_leidos' => $noLeidos[(int) $c->id_componente] ?? 0,
            ])
            ->filter(fn(array $fila) => $fila['no_leidos'] > 0)
            ->groupBy('id_curso')
            ->map(fn($delCurso) => [
                'id_curso' => $delCurso->first()['id_curso'],
                'nombre' => $delCurso->first()['nombre'],
                'cod_curso' => $delCurso->first()['cod_curso'],
                'no_leidos' => $delCurso->sum('no_leidos'),
            ])
            ->sortByDesc('no_leidos')
            ->values();
    }

    /**
     * Interacciones de agenda.agenda que esperan respuesta del docente: el
     * último mensaje del grupo es de tipo "Mensaje al profesor" (canal
     * distinto de curso.mensaje — ver ContaPendientesMensajes). Se acota a
     * TODOS los cursos donde el docente participa, no sólo donde es titular
     * (a diferencia de MensajesController, que sólo cubre sus cursos como
     * titular).
     *
     * Cada ítem trae el texto del mensaje (para leerlo sin abrir nada) y el
     * grupo del curso; el enlace abre la agenda de ese grupo en la actividad,
     * igual que la bandeja. `total` cuenta todos, no sólo los `$limit` listados.
     *
     * @param  int[]  $cursoIds
     * @return array{items: array<int,array{quien:string,actividad_nombre:string,mensaje:string,id_curso:int,id_actividad:int,id_grupo:int,cod_curso:string,letra_grupo:?string,fecha_envio:?string}>, total: int}
     */
    private function agendaPendiente(array $cursoIds, int $limit = 5): array
    {
        if (empty($cursoIds)) {
            return ['items' => [], 'total' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($cursoIds), '?'));

        $rows = DB::select(
            "SELECT ultimo.id_actividad_asignada_grupo, ultimo.fecha_envio, ultimo.mensaje,
                    -- Posición del grupo dentro de su actividad, para «Grupo N».
                    (SELECT COUNT(*) FROM agenda.actividad_asignada_grupo x
                      WHERE x.id_actividad = ultimo.id_actividad
                        AND x.id_actividad_asignada_grupo <= ultimo.id_actividad_asignada_grupo) AS posicion_grupo,
                    COUNT(*) OVER () AS total
             FROM (
                 SELECT DISTINCT ON (a.id_actividad_asignada_grupo)
                        a.id_actividad_asignada_grupo,
                        aag.id_actividad,
                        a.tipo_mensaje,
                        a.mensaje,
                        a.fecha_envio
                 FROM agenda.agenda a
                 JOIN agenda.actividad_asignada_grupo aag
                      ON aag.id_actividad_asignada_grupo = a.id_actividad_asignada_grupo
                 JOIN agenda.actividad act ON act.id_actividad = aag.id_actividad
                 JOIN curso.componente c ON c.id_componente = act.id_componente
                 WHERE c.id_curso IN ($placeholders)
                   AND a.tipo_mensaje IN ('Mensaje al profesor', 'Feedback')
                 ORDER BY a.id_actividad_asignada_grupo, a.fecha_envio DESC
             ) ultimo
             WHERE ultimo.tipo_mensaje = 'Mensaje al profesor'
             ORDER BY ultimo.fecha_envio ASC
             LIMIT ?",
            [...$cursoIds, $limit]
        );

        if (empty($rows)) {
            return ['items' => [], 'total' => 0];
        }

        $grupoIds = collect($rows)->pluck('id_actividad_asignada_grupo');

        $grupos = ActividadAsignadaGrupo::whereIn('id_actividad_asignada_grupo', $grupoIds)
            ->with(['actividad.componente.curso', 'integranteGrupos.estudiante.usuario'])
            ->get()
            ->keyBy('id_actividad_asignada_grupo');

        $items = collect($rows)
            ->map(function ($row) use ($grupos) {
                $grupo = $grupos->get($row->id_actividad_asignada_grupo);
                $curso = $grupo?->actividad?->componente?->curso;

                if (!$grupo || !$grupo->actividad || !$curso) {
                    return null;
                }

                // El nombre viene de la base en MAYÚSCULAS; en una lista de cinco se leía a gritos.
                $usuario = $grupo->integranteGrupos->first()?->estudiante?->usuario;

                return [
                    'quien' => ActividadAsignadaGrupo::etiqueta(
                        (bool) $grupo->actividad->es_grupal,
                        $grupo->nombre_grupo,
                        $usuario ? Str::title(trim("{$usuario->nombre1} {$usuario->apellido1}")) : null,
                        (int) $row->posicion_grupo,
                    ),
                    'actividad_nombre' => $grupo->actividad->nombre,
                    'mensaje' => $row->mensaje,
                    'id_curso' => $curso->id_curso,
                    'id_actividad' => $grupo->id_actividad,
                    'id_grupo' => $grupo->id_actividad_asignada_grupo,
                    'cod_curso' => $curso->cod_curso,
                    'letra_grupo' => $curso->letra_grupo,
                    'fecha_envio' => $row->fecha_envio,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return ['items' => $items, 'total' => (int) $rows[0]->total];
    }

    /**
     * Actividades con fecha límite de hoy en adelante en los cursos donde el
     * docente participa (titular o componente), por fecha. Mismo criterio que
     * CalendarioController. De aquí salen tanto la «próxima entrega» de cada
     * tarjeta de curso como la lista de «Próximas fechas límite».
     *
     * @param  int[]  $cursoIds
     * @return Collection<int,Actividad>
     */
    private function actividadesProximas(array $cursoIds): Collection
    {
        if (empty($cursoIds)) {
            return collect();
        }

        return Actividad::whereHas('componente', fn($q) => $q->whereIn('id_curso', $cursoIds))
            ->whereNotNull('fecha_limite')
            ->whereDate('fecha_limite', '>=', now()->toDateString())
            ->with('componente.curso')
            ->orderBy('fecha_limite')
            ->get();
    }

    /**
     * Próximas fechas límite: una fila por actividad y, dentro de ella, un
     * enlace por grupo con su fecha. La misma actividad existe una vez por
     * curso (grupo A, grupo B…), así que se agrupa por nombre y curso en vez
     * de repetirla sin decir de qué grupo es cada fecha.
     *
     * @param  Collection<int,Actividad>  $actividades  ordenadas por fecha_limite
     * @return array<int,array{id_actividad:int,nombre:string,nombre_curso:?string,grupos:array<int,array{id_curso:int,letra_grupo:?string,fecha_limite:string}>}>
     */
    private function proximasFechasLimite(Collection $actividades, int $limit = 4): array
    {
        return $actividades
            ->filter(fn(Actividad $a) => $a->componente?->curso !== null)
            ->groupBy(fn(Actividad $a) => $a->nombre . '|' . $a->componente->curso->nombre)
            ->map(function (Collection $filas) {
                /** @var Actividad $primera */
                $primera = $filas->first();

                $grupos = $filas
                    ->unique(fn(Actividad $a) => $a->componente->id_curso)
                    ->map(fn(Actividad $a) => [
                        'id_curso' => $a->componente->curso->id_curso,
                        'letra_grupo' => $a->componente->curso->letra_grupo,
                        'fecha_limite' => $a->fecha_limite->format('Y-m-d'),
                    ])
                    ->values()
                    ->all();

                return [
                    'id_actividad' => $primera->id_actividad,
                    'nombre' => $primera->nombre,
                    'nombre_curso' => $primera->componente->curso->nombre,
                    'grupos' => $grupos,
                ];
            })
            ->sortBy(fn(array $fila) => $fila['grupos'][0]['fecha_limite'])
            ->take($limit)
            ->values()
            ->all();
    }
}
