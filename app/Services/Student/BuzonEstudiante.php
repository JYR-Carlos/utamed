<?php

namespace App\Services\Student;

use App\Enums\DB\TipoMensaje;
use App\Services\MensajeriaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buzón del alumno (OBS-48, OBS-54): todo lo que le escribieron, de todos sus
 * ramos, en una sola lista, para llegar a cada conversación en un clic.
 *
 * Reúne dos mensajerías que siguen siendo distintas, y cada fila dice de cuál
 * viene:
 *
 *  - Mensajería del ramo (curso.mensaje), de tema libre: una fila por
 *    conversación privada con alguien del equipo (OBS-49) y otra por los
 *    avisos de cada componente.
 *  - Agenda de cada actividad (agenda.agenda): consultas, feedback,
 *    evaluaciones y apelaciones de esa actividad. Una fila por actividad.
 *
 * Es sólo un índice: cada fila abre la conversación en su lugar (la pestaña
 * Mensajería del ramo o la pestaña Consultas de la actividad), que es donde
 * se lee, se responde y se marca como leída. Abrir el buzón no marca nada.
 */
class BuzonEstudiante
{
    /**
     * Tipos de agenda que el buzón muestra: lo que se conversa en la
     * actividad. Las entregas, sus cancelaciones y el cierre son registros,
     * no mensajes (siguen en Consultas y en el Historial del ramo).
     */
    private const TIPOS_AGENDA = [
        TipoMensaje::MENSAJE_AL_PROFESOR,
        TipoMensaje::FEEDBACK,
        TipoMensaje::EVALUACIÓN,
        TipoMensaje::SOLICITUD_DE_APELACIÓN,
        TipoMensaje::RESOLUCIÓN_DE_APELACIÓN,
    ];

    public function __construct(private readonly MensajeriaService $mensajeria)
    {
    }

    /**
     * Las filas del buzón, primero las que tienen algo sin leer y después por
     * el mensaje más reciente.
     *
     * @return list<array<string,mixed>>
     */
    public function hilos(int $idUsuario): array
    {
        $componentes = $this->componentes($idUsuario)->keyBy(fn($c) => (int) $c->id_componente);
        $ids = $componentes->keys()->all();

        $curso = fn(object $c) => [
            'id_curso' => (int) $c->id_curso,
            'nombre'   => $c->curso_nombre,
            'codigo'   => $c->cod_asignatura ?: $c->cod_curso,
        ];
        $hrefMensajeria = fn(object $c, string $extra) =>
            "/estudiante/cursos/{$c->id_curso}?seccion=mensajeria&componente_id={$c->id_componente}{$extra}";

        $conversaciones = $this->mensajeria->conversacionesEn($ids, $idUsuario);
        $roles = $this->rolesDelEquipo($conversaciones);

        $conversaciones = $conversaciones
            ->map(function (array $cv) use ($componentes, $curso, $hrefMensajeria, $roles) {
                $c = $componentes[$cv['id_componente']];

                return [
                    'clave'      => "m-{$cv['id_componente']}-{$cv['id_usuario']}",
                    'origen'     => 'mensaje',
                    'curso'      => $curso($c),
                    'componente' => $c->tipo_componente,
                    'titulo'     => $cv['nombre'],
                    'detalle'    => $roles[$cv['id_componente']][$cv['id_usuario']] ?? null,
                    'tipo'       => null,
                    'tema'       => $cv['tema'],
                    'texto'      => $cv['ultimo_mensaje'],
                    'emisor'     => $cv['ultimo_es_mio'] ? null : $cv['nombre'],
                    'es_mio'     => $cv['ultimo_es_mio'],
                    'fecha'      => (string) $cv['ultima_fecha'],
                    'no_leidos'  => $cv['no_leidos'],
                    'href'       => $hrefMensajeria($c, "&interlocutor_id={$cv['id_usuario']}"),
                ];
            });

        $avisos = $this->mensajeria->avisosEn($ids, $idUsuario)
            ->map(function (array $av) use ($componentes, $curso, $hrefMensajeria) {
                $c = $componentes[$av['id_componente']];

                return [
                    'clave'      => "a-{$av['id_componente']}",
                    'origen'     => 'aviso',
                    'curso'      => $curso($c),
                    'componente' => $c->tipo_componente,
                    'titulo'     => 'Avisos del curso',
                    'detalle'    => null,
                    'tipo'       => null,
                    'tema'       => $av['tema'],
                    'texto'      => $av['mensaje'],
                    'emisor'     => $av['emisor'],
                    'es_mio'     => false,
                    'fecha'      => (string) $av['fecha'],
                    'no_leidos'  => $av['no_leidos'],
                    'href'       => $hrefMensajeria($c, '&vista=avisos'),
                ];
            });

        $actividades = $this->hilosDeAgenda($ids, $idUsuario)
            ->map(function (object $h) use ($componentes, $curso, $idUsuario) {
                $c = $componentes[(int) $h->id_componente];
                $esMio = (int) $h->id_usuario_emisor === $idUsuario;

                return [
                    'clave'      => "g-{$h->grupo}",
                    'origen'     => 'actividad',
                    'curso'      => $curso($c),
                    'componente' => $c->tipo_componente,
                    'titulo'     => $h->actividad,
                    'detalle'    => null,
                    'tipo'       => $h->tipo_mensaje,
                    'tema'       => null,
                    'texto'      => $h->mensaje,
                    'emisor'     => $esMio ? null : $h->emisor,
                    'es_mio'     => $esMio,
                    'fecha'      => (string) $h->fecha_envio,
                    'no_leidos'  => (int) $h->no_leidos,
                    'href'       => "/estudiante/cursos/{$c->id_curso}/actividad/{$h->id_actividad}?seccion=agenda",
                ];
            });

        return $conversaciones->concat($avisos)->concat($actividades)
            ->sort(fn(array $a, array $b) => [$b['no_leidos'] > 0, $b['fecha']] <=> [$a['no_leidos'] > 0, $a['fecha']])
            ->values()
            ->all();
    }

