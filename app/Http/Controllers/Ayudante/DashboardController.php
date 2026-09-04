<?php

namespace App\Http\Controllers\Ayudante;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\ProgramaHistorial;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\MensajeriaService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard del ayudante — /ayudante/dashboard.
 *
 * Pantalla propia del rol, acotada a lo que un ayudante realmente hace:
 * redactar/editar el syllabus de sus cursos y responder la mensajería del
 * componente. Sin cifras de administración (usuarios, asistencia, notas,
 * delegación) — ver docs_ui_mockups/rol_ayudante.md.
 */
class DashboardController extends Controller
{
    /**
     * GET ayudante/dashboard
     */
    public function index(MensajeriaService $mensajeria): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Consulta directa a UsuarioRolAsignacion porque rolesAsignados() no
        // expone id_contexto en el pivot (mismo criterio que CourseController).
        $rolAyudante = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();

        $contextosAsignados = $rolAyudante
            ? UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
                ->where('id_rol', $rolAyudante->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->pluck('id_contexto')
            : collect();

        // Período vigente = par (agno_real, semestre_real) más reciente con
        // cursos en todo el sistema (mismo criterio que Docente\DashboardController).
        $periodo = $this->resolvePeriodoVigente();

        $cursosQuery = Curso::whereIn('id_contexto', $contextosAsignados)
            ->whereNull('fecha_eliminacion');

        if ($periodo) {
            $cursosQuery->where('agno_real', $periodo['ano'])->where('semestre_real', $periodo['sem']);
        }

        $cursos = $cursosQuery
            ->with(['programas' => fn($q) => $q->where('es_actual', true)->with('autor')])
            ->orderBy('nombre')
            ->get();

        $historialPorPrograma = $this->historialMasRecientePorPrograma(
            $cursos->flatMap(fn(Curso $c) => $c->programas)->pluck('id_programa')
        );

        $idUsuario = (int) $user->id_usuario;

        $cursosData = $cursos->map(function (Curso $curso) use ($historialPorPrograma, $idUsuario) {
            $programa = $curso->programas->first();
            $syllabus = $this->resolverEstadoSyllabus($programa, $historialPorPrograma, $idUsuario);

            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'letra_grupo' => $curso->letra_grupo,
                'agno_real' => $curso->agno_real !== null ? (int) $curso->agno_real : null,
                'semestre_real' => $curso->semestre_real !== null ? (int) $curso->semestre_real : null,
                'estado_syllabus' => $syllabus['estado'],
                'detalle' => $syllabus['detalle'],
                'rechazo' => $syllabus['rechazo'],
            ];
        })->values();

        $mensajeriaCursos = $this->mensajeriaPorCurso($mensajeria, $cursos, $idUsuario);

        $estudiante = $user->estudiante;

