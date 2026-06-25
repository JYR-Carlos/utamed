<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Curso\Curso;
use App\Enums\DB\TipoMensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Centro de mensajes del docente (bandeja transversal a todos sus cursos).
 *
 * Modelo de datos:
 * - Los "mensajes" son filas de agenda.agenda. Cada fila cuelga obligatoriamente
 *   de un grupo (agenda.actividad_asignada_grupo) → actividad (agenda.actividad)
 *   → componente (curso.componente) → curso. No existe el concepto de mensaje
 *   "general" sin actividad: un mensaje "general"/grupal es el que se envía a
 *   TODOS los grupos de una actividad; uno "individual" va a un solo grupo
 *   (que puede estar conformado por un único estudiante).
 *
 * Visibilidad (recepción):
 * - Un docente sólo ve los mensajes de los cursos donde es titular
 *   (curso.id_docente_titular) más, implícitamente, lo que él mismo envió en
 *   esos cursos. Nunca ve los cursos de otros docentes.
 *
 * Tipos de mensaje considerados como conversación docente↔estudiante:
 * - "Mensaje al profesor" (lo envía el estudiante)
 * - "Feedback"            (lo envía el docente)
 */
class MensajesController extends Controller
{
    use ContaPendientesMensajes;

    /** Tipos de agenda que constituyen la conversación docente ↔ estudiante. */
    private const TIPOS_CONVERSACION = ['Mensaje al profesor', 'Feedback'];

    /**
     * Bandeja de mensajes: árbol Curso → Actividad con conteos, y (lazy) el hilo
     * de la actividad seleccionada (sus grupos con los mensajes de cada uno).
     */
    public function index()
    {
        $docente = Auth::user()->docente;

        if (!$docente) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        // Cursos donde el docente es titular (actuales + históricos).
        $cursos = Curso::where('id_docente_titular', $docente->id_docente)
            ->whereNull('fecha_eliminacion')
            ->select('id_curso', 'nombre', 'cod_curso', 'agno_real', 'semestre_real')
            ->orderByDesc('agno_real')
            ->orderByDesc('semestre_real')
            ->get();

        $cursoIds = $cursos->pluck('id_curso')->all();

        // Por (curso, actividad): totales, mensajes de estudiantes y última fecha.
        $totales = empty($cursoIds) ? collect() : DB::table('agenda.agenda as a')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->whereIn('c.id_curso', $cursoIds)
            ->whereIn('a.tipo_mensaje', self::TIPOS_CONVERSACION)
            ->groupBy('c.id_curso', 'act.id_actividad', 'act.nombre')
            ->select(
                'c.id_curso',
                'act.id_actividad',
                'act.nombre as actividad_nombre',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) FILTER (WHERE a.tipo_mensaje = 'Mensaje al profesor') as total_estudiante"),
                DB::raw('MAX(a.fecha_envio) as ultima_fecha'),
            )
            ->get();

        // "Pendientes" por actividad = grupos cuyo último mensaje es del estudiante
        // (es decir, esperan respuesta del docente).
        $pendientes = $this->pendientesPorActividad($cursoIds);

        // Todas las actividades de los cursos que tengan al menos un grupo (aunque
        // no tengan mensajes aún), para poder iniciar una conversación nueva.
        $actividadesBase = empty($cursoIds) ? collect() : DB::table('agenda.actividad as act')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad', '=', 'act.id_actividad')
            ->whereIn('c.id_curso', $cursoIds)
            ->groupBy('c.id_curso', 'act.id_actividad', 'act.nombre')
            ->select(
                'c.id_curso',
                'act.id_actividad',
                'act.nombre as actividad_nombre',
                DB::raw('COUNT(DISTINCT aag.id_actividad_asignada_grupo) as total_grupos'),
            )
            ->get();

        // Ensambla el árbol curso → actividades (incluye actividades sin mensajes).
        $totalesMap = $totales->keyBy('id_actividad');
        $basePorCurso = $actividadesBase->groupBy('id_curso');

