<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Curso\Curso;
use App\Enums\DB\TipoMensaje;
use App\Services\Agenda\LecturaAgendaService;
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
     * Bandeja de mensajes: las actividades de los cursos del docente, cada una
     * con los mensajes que aún no ha visto. Las conversaciones no se leen aquí:
     * cada fila lleva a la página de la actividad y abre la agenda del grupo
     * (ahí se marcan como vistas). Así hay una sola forma de ver la agenda.
     *
     * El estado de cada actividad (Actividad::calcularEstadoBase) deja separar
     * las activas de las que ya no admiten conversación (cerradas, no visibles
     * o planificadas), que la pantalla esconde salvo que se pidan.
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
            ->get()
            ->keyBy('id_curso');

        $cursoIds = $cursos->keys()->all();

        // Actividades con al menos un grupo: sin grupos no hay con quién hablar.
        $actividades = empty($cursoIds) ? collect() : Actividad::query()
            ->whereHas('componente', fn ($q) => $q->whereIn('id_curso', $cursoIds))
            ->whereHas('actividadAsignadaGrupos')
            ->with('componente:id_componente,id_curso')
            ->get();

        // No vistos por grupo; por actividad se suman y se recuerda el grupo
        // más reciente, que es la agenda que abre el clic.
        $noLeidos = empty($cursoIds) ? collect() : (new LecturaAgendaService)->noLeidosPorGrupo(
            Auth::id(),
            self::TIPOS_CONVERSACION,
            fn ($q) => $q->whereIn('c.id_curso', $cursoIds),
        )->groupBy('id_actividad');

        $pendientes = $this->pendientesPorActividad($cursoIds);

        $filas = $actividades->map(function (Actividad $act) use ($cursos, $noLeidos, $pendientes) {
            $curso = $cursos[$act->componente->id_curso];
            $grupos = $noLeidos[$act->id_actividad] ?? collect();
            $masReciente = $grupos->sortByDesc('ultima_fecha')->first();

            return [
                'id_actividad'  => $act->id_actividad,
                'nombre'        => $act->nombre,
                'estado'        => $act->calcularEstadoBase(),
                'fecha_limite'  => $act->fecha_limite?->format('Y-m-d'),
                'no_leidos'     => (int) $grupos->sum('no_leidos'),
                'grupos_con_no_leidos' => $grupos->count(),
                'grupo_a_abrir' => $masReciente?->grupo,
                'ultima_fecha'  => $masReciente?->ultima_fecha,
                'pendientes'    => (int) ($pendientes[$act->id_actividad] ?? 0),
                'curso' => [
                    'id_curso'      => $curso->id_curso,
                    'nombre'        => $curso->nombre,
                    'cod_curso'     => $curso->cod_curso,
                    'agno_real'     => $curso->agno_real,
                    'semestre_real' => $curso->semestre_real,
                ],
            ];
        })->values();

        return Inertia::render('docente/Mensajes', [
            'actividades' => $filas,
        ]);
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
