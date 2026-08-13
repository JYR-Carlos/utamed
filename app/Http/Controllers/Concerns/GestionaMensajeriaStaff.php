<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Curso\Curso;
use App\Services\MensajeriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Bandeja de mensajería para el personal del curso (docentes y ayudantes).
 *
 * Se entra DESDE UN CURSO: la bandeja nunca lista los demás cursos del usuario.
 * Dentro del curso, cada componente (Cátedra, Laboratorio, Taller…) es una
 * pestaña, y dentro de la pestaña hay dos cosas:
 *
 *  - Generales   : difusiones al componente (MENSAJE_PARA_TODO_EL_CURSO).
 *  - Individuales: un canal por alumno (MENSAJE_INDIVIDUAL). El canal es
 *                  (componente, alumno) y lo comparten todos los docentes del
 *                  componente, así que un colegiado que responde no abre una
 *                  conversación aparte.
 *
 * Docentes y ayudantes hacen exactamente lo mismo; sólo cambia qué componentes
 * ven. Cada controlador implementa `resolverComponentesVisibles()` y el resto se
 * comparte.
 */
trait GestionaMensajeriaStaff
{
    /** @var Collection<int,object>|null Memoiza el listado dentro de la petición. */
    private ?Collection $componentesCache = null;

    /**
     * Todos los componentes que este usuario puede ver, con las columnas que
     * devuelve MensajeriaService::sqlComponentes(). El filtrado por curso lo
     * hace el trait: así el criterio de visibilidad de cada rol vive en un solo
     * sitio y sirve también para autorizar los envíos.
     *
     * @return Collection<int,object>
     */
    abstract protected function resolverComponentesVisibles(): Collection;

    /** Componente Inertia a renderizar (p. ej. 'docente/Mensajeria'). */
    abstract protected function vistaMensajeria(): string;

    /** Prefijo de las rutas del rol para ESTE curso. */
    abstract protected function baseRutaMensajeria(Curso $curso): string;

    /**
     * Bandeja del curso: sus componentes como pestañas y, dentro de la activa,
     * las difusiones y los canales de alumno.
     */
    public function index(Curso $curso, MensajeriaService $mensajeria)
    {
        $componentes = $this->componentesDelCurso($curso);
        $idUsuario = (int) Auth::id();

        $activo = $this->componenteActivo($componentes);

        // El panel se resuelve antes que los contadores porque abrirlo marca
        // como leído lo que muestra: así la pestaña recién abierta aparece ya
        // sin badge en vez de quedarse con el número de la visita anterior.
        $panel = $this->resolverPanel($mensajeria, $componentes, $activo);

        $noLeidos = $mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: true,
        );

        return Inertia::render($this->vistaMensajeria(), [
            'curso'             => $this->datosCurso($curso),
            'componentes'       => $this->pestanas($componentes, $noLeidos),
            'componente_activo' => $activo,
            'base_ruta'         => $this->baseRutaMensajeria($curso),
            'panel'             => $panel,
        ]);
    }

    /**
     * Difusión a todo el componente.
     * POST {base}/componentes/{componente}/difusion
     */
    public function enviarDifusion(Request $request, Curso $curso, int $componente, MensajeriaService $mensajeria)
    {
        $this->autorizarComponente($curso, $componente);

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
    public function enviarMensaje(Request $request, Curso $curso, int $componente, int $alumno, MensajeriaService $mensajeria)
    {
        $this->autorizarComponente($curso, $componente);

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

    /**
     * Panel de la pestaña activa: difusiones, lista de canales y —si se pidió un
     * alumno— la conversación de ese canal. Abrirlo marca como leído lo que
     * muestra.
     *
     * @param  Collection<int,object>  $componentes
     * @return array<string,mixed>|null
     */
    private function resolverPanel(MensajeriaService $mensajeria, Collection $componentes, ?int $idComponente): ?array
    {
        $componente = $idComponente
            ? $componentes->firstWhere('id_componente', $idComponente)
            : null;

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
            'difusiones'    => $difusiones->values(),
            'canales'       => $canales->values(),
            'canal'         => $canal,
        ];
    }

    /**
     * Componentes visibles de ESTE curso. Vacío significa que el usuario no
     * tiene nada que hacer aquí, no que el curso no tenga componentes.
     *
     * @return Collection<int,object>
     */
    private function componentesDelCurso(Curso $curso): Collection
    {
        $this->componentesCache ??= $this->resolverComponentesVisibles();

        $delCurso = $this->componentesCache
            ->filter(fn($c) => (int) $c->id_curso === (int) $curso->id_curso)
            ->values();

        if ($delCurso->isEmpty()) {
            abort(403, 'No tienes acceso a la mensajería de este curso.');
        }

        return $delCurso;
    }

    /**
     * Pestaña a abrir: la pedida por query string si es visible; si no, la
     * primera. El alumno llega con ?componente_id=… al cambiar de pestaña.
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

    /** Aborta si el componente no pertenece a los visibles de este curso. */
    private function autorizarComponente(Curso $curso, int $idComponente): void
    {
        $visible = $this->componentesDelCurso($curso)
            ->contains(fn($c) => (int) $c->id_componente === $idComponente);

        if (!$visible) {
            abort(403, 'No tienes acceso a este componente.');
        }
    }

    /** @return array<string,mixed> */
    private function datosCurso(Curso $curso): array
    {
        return [
            'id_curso'      => (int) $curso->id_curso,
            'nombre'        => $curso->nombre,
            'cod_curso'     => (string) $curso->cod_curso,
            'agno_real'     => (int) $curso->agno_real,
            'semestre_real' => (int) $curso->semestre_real,
            'letra_grupo'   => $curso->letra_grupo,
        ];
    }

    /**
     * Componentes del curso como pestañas, con su badge de no leídos.
     *
     * @param  Collection<int,object>  $componentes
     * @param  array<int,int>  $noLeidos
     * @return array<int,array<string,mixed>>
     */
    private function pestanas(Collection $componentes, array $noLeidos): array
    {
        return $componentes
            ->map(fn($c) => [
                'id_componente' => (int) $c->id_componente,
                'tipo'          => $c->tipo_componente,
                'no_leidos'     => $noLeidos[(int) $c->id_componente] ?? 0,
            ])
            ->values()
            ->all();
    }
}