    /**
     * Lo que el alumno tiene sin leer en todo el buzón: la cifra del menú
     * lateral. Cuenta lo mismo que suman las filas de {@see hilos()}.
     */
    public function noLeidos(int $idUsuario): int
    {
        $ids = $this->componentes($idUsuario)->pluck('id_componente')->map(fn($id) => (int) $id)->all();

        if (empty($ids)) {
            return 0;
        }

        $deMensajeria = array_sum($this->mensajeria->noLeidosPorComponente($ids, $idUsuario));

        $deAgenda = (int) DB::selectOne("
            SELECT COUNT(*) AS n
            FROM ({$this->sqlGruposDelAlumno()}) g
            JOIN agenda.agenda a ON a.id_actividad_asignada_grupo = g.grupo
            WHERE a.tipo_mensaje::text = ANY(string_to_array(:tipos, '|'))
              AND a.id_usuario_emisor <> :id_emisor
              AND NOT EXISTS (
                    SELECT 1 FROM agenda.lectura_agenda l
                    WHERE l.id_agenda = a.id_agenda AND l.id_usuario_lector = :id_lector
              )
        ", $this->bindingsGrupos($ids, $idUsuario) + [
            'tipos'     => $this->tiposAgenda(),
            'id_emisor' => $idUsuario,
            'id_lector' => $idUsuario,
        ])->n;