        $cursosTree = $cursos
            ->map(function ($curso) use ($basePorCurso, $totalesMap, $pendientes) {
                $acts = ($basePorCurso[$curso->id_curso] ?? collect())
                    ->map(function ($r) use ($totalesMap, $pendientes) {
                        $t = $totalesMap[$r->id_actividad] ?? null;
                        return [
                            'id_actividad'    => $r->id_actividad,
                            'nombre'          => $r->actividad_nombre,
                            'total'           => (int) ($t->total ?? 0),
                            'total_estudiante' => (int) ($t->total_estudiante ?? 0),
                            'pendientes'      => (int) ($pendientes[$r->id_actividad] ?? 0),
                            'total_grupos'    => (int) $r->total_grupos,
                            'ultima_fecha'    => $t->ultima_fecha ?? null,
                        ];
                    })
                    // Con mensajes primero (por última fecha desc); luego sin mensajes (alfabético).
                    ->sort(function ($a, $b) {
                        if (($a['ultima_fecha'] === null) !== ($b['ultima_fecha'] === null)) {
                            return $a['ultima_fecha'] === null ? 1 : -1;
                        }
                        if ($a['ultima_fecha'] && $b['ultima_fecha']) {
                            return strcmp($b['ultima_fecha'], $a['ultima_fecha']);
                        }
                        return strcmp($a['nombre'], $b['nombre']);
                    })
                    ->values();

                return [
                    'id_curso'      => $curso->id_curso,
                    'nombre'        => $curso->nombre,
                    'cod_curso'     => $curso->cod_curso,
                    'agno_real'     => $curso->agno_real,
                    'semestre_real' => $curso->semestre_real,
                    'pendientes'    => $acts->sum('pendientes'),
                    'actividades'   => $acts,
                ];
            })
            ->filter(fn($c) => count($c['actividades']) > 0)
            ->values();

