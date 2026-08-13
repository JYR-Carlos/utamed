<?php

namespace App\Http\Controllers\Concerns;

use App\Services\MensajeriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Bandeja de mensajería para el personal del curso (docentes y ayudantes).
 *
 * Docentes y ayudantes hacen exactamente lo mismo; sólo cambia qué componentes
 * ven. Cada controlador implementa `componentesVisibles()` y el resto se comparte.
 *
 * Estructura de la vista: curso → componente → (difusiones | canales de alumno).
 * El canal es (componente, alumno): todos los docentes del componente escriben
 * en el mismo canal, así que un colegiado que responde no abre una conversación
 * aparte.
 */
trait GestionaMensajeriaStaff
{
    /** @var Collection<int,object>|null Memoiza el listado dentro de la petición. */
    private ?Collection $componentesCache = null;

    /**
     * Componentes que este usuario puede ver, con las columnas que devuelve
     * MensajeriaService::sqlComponentes().
     *
     * @return Collection<int,object>
     */
    abstract protected function resolverComponentesVisibles(): Collection;

    /**
     * @return Collection<int,object>
     */
    private function componentesVisibles(): Collection
    {
        return $this->componentesCache ??= $this->resolverComponentesVisibles();
    }

    /** Componente Inertia a renderizar (p. ej. 'docente/Mensajeria'). */
    abstract protected function vistaMensajeria(): string;

    /** Prefijo de las rutas del rol (p. ej. '/docente/mensajeria'). */
    abstract protected function baseRutaMensajeria(): string;

    /**
     * Bandeja: árbol curso → componente con badges de no leídos. El panel de la
     * derecha se resuelve de forma diferida (?componente_id=…&alumno_id=…).
     */
    public function index(MensajeriaService $mensajeria)
    {
        $componentes = $this->componentesVisibles();
        $idUsuario = (int) Auth::id();

        $noLeidos = $mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: true,
        );

        return Inertia::render($this->vistaMensajeria(), [
            'cursos'    => $this->agruparPorCurso($componentes, $noLeidos),
            'base_ruta' => $this->baseRutaMensajeria(),
            'es_staff'  => true,
            'panel'     => Inertia::lazy(fn() => $this->resolverPanel($mensajeria, $componentes)),
        ]);
    }

    /**
     * Panel derecho: difusiones del componente, lista de canales y —si se pidió
     * un alumno— la conversación de ese canal. Abrir el panel marca como leído
     * lo que se muestra.
     *
     * @param  Collection<int,object>  $componentes
     * @return array<string,mixed>|null
     */
    private function resolverPanel(MensajeriaService $mensajeria, Collection $componentes): ?array
    {
        $idComponente = (int) request('componente_id');
        $componente = $componentes->firstWhere('id_componente', $idComponente);

        if (!$componente) {
            return null;
        }

        $idUsuario = (int) Auth::id();

        $difusiones = $mensajeria->difusionesDeComponente($idComponente, $idUsuario);
        $canales = $mensajeria->canalesDeComponente($idComponente, $idUsuario);

        $mensajeria->marcarLeidos($difusiones->pluck('id_mensaje')->all(), $idUsuario);

        $canal = null;
        $idAlumno = (int) request('alumno_id');

        // El alumno debe estar inscrito en ESE componente: la lista de canales
        // ya sale de curso.inscripcion_componente, así que sirve de validación.
        if ($idAlumno && $canales->contains(fn($c) => $c['id_alumno'] === $idAlumno)) {
            $mensajes = $mensajeria->mensajesDelCanal($idComponente, $idAlumno, $idUsuario);
            $mensajeria->marcarLeidos($mensajes->pluck('id_mensaje')->all(), $idUsuario);

            $canal = [
                'id_alumno' => $idAlumno,
                'alumno'    => $canales->firstWhere('id_alumno', $idAlumno)['alumno'] ?? '',
                'mensajes'  => $mensajes->values(),
            ];
        }

        return [
            'id_componente' => $idComponente,
            'componente'    => $componente->tipo_componente,
            'curso'         => $componente->curso_nombre,
            'difusiones'    => $difusiones->values(),
            'canales'       => $canales->values(),
            'canal'         => $canal,
        ];
    }

    /**
     * Difusión a todo el componente.
     * POST {base}/componentes/{componente}/difusion
     */
    public function enviarDifusion(Request $request, int $componente, MensajeriaService $mensajeria)
    {
        $this->autorizarComponente($componente);

        $datos = $request->validate([
            'tema'    => 'required|string|max:150',
            'mensaje' => 'required|string|max:2000',
        ]);

        $mensajeria->enviarDifusion(
            $componente,
            (int) Auth::id(),
            $datos['mensaje'],
            $datos['tema'],
        );

        return back()->with('success', 'Aviso enviado a todo el componente.');
    }

    /**
     * Mensaje al canal de un alumno.
     * POST {base}/componentes/{componente}/alumnos/{alumno}/mensaje
     */
    public function enviarMensaje(Request $request, int $componente, int $alumno, MensajeriaService $mensajeria)
    {
        $this->autorizarComponente($componente);

        $datos = $request->validate([
            'mensaje' => 'required|string|max:2000',
            'tema'    => 'nullable|string|max:150',
        ]);

        $idUsuario = (int) Auth::id();

        // El destinatario debe estar inscrito en este componente.
        $esAlumnoDelComponente = $mensajeria
            ->canalesDeComponente($componente, $idUsuario)
            ->contains(fn($c) => $c['id_alumno'] === $alumno);

        if (!$esAlumnoDelComponente) {
            abort(404, 'El alumno no está inscrito en este componente.');
        }

        $mensajeria->enviarIndividual(
            idComponente: $componente,
            idUsuarioEmisor: $idUsuario,
            idUsuarioReceptor: $alumno,
            idUsuarioAlumno: $alumno,
            mensaje: $datos['mensaje'],
            tema: $datos['tema'] ?? null,
        );

        return back()->with('success', 'Mensaje enviado.');
    }

    /** Aborta si el componente no está entre los visibles para este usuario. */
    private function autorizarComponente(int $idComponente): void
    {
        $visible = $this->componentesVisibles()
            ->contains(fn($c) => (int) $c->id_componente === $idComponente);

        if (!$visible) {
            abort(403, 'No tienes acceso a este componente.');
        }
    }

    /**
     * Agrupa los componentes por curso y suma los no leídos de cada uno.
     *
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
