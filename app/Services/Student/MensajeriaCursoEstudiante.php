<?php

namespace App\Services\Student;

use App\Models\Curso\Curso;
use App\Services\MensajeriaService;
use Illuminate\Support\Collection;

/**
 * Mensajería del alumno en un curso — hilos sostenidos por el componente.
 *
 * Es la pestaña «Mensajería» de la ficha del curso, así que el curso ya está
 * elegido y aquí sólo se navegan sus componentes (Cátedra, Laboratorio…). En
 * cada uno el alumno tiene:
 *  - los avisos del equipo docente (difusiones), y
 *  - una conversación privada con cada persona del equipo: titular, docentes
 *    y ayudantes (OBS-49). Cada una la ven sólo él y esa persona.
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
     * Mensajes del curso que el alumno todavía no abre, sumando sus
     * componentes. Es el contador de la pestaña; a diferencia de bandeja(),
     * no marca nada como leído.
     */
    public function noLeidos(Curso $curso, int $idUsuario): int
    {
        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $this->componentesDelCurso($curso, $idUsuario)
                ->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
        );

        return (int) array_sum($noLeidos);
    }

    /**
     * Bandeja del curso: sus componentes con badges de no leídos y el panel del
     * componente activo (el pedido si es suyo; si no, el primero), con una
     * conversación abierta.
     *
     * @return array{componentes: list<array{id_componente:int, tipo:string, no_leidos:int}>, componente_activo: ?int, panel: ?array<string,mixed>}
     */
    public function bandeja(Curso $curso, int $idUsuario, ?int $idComponentePedido, ?int $idInterlocutorPedido = null): array
    {
        $componentes = $this->componentesDelCurso($curso, $idUsuario);

        $activo = $componentes->contains(fn($c) => (int) $c->id_componente === $idComponentePedido)
            ? $idComponentePedido
            : ($componentes->isNotEmpty() ? (int) $componentes->first()->id_componente : null);

        // Primero el panel (abrirlo marca como leído) y después los contadores,
        // para que la pestaña recién abierta no siga mostrando su badge.
        $panel = $this->panel($componentes, $activo, $idUsuario, $idInterlocutorPedido);

        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $componentes->pluck('id_componente')->map(fn($id) => (int) $id)->all(),
            $idUsuario,
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
     * Panel del componente activo: avisos, una conversación por persona del
     * equipo y la abierta. Abrirlo marca como leídos los avisos y la
     * conversación abierta; las demás conservan sus no leídos.
     *
     * Se abre la pedida; si no, la que tiene lo último sin leer; si no, la
     * última con mensajes; si no, la primera del equipo (el titular).
     *
     * @param  Collection<int,object>  $componentes
     * @return array<string,mixed>|null
     */
    private function panel(Collection $componentes, ?int $idComponente, int $idUsuario, ?int $idInterlocutorPedido): ?array
    {
        $componente = $idComponente
            ? $componentes->firstWhere('id_componente', $idComponente)
            : null;

        if (!$componente) {
            return null;
        }

        $conversaciones = $this->conversaciones($idComponente, $idUsuario);

        $abierta = $conversaciones->firstWhere('id_usuario', $idInterlocutorPedido)
            ?? $conversaciones->where('no_leidos', '>', 0)->sortByDesc('ultima_fecha')->first()
            ?? $conversaciones->whereNotNull('ultima_fecha')->sortByDesc('ultima_fecha')->first()
            ?? $conversaciones->first();

        $difusiones = $this->mensajeria->difusionesDeComponente($idComponente, $idUsuario);
        $mensajes = $abierta
            ? $this->mensajeria->mensajesDeConversacion($idComponente, $idUsuario, $abierta['id_usuario'], $idUsuario)
            : collect();

        // Lo que llega sin abrir se destaca como «Nuevo» en esta visita.
        $ids = $difusiones->pluck('id_mensaje')->merge($mensajes->pluck('id_mensaje'))->all();
        $nuevos = $this->mensajeria->sinLeer($ids, $idUsuario);
        $this->mensajeria->marcarLeidos($ids, $idUsuario);

        $conNuevo = fn(array $m) => $m + ['nuevo' => isset($nuevos[$m['id_mensaje']])];

        return [
            'id_componente'  => $idComponente,
            'componente'     => $componente->tipo_componente,
            'difusiones'     => $difusiones->map($conNuevo)->values(),
            'conversaciones' => $conversaciones,
            'conversacion'   => $abierta ? [
                'id_usuario' => $abierta['id_usuario'],
                'mensajes'   => $mensajes->map($conNuevo)->values(),
            ] : null,
        ];
    }

    /**
     * Una conversación por persona del equipo (a quien puede escribirle) más
     * las de quienes ya no están en el equipo pero tienen mensajes con él, en
     * sólo lectura. `no_leidos` se cuenta antes de abrir el panel.
     *
     * @return Collection<int,array<string,mixed>>
     */
    private function conversaciones(int $idComponente, int $idUsuario): Collection
    {
        $resumen = $this->mensajeria->conversacionesDeAlumno($idComponente, $idUsuario);
        $equipo = $this->mensajeria->equipoDeComponente($idComponente)
            ->reject(fn(array $p) => $p['id_usuario'] === $idUsuario)
            ->map(fn(array $p) => $p + ['en_equipo' => true]);

        $exEquipo = $resumen->except($equipo->pluck('id_usuario')->all())
            ->map(fn(array $r, int $id) => [
                'id_usuario' => $id,
                'nombre'     => $r['nombre'],
                'rol'        => null,
                'en_equipo'  => false,
            ]);

        return $equipo->concat($exEquipo->values())
            ->map(fn(array $c) => $c + [
                'ultimo_mensaje' => $resumen[$c['id_usuario']]['ultimo_mensaje'] ?? null,
                'ultima_fecha'   => $resumen[$c['id_usuario']]['ultima_fecha'] ?? null,
                'ultimo_es_mio'  => $resumen[$c['id_usuario']]['ultimo_es_mio'] ?? false,
                'no_leidos'      => $resumen[$c['id_usuario']]['no_leidos'] ?? 0,
            ])
            ->values();
    }
}