        return Inertia::render('docente/Mensajes', [
            'cursos' => $cursosTree,
            // Hilo de la actividad seleccionada — sólo se resuelve cuando llega ?actividad_id
            'hilo'   => Inertia::lazy(fn() => $this->resolverHilo($cursoIds)),
        ]);
    }

    /**
     * Resuelve el hilo de una actividad: sus grupos (con integrantes) y los
     * mensajes de cada grupo. Valida que la actividad pertenezca a un curso del
     * docente (titular).
     */
    private function resolverHilo(array $cursoIds): ?array
    {
        $actividadId = request('actividad_id');
        if (!$actividadId) {
            return null;
        }

        $actividad = Actividad::with('componente')->find($actividadId);
        if (!$actividad || !in_array($actividad->componente?->id_curso, $cursoIds, true)) {
            return null;
        }

        // Grupos de la actividad (incluye los que aún no tienen mensajes).
        $grupos = DB::table('agenda.actividad_asignada_grupo as aag')
            ->where('aag.id_actividad', $actividadId)
            ->orderBy('aag.id_actividad_asignada_grupo')
            ->select('aag.id_actividad_asignada_grupo as grupo')
            ->get();

        $grupoIds = $grupos->pluck('grupo')->all();

        // Integrantes por grupo (para etiquetar individual vs. grupal).
        $integrantes = empty($grupoIds) ? collect() : DB::table('agenda.integrante_grupo as ig')
            ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'ig.id_estudiante')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->whereIn('ig.id_actividad_asignada_grupo', $grupoIds)
            ->select(
                'ig.id_actividad_asignada_grupo as grupo',
                DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.apellido1,''))) as nombre"),
            )
            ->get()
            ->groupBy('grupo');

        // Mensajes de todos los grupos de la actividad.
        $mensajes = empty($grupoIds) ? collect() : DB::table('agenda.agenda as a')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
            ->whereIn('a.id_actividad_asignada_grupo', $grupoIds)
            ->whereIn('a.tipo_mensaje', self::TIPOS_CONVERSACION)
            ->orderBy('a.fecha_envio', 'asc')
            ->select(
                'a.id_agenda',
                'a.fecha_envio',
                'a.mensaje',
                'a.tipo_mensaje as tipo_registro',
                'a.id_actividad_asignada_grupo as grupo',
                'u.id_usuario as emisor_id_usuario',
                DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,''))) as emisor_nombre"),
            )
            ->get()
            ->groupBy('grupo');

        $hilos = $grupos->map(function ($g) use ($integrantes, $mensajes) {
            $miembros = ($integrantes[$g->grupo] ?? collect())->pluck('nombre')->values();
            return [
                'grupo'        => $g->grupo,
                'integrantes'  => $miembros,
                'es_individual' => $miembros->count() === 1,
                'mensajes'     => ($mensajes[$g->grupo] ?? collect())->map(fn($m) => [
                    'id_agenda'    => $m->id_agenda,
                    'fecha_envio'  => $m->fecha_envio,
                    'mensaje'      => $m->mensaje,
                    'es_docente'   => $m->tipo_registro === 'Feedback',
                    'emisor'       => $m->emisor_nombre,
                    'tipo'         => $m->tipo_registro,
                ])->values(),
            ];
        })->values();

        return [
            'id_actividad' => $actividad->id_actividad,
            'nombre'       => $actividad->nombre,
            'id_curso'     => $actividad->componente?->id_curso,
            'hilos'        => $hilos,
        ];
    }

    // El cálculo de "pendientes por actividad" vive en el trait ContaPendientesMensajes
    // (compartido con DashboardController) para evitar duplicar el DISTINCT ON.

    /**
     * El docente envía un mensaje a TODOS los grupos de una actividad (grupal)
     * o a un grupo específico (individual). Se registra como "Feedback".
     *
     * POST docente/mensajes/cursos/{curso}/actividades/{actividad}/enviar
     */
    public function send(Request $request, Curso $curso, Actividad $actividad)
    {
        $docente = Auth::user()->docente;

        // Sólo el titular del curso puede enviar (visibilidad = sus cursos).
        if (!$docente || $curso->id_docente_titular !== $docente->id_docente) {
            abort(403, 'No tienes acceso a este curso.');
        }

        // La actividad debe pertenecer a este curso.
        if ($actividad->componente?->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        $validated = $request->validate([
            'mensaje' => 'required|string|max:2000',
            // 'todos' = todos los grupos de la actividad; o el id de un grupo.
            'destino' => 'required',
        ]);

        $grupoIds = $this->resolverDestino($actividad, $validated['destino']);

        if (empty($grupoIds)) {
            return back()->withErrors([
                'mensaje' => 'La actividad no tiene grupos a los que enviar el mensaje.',
            ]);
        }

        $ahora = now();
        $filas = array_map(fn($grupoId) => [
            'mensaje'                     => $validated['mensaje'],
            'id_usuario_emisor'           => Auth::id(),
            'id_actividad_asignada_grupo' => $grupoId,
            'tipo_mensaje'                => TipoMensaje::FEEDBACK->value,
            'fecha_envio'                 => $ahora,
        ], $grupoIds);

        DB::table('agenda.agenda')->insert($filas);

        $msg = count($grupoIds) > 1
            ? 'Mensaje enviado a ' . count($grupoIds) . ' grupos.'
            : 'Mensaje enviado.';

        return back()->with('success', $msg);
    }

    /**
     * Resuelve los grupos destino: 'todos' → todos los grupos de la actividad;
     * numérico → ese grupo (validando que pertenezca a la actividad).
     *
     * @return array<int,int>
     */
    private function resolverDestino(Actividad $actividad, $destino): array
    {
        if ($destino === 'todos') {
            return ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
                ->pluck('id_actividad_asignada_grupo')
                ->all();
        }

        $grupoId = (int) $destino;
        $existe = ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $grupoId)
            ->where('id_actividad', $actividad->id_actividad)
            ->exists();

        return $existe ? [$grupoId] : [];
    }
}
