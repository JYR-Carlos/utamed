<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Services\MensajeriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Mensajería del estudiante — hilos sostenidos por el componente.
 *
 * Se entra DESDE UN CURSO y sólo se ve ese curso. Sus componentes (Cátedra,
 * Laboratorio…) son pestañas, y en cada una el alumno tiene:
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
    /** @var Collection<int,object>|null Memoiza el listado dentro de la petición. */
    private ?Collection $componentesCache = null;

    public function __construct(private readonly MensajeriaService $mensajeria)
    {
    }

    /** Bandeja del curso: sus componentes como pestañas, con badges de no leídos. */
    public function index(Curso $curso)
    {
        $componentes = $this->componentesDelCurso($curso);
        $idUsuario = (int) Auth::id();

        $activo = $this->componenteActivo($componentes);

        // Primero el panel (abrirlo marca como leído) y después los contadores,
        // para que la pestaña recién abierta no siga mostrando su badge.
        $panel = $this->resolverPanel($componentes, $activo);

        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: false,
        );

        return Inertia::render('student/Mensajeria', [
            'curso'             => [
                'id_curso'      => (int) $curso->id_curso,
                'nombre'        => $curso->nombre,
                'cod_curso'     => (string) $curso->cod_curso,
                'agno_real'     => (int) $curso->agno_real,
                'semestre_real' => (int) $curso->semestre_real,
                'letra_grupo'   => $curso->letra_grupo,
            ],
            'componentes'       => $componentes->map(fn($c) => [
                'id_componente' => (int) $c->id_componente,
                'tipo'          => $c->tipo_componente,
                'no_leidos'     => $noLeidos[(int) $c->id_componente] ?? 0,
            ])->values()->all(),
            'componente_activo' => $activo,
            'panel'             => $panel,
        ]);
    }

    /**
     * El alumno escribe al equipo docente del componente.
     * POST /estudiante/cursos/{curso}/mensajeria/componentes/{componente}/mensaje
     */
    public function enviar(Request $request, Curso $curso, int $componente)
    {
        $visible = $this->componentesDelCurso($curso)
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

    /**
     * Panel de la pestaña activa: avisos, su conversación y a qué docentes puede
     * dirigirse. Abrirlo marca como leído lo que muestra.
     *
     * @param  Collection<int,object>  $componentes
     * @return array<string,mixed>|null
     */
    private function resolverPanel(Collection $componentes, ?int $idComponente): ?array
    {
        $componente = $idComponente
            ? $componentes->firstWhere('id_componente', $idComponente)
            : null;

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
            'difusiones'    => $difusiones->values(),
            'mensajes'      => $mensajes->values(),
            'docentes'      => $this->mensajeria->docentesDeComponente($idComponente)->values(),
        ];
    }

    /**
     * Componentes de ESTE curso donde el alumno está inscrito. Vacío significa
     * que no cursa este curso, así que no tiene mensajería que ver.
     *
     * @return Collection<int,object>
     */
    private function componentesDelCurso(Curso $curso): Collection
    {
        $this->componentesCache ??= $this->mensajeria->componentesDeEstudiante((int) Auth::id());

        $delCurso = $this->componentesCache
            ->filter(fn($c) => (int) $c->id_curso === (int) $curso->id_curso)
            ->values();

        if ($delCurso->isEmpty()) {
            abort(403, 'No estás inscrito en este curso.');
        }

        return $delCurso;
    }

    /**
     * Pestaña a abrir: la pedida por query string si es visible; si no, la
     * primera.
     *
     * @param  Collection<int,object>  $componentes
     */
    private function componenteActivo(Collection $componentes): ?int
    {
        $pedido = (int) request('componente_id');

        if ($pedido && $componentes->contains(fn($c) => (int) $c->id_componente === $pedido)) {
            return $pedido;
        }

        $primero = $componentes->first();

        return $primero ? (int) $primero->id_componente : null;
    }
}