        return $deMensajeria + $deAgenda;
    }

    /**
     * Los componentes del alumno en cursos donde sigue inscrito: los únicos
     * cuya ficha puede abrir (CourseController exige `INSCRITO`), así que
     * ninguna fila del buzón lleva a un 403.
     *
     * @return Collection<int,object>
     */
    private function componentes(int $idUsuario): Collection
    {
        $inscritos = DB::table('curso.inscripcion_curso as ic')
            ->join('usuario.estudiante as est', 'est.id_estudiante', '=', 'ic.id_estudiante')
            ->where('est.id_usuario', $idUsuario)
            ->where('ic.estado_inscripcion', 'INSCRITO')
            ->pluck('ic.id_curso')
            ->map(fn($id) => (int) $id)
            ->all();

        return $this->mensajeria->componentesDeEstudiante($idUsuario)
            ->filter(fn($c) => in_array((int) $c->id_curso, $inscritos, true))
            ->values();
    }

    /**
     * Una fila por actividad donde el alumno tiene algo conversado: el último
     * mensaje (de los tipos del buzón) y cuántos de esos le faltan por leer.
     *
     * @param  int[]  $componenteIds
     * @return Collection<int,object>
     */
    private function hilosDeAgenda(array $componenteIds, int $idUsuario): Collection
    {
        if (empty($componenteIds)) {
            return collect();
        }

        return collect(DB::select("
            SELECT g.grupo, g.id_actividad, g.actividad, g.id_componente,
                   ult.tipo_mensaje, ult.mensaje, ult.fecha_envio, ult.id_usuario_emisor, ult.emisor,
                   nl.no_leidos
            FROM ({$this->sqlGruposDelAlumno()}) g
            JOIN LATERAL (
                SELECT a.tipo_mensaje::text AS tipo_mensaje, a.mensaje, a.fecha_envio, a.id_usuario_emisor,
                       TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS emisor
                FROM agenda.agenda a
                JOIN usuario.usuario u ON u.id_usuario = a.id_usuario_emisor
                WHERE a.id_actividad_asignada_grupo = g.grupo
                  AND a.tipo_mensaje::text = ANY(string_to_array(:tipos, '|'))
                ORDER BY a.fecha_envio DESC, a.id_agenda DESC
                LIMIT 1
            ) ult ON TRUE
            JOIN LATERAL (
                SELECT COUNT(*) AS no_leidos
                FROM agenda.agenda a
                WHERE a.id_actividad_asignada_grupo = g.grupo
                  AND a.tipo_mensaje::text = ANY(string_to_array(:tipos2, '|'))
                  AND a.id_usuario_emisor <> :id_emisor
                  AND NOT EXISTS (
                        SELECT 1 FROM agenda.lectura_agenda l
                        WHERE l.id_agenda = a.id_agenda AND l.id_usuario_lector = :id_lector
                  )
            ) nl ON TRUE
        ", $this->bindingsGrupos($componenteIds, $idUsuario) + [
            'tipos'     => $this->tiposAgenda(),
            'tipos2'    => $this->tiposAgenda(),
            'id_emisor' => $idUsuario,
            'id_lector' => $idUsuario,
        ]));
    }

    /**
     * Los grupos de actividad del alumno que puede abrir: actividad visible
     * (no plantilla) de uno de los componentes de {@see componentes()}. Mismo
     * criterio que la página de la actividad.
     */
    private function sqlGruposDelAlumno(): string
    {
        return "
            SELECT ig.id_actividad_asignada_grupo AS grupo,
                   act.id_actividad, act.nombre AS actividad, act.id_componente
            FROM agenda.integrante_grupo ig
            JOIN usuario.estudiante est ON est.id_estudiante = ig.id_estudiante
            JOIN agenda.actividad_asignada_grupo aag ON aag.id_actividad_asignada_grupo = ig.id_actividad_asignada_grupo
            JOIN agenda.actividad act ON act.id_actividad = aag.id_actividad
            WHERE est.id_usuario = :id_alumno
              AND act.visible
              AND NOT act.es_plantilla
              AND act.id_componente = ANY(string_to_array(:ids, ',')::int[])
        ";
    }

    /** @param int[] $componenteIds */
    private function bindingsGrupos(array $componenteIds, int $idUsuario): array
    {
        return ['id_alumno' => $idUsuario, 'ids' => implode(',', array_map('intval', $componenteIds))];
    }

    private function tiposAgenda(): string
    {
        return implode('|', array_map(fn(TipoMensaje $t) => $t->value, self::TIPOS_AGENDA));
    }

    /**
     * Papel de cada interlocutor en su componente (Titular, Docente o
     * Ayudante), sólo de los componentes donde el alumno tiene conversaciones.
     * Quien ya no está en el equipo queda sin papel.
     *
     * @return array<int,array<int,string>>  [id_componente => [id_usuario => rol]]
     */
    private function rolesDelEquipo(Collection $conversaciones): array
    {
        return $conversaciones->pluck('id_componente')->unique()
            ->mapWithKeys(fn(int $id) => [$id => $this->mensajeria->equipoDeComponente($id)
                ->pluck('rol', 'id_usuario')->all()])
            ->all();
    }
}
