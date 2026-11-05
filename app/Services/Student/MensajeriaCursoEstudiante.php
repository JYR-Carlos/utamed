<?php

namespace App\Services\Student;

use App\Models\Curso\Curso;
use App\Models\Curso\Mensaje;
use App\Services\MensajeriaService;
use Illuminate\Support\Collection;

/**
 * Mensajería del alumno en un curso — hilos sostenidos por el componente.
 *
 * Es la pestaña «Mensajería» de la ficha del curso, así que el curso ya está
 * elegido y aquí sólo se navegan sus componentes (Cátedra, Laboratorio…). En
 * cada uno el alumno tiene:
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
class MensajeriaCursoEstudiante
{
    public function __construct(private readonly MensajeriaService $mensajeria)
    {
    }

    /**
     * Componentes de ESTE curso donde el alumno está inscrito. Vacío significa
     * que no tiene mensajería que ver en el curso.
     *
     * @return Collection<int,object>
     */
    public function componentesDelCurso(Curso $curso, int $idUsuario): Collection
    {
        return $this->mensajeria->componentesDeEstudiante($idUsuario)
            ->filter(fn($c) => (int) $c->id_curso === (int) $curso->id_curso)
            ->values();
    }

    /**
     * Bandeja del curso: sus componentes con badges de no leídos y el panel del
     * componente activo (el pedido si es suyo; si no, el primero).
     *
     * @return array{componentes: list<array{id_componente:int, tipo:string, no_leidos:int}>, componente_activo: ?int, panel: ?array<string,mixed>}
     */
    public function bandeja(Curso $curso, int $idUsuario, ?int $idComponentePedido): array
    {
        $componentes = $this->componentesDelCurso($curso, $idUsuario);

        $activo = $componentes->contains(fn($c) => (int) $c->id_componente === $idComponentePedido)
            ? $idComponentePedido
            : ($componentes->isNotEmpty() ? (int) $componentes->first()->id_componente : null);

        // Primero el panel (abrirlo marca como leído) y después los contadores,
        // para que la pestaña recién abierta no siga mostrando su badge.
        $panel = $this->panel($componentes, $activo, $idUsuario);

        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
            esStaff: false,
        );

        return [
            'componentes'       => $componentes->map(fn($c) => [
                'id_componente' => (int) $c->id_componente,
                'tipo'          => $c->tipo_componente,
                'no_leidos'     => $noLeidos[(int) $c->id_componente] ?? 0,
            ])->values()->all(),
            'componente_activo' => $activo,
            'panel'             => $panel,
        ];
    }

    /**
     * Panel del componente activo: avisos, su conversación y a qué docentes
     * puede dirigirse. Abrirlo marca como leído lo que muestra.
     *
     * @param  Collection<int,object>  $componentes
     * @return array<string,mixed>|null
     */
    private function panel(Collection $componentes, ?int $idComponente, int $idUsuario): ?array
    {
        $componente = $idComponente
            ? $componentes->firstWhere('id_componente', $idComponente)
            : null;

        if (!$componente) {
            return null;
        }

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
     * Consolida la mensajería completa de todos los componentes del curso para la app móvil.
     *
     * @return array<string, mixed>
     */
    public function consolidarMensajeria(Curso $curso, int $idUsuario, bool $marcarLeidos = true): array
    {
        $componentes = $this->componentesDelCurso($curso, $idUsuario);

        $cursoData = [
            'id_curso'       => (int) $curso->id_curso,
            'nombre'         => $curso->nombre,
            'cod_curso'      => $curso->cod_curso,
            'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? '',
        ];

        if ($componentes->isEmpty()) {
            return [
                'curso'       => $cursoData,
                'componentes' => [],
            ];
        }

        $componenteIds = $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all();

        // Contadores de no leídos previos al marcado
        $noLeidos = $this->mensajeria->noLeidosPorComponente($componenteIds, $idUsuario, esStaff: false);

        $todosMensajesParaMarcar = [];
        $componentesData = [];

        foreach ($componentes as $c) {
            $idComponente = (int) $c->id_componente;
            $difusiones = $this->mensajeria->difusionesDeComponente($idComponente, $idUsuario);
            $mensajes = $this->mensajeria->mensajesDelCanal($idComponente, $idUsuario, $idUsuario);
            $docentes = $this->mensajeria->docentesDeComponente($idComponente);

            if ($marcarLeidos) {
                $todosMensajesParaMarcar = array_merge(
                    $todosMensajesParaMarcar,
                    $difusiones->pluck('id_mensaje')->all(),
                    $mensajes->pluck('id_mensaje')->all()
                );
            }

            $componentesData[] = [
                'id_componente' => $idComponente,
                'tipo'          => $c->tipo_componente,
                'no_leidos'     => $noLeidos[$idComponente] ?? 0,
                'docentes'      => $docentes->values()->all(),
                'difusiones'    => $difusiones->values()->all(),
                'mensajes'      => $mensajes->values()->all(),
            ];
        }

        if ($marcarLeidos && !empty($todosMensajesParaMarcar)) {
            $this->mensajeria->marcarLeidos(array_unique($todosMensajesParaMarcar), $idUsuario);
        }

        return [
            'curso'       => $cursoData,
            'componentes' => $componentesData,
        ];
    }

    /**
     * Envía un mensaje del estudiante al equipo docente de un componente.
     */
    public function enviarMensajeEstudiante(
        Curso $curso,
        int $idComponente,
        int $idUsuario,
        string $mensaje,
        ?string $tema = null,
        ?int $idReceptor = null,
    ): Mensaje {
        $componentes = $this->componentesDelCurso($curso, $idUsuario);
        $componente = $componentes->firstWhere('id_componente', $idComponente);

        if (!$componente) {
            abort(403, 'No estás inscrito en este componente.');
        }

        $docentes = $this->mensajeria->docentesDeComponente($idComponente);

        if ($docentes->isEmpty()) {
            abort(422, 'Este componente todavía no tiene docentes asignados.');
        }

        $receptor = $docentes->firstWhere('id_usuario', $idReceptor)
            ?? $docentes->firstWhere('es_titular', true)
            ?? $docentes->first();

        $idMensaje = $this->mensajeria->enviarIndividual(
            idComponente: $idComponente,
            idUsuarioEmisor: $idUsuario,
            idUsuarioReceptor: (int) $receptor['id_usuario'],
            idUsuarioAlumno: $idUsuario,
            mensaje: $mensaje,
            tema: $tema,
        );

        return Mensaje::with(['emisor', 'receptor', 'componente'])->findOrFail($idMensaje);
    }
}
