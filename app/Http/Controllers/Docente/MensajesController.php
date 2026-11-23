<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Curso\Curso;
use App\Enums\DB\TipoMensaje;
use App\Services\Docente\BuzonDocente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Mensajes del docente: el buzón de todos sus cursos y el envío de mensajes a
 * los grupos de una actividad.
 *
 * El buzón ({@see BuzonDocente}) junta la mensajería de los cursos
 * (curso.mensaje) y la agenda de las actividades (agenda.agenda), como el del
 * alumno. El envío escribe en la agenda, que cuelga siempre de un grupo
 * (agenda.actividad_asignada_grupo → actividad → componente → curso): un
 * mensaje grupal es el que va a TODOS los grupos de una actividad y uno
 * individual va a un solo grupo, que puede ser de un único estudiante.
 */
class MensajesController extends Controller
{
    /**
     * El buzón: una fila por conversación, con su origen. Es sólo un índice;
     * cada fila abre la conversación en su lugar, que es donde se marca leída.
     */
    public function index(BuzonDocente $buzon)
    {
        $docente = Auth::user()->docente;

        if (!$docente) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        return Inertia::render('docente/Mensajes', $buzon->bandeja((int) Auth::id(), (int) $docente->id_docente));
    }

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