        return Inertia::render('ayudante/Dashboard', [
            'stats' => [
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
                'total_cursos' => $cursosData->count(),
            ],
            'periodo' => $periodo,
            'cursos' => $cursosData,
            'mensajeria' => [
                'no_leidos' => $mensajeriaCursos->sum('no_leidos'),
                'cursos' => $mensajeriaCursos,
            ],
            'esEstudiante' => (bool) $estudiante,
            'carreraEstudiante' => $estudiante?->carrera?->nombre,
            'nombramiento' => $this->fechaNombramiento($rolAyudante, $idUsuario),
        ]);
    }

    /**
     * Fecha del nombramiento como ayudante: el alta más antigua todavía vigente
     * en usuario.usuario_rol_asignacion (columna fecha_creacion, que la BD pone
     * con DEFAULT now() al insertar la fila). Sólo la usa el estado vacío, para
     * que un ayudante recién nombrado y aún sin cursos vea que su alta existe
     * en vez de un hueco sin explicación.
     */
    private function fechaNombramiento(?Rol $rolAyudante, int $idUsuario): ?string
    {
        if (!$rolAyudante) {
            return null;
        }

        return UsuarioRolAsignacion::where('id_usuario', $idUsuario)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->min('fecha_creacion');
    }

    /**
     * Período vigente = par (agno_real, semestre_real) más reciente con
     * cursos en todo el sistema. No hay tabla de calendario académico, así
     * que se aproxima con el dato real más reciente (mismo criterio que
     * Docente\DashboardController::resolvePeriodoVigente).
     *
     * @return array{ano:int,sem:int,fecha_inicio:?string}|null
     */
    private function resolvePeriodoVigente(): ?array
    {
        $row = Curso::whereNull('fecha_eliminacion')
            ->whereNotNull('agno_real')
            ->whereNotNull('semestre_real')
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->first(['agno_real', 'semestre_real']);

        if (!$row) {
            return null;
        }

        $fechaInicio = Curso::whereNull('fecha_eliminacion')
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
     * Último evento de auditoria.programa_historial por programa. Los
     * triggers de BD (fn_programa_creado / fn_programa_modificado) registran
     * un evento en cada versión nueva y en cada cambio de estado sobre la
     * misma fila, así que el más reciente es la fecha real del último
     * movimiento del syllabus (edición, envío o resolución).
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
     * Traduce el estado de un Programa al estado que ve el ayudante, con el
     * detalle real (fecha + quién) del último movimiento. RECHAZADO es un
     * BORRADOR cuyo último evento de historial fue un rechazo — el estado no
     * alcanza por sí solo, hay que desambiguar con el historial (mismo
     * criterio que Docente\DashboardController::resolverEstadoSyllabus).
     *
     * @return array{estado:string, detalle:?array{fecha:?string,por:?string}, rechazo:?array{razon:string,por:?string,fecha:?string}}
     */
    private function resolverEstadoSyllabus(?Programa $programa, Collection $historialPorPrograma, int $idUsuarioActual): array
    {
        if (!$programa) {
            return ['estado' => 'NO_INICIADO', 'detalle' => null, 'rechazo' => null];
        }

        $estado = match ($programa->estado) {
            'BORRADOR', 'BASICO_COMPLETO' => 'BORRADOR',
            'COMPLETO' => 'EN_REVISION',
            'APROBADO', 'PUBLICADO' => 'APROBADO',
            default => 'NO_INICIADO',
        };

        /** @var ProgramaHistorial|null $ultimoEvento */
        $ultimoEvento = $historialPorPrograma->get($programa->id_programa);

        $fechaRaw = $ultimoEvento?->fecha_accion ?? $programa->fecha_creacion;
        $fecha = $fechaRaw ? Carbon::parse($fechaRaw)->format('d-m-Y') : null;

        $actor = $ultimoEvento?->usuario ?? $programa->autor;
        $actorLabel = $actor
            ? ((int) $actor->id_usuario === $idUsuarioActual ? 'ti' : trim("{$actor->nombre1} {$actor->apellido1}"))
            : null;

        if ($estado === 'BORRADOR' && $ultimoEvento && $ultimoEvento->accion === 'RECHAZO') {
            return [
                'estado' => 'RECHAZADO',
                'detalle' => null,
                'rechazo' => [
                    'razon' => $ultimoEvento->observaciones,
                    'por' => $actorLabel,
                    'fecha' => $fecha,
                ],
            ];
        }

        return [
            'estado' => $estado,
            'detalle' => ['fecha' => $fecha, 'por' => $actorLabel],
            'rechazo' => null,
        ];
    }

    /**
     * Mensajería de nivel curso (curso.mensaje) sin leer para TODOS los
     * cursos donde el usuario es ayudante en el período vigente — incluidos
     * los que no tienen mensajes nuevos, para que la bandeja muestre el
     * panorama completo en vez de sólo lo pendiente (a diferencia de
     * Docente\DashboardController::mensajeriaPorCurso, que sólo lista cursos
     * con no_leidos > 0).
     *
     * Un curso puede tener varios componentes: los no leídos se suman y la
     * fecha del último es la más reciente de todos ellos.
     *
     * @return Collection<int,array{id_curso:int,nombre:string,cod_curso:string,letra_grupo:?string,no_leidos:int,ultimo_no_leido:?string}>
     */
    private function mensajeriaPorCurso(MensajeriaService $mensajeria, Collection $cursos, int $idUsuario): Collection
    {
        $componentes = $mensajeria->componentesDeAyudante($idUsuario);
        $componenteIds = $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all();

        $noLeidos = $mensajeria->noLeidosPorComponente($componenteIds, $idUsuario, esStaff: true);
        $ultimos = $mensajeria->ultimoNoLeidoPorComponente($componenteIds, $idUsuario, esStaff: true);

        $porCurso = $componentes->groupBy('id_curso');

        $noLeidosPorCurso = $porCurso
            ->map(fn($delCurso) => $delCurso->sum(fn($c) => $noLeidos[(int) $c->id_componente] ?? 0));

        // Los timestamps de Postgres llegan como 'YYYY-MM-DD HH:MM:SS', que
        // ordena igual como texto que como instante, así que max() basta.
        $ultimoPorCurso = $porCurso
            ->map(fn($delCurso) => $delCurso
                ->map(fn($c) => $ultimos[(int) $c->id_componente] ?? null)
                ->filter()
                ->max());

        return $cursos
            ->map(fn(Curso $curso) => [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'letra_grupo' => $curso->letra_grupo,
                'no_leidos' => (int) ($noLeidosPorCurso[$curso->id_curso] ?? 0),
                'ultimo_no_leido' => $ultimoPorCurso[$curso->id_curso] ?? null,
            ])
            ->sortByDesc('no_leidos')
            ->values();
    }
}
