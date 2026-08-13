<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\MensajeriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Mensajería del estudiante — hilos sostenidos por el componente.
 *
 * El alumno ve, por cada componente donde está inscrito:
 *  - los avisos del equipo docente (difusiones), y
 *  - su propia conversación con ese equipo.
 *
 * Su conversación es el canal (componente, él mismo). Elige a qué docente dirige
 * el mensaje, pero lo ven todos los docentes del componente, así que puede
 * responderle cualquiera de ellos sin abrir un hilo aparte.
 *
 * Nada de esto pasa por agenda.agenda: los mensajes de entrega y feedback de
 * actividades siguen viviendo allí, separados.
 */
class MensajeriaController extends Controller
{
    public function __construct(private readonly MensajeriaService $mensajeria)
    {
    }

    /** Bandeja: sus componentes agrupados por curso, con badges de no leídos. */
    public function index()
    {
        $componentes = $this->componentesVisibles();
        $idUsuario = (int) Auth::id();

        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: false,
        );

        return Inertia::render('student/Mensajeria', [
            'cursos' => $this->agruparPorCurso($componentes, $noLeidos),
            'panel'  => Inertia::lazy(fn() => $this->resolverPanel($componentes)),
        ]);
    }

    /**
     * Panel del componente elegido: avisos + su conversación + a qué docentes
     * puede dirigirse. Abrirlo marca como leído lo que se muestra.
     *
     * @param  Collection<int,object>  $componentes
     * @return array<string,mixed>|null
     */
    private function resolverPanel(Collection $componentes): ?array
    {
        $idComponente = (int) request('componente_id');
        $componente = $componentes->firstWhere('id_componente', $idComponente);

        if (!$componente) {
            return null;
        }

        $idUsuario = (int) Auth::id();

        $difusiones = $this->mensajeria->difusionesDeComponente($idComponente, $idUsuario);
        $mensajes = $this->mensajeria->mensajesDelCanal($idComponente, $idUsuario, $idUsuario);

        $this->mensajeria->marcarLeidos(
            $difusiones->pluck('id_mensaje')->merge($mensajes->pluck('id_mensaje'))->all(),
            $idUsuario,
        );

        return [
            'id_componente' => $idComponente,
            'componente'    => $componente->tipo_componente,
            'curso'         => $componente->curso_nombre,
            'difusiones'    => $difusiones->values(),
            'mensajes'      => $mensajes->values(),
            'docentes'      => $this->mensajeria->docentesDeComponente($idComponente)->values(),
        ];
    }

    /**
     * El alumno escribe al equipo docente del componente.
     * POST /estudiante/mensajeria/componentes/{componente}/mensaje
     */
    public function enviar(Request $request, int $componente)
    {
        $visible = $this->componentesVisibles()
            ->contains(fn($c) => (int) $c->id_componente === $componente);

        if (!$visible) {
            abort(403, 'No estás inscrito en este componente.');
        }

        $datos = $request->validate([
            'mensaje'             => 'required|string|max:2000',
            'tema'                => 'nullable|string|max:150',
            'id_usuario_receptor' => 'nullable|integer',
        ]);

        $docentes = $this->mensajeria->docentesDeComponente($componente);

        if ($docentes->isEmpty()) {
            return back()->withErrors([
                'mensaje' => 'Este componente todavía no tiene docentes asignados.',
            ]);
        }

        // Destinatario: el elegido si pertenece al equipo del componente; si no,
        // el titular (y en su defecto, el primero de la lista).
        $elegido = $datos['id_usuario_receptor'] ?? null;
        $receptor = $docentes->firstWhere('id_usuario', $elegido)
            ?? $docentes->firstWhere('es_titular', true)
            ?? $docentes->first();

        $idUsuario = (int) Auth::id();

        $this->mensajeria->enviarIndividual(
            idComponente: $componente,
            idUsuarioEmisor: $idUsuario,
            idUsuarioReceptor: (int) $receptor['id_usuario'],
            idUsuarioAlumno: $idUsuario,
            mensaje: $datos['mensaje'],
            tema: $datos['tema'] ?? null,
        );

        return back()->with('success', 'Mensaje enviado.');
    }

    /** @return Collection<int,object> */
    private function componentesVisibles(): Collection
    {
        return $this->mensajeria->componentesDeEstudiante((int) Auth::id());
    }

    /**
     * @param  Collection<int,object>  $componentes
     * @param  array<int,int>  $noLeidos
     * @return array<int,array<string,mixed>>
     */
    private function agruparPorCurso(Collection $componentes, array $noLeidos): array
    {
        return $componentes
            ->groupBy('id_curso')
            ->map(function (Collection $delCurso) use ($noLeidos) {
                $primero = $delCurso->first();

                $items = $delCurso->map(fn($c) => [
                    'id_componente' => (int) $c->id_componente,
                    'tipo'          => $c->tipo_componente,
                    'no_leidos'     => $noLeidos[(int) $c->id_componente] ?? 0,
                ])->values();

                return [
                    'id_curso'      => (int) $primero->id_curso,
                    'nombre'        => $primero->curso_nombre,
                    'cod_curso'     => (string) $primero->cod_curso,
                    'agno_real'     => (int) $primero->agno_real,
                    'semestre_real' => (int) $primero->semestre_real,
                    'letra_grupo'   => $primero->letra_grupo,
                    'no_leidos'     => $items->sum('no_leidos'),
                    'componentes'   => $items,
                ];
            })
            ->values()
            ->all();
    }
}
