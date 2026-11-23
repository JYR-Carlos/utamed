<?php

namespace App\Services\Docente;

use App\Enums\DB\TipoMensaje;
use App\Services\MensajeriaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buzón del docente: lo mismo que el buzón del alumno (OBS-48), visto desde
 * el otro lado. Todo lo que le escribieron, de todos sus cursos, en una sola
 * lista, para llegar a cada conversación en un clic.
 *
 * Reúne las dos mensajerías, que siguen siendo distintas, y cada fila dice de
 * cuál viene:
 *
 *  - Mensajería del curso (curso.mensaje), de tema libre: una fila por
 *    conversación privada con cada alumno (OBS-49: sólo las suyas, nunca las
 *    de un colegiado o un ayudante) y otra por los avisos de cada componente.
 *  - Agenda de cada actividad (agenda.agenda): consultas, feedback,
 *    evaluaciones y apelaciones. Una fila por actividad, que abre la agenda
 *    del grupo con lo más reciente. Sólo en los cursos donde es titular, como
 *    la bandeja de actividades que reemplaza.
 *
 * Abarca el semestre vigente (el más reciente con cursos, el mismo del menú);
 * de los anteriores sólo entra lo que tiene algo sin leer, para que nada
 * quede oculto.
 *
 * Es sólo un índice: cada fila abre la conversación en su lugar (la
 * mensajería del curso o la agenda de la actividad), que es donde se lee, se
 * responde y se marca como leída. Abrir el buzón no marca nada.
 */
class BuzonDocente
{
    /** Los mismos tipos del buzón del alumno: lo que se conversa en la actividad. */
    private const TIPOS_AGENDA = [
        TipoMensaje::MENSAJE_AL_PROFESOR,
        TipoMensaje::FEEDBACK,
        TipoMensaje::EVALUACIÓN,
        TipoMensaje::SOLICITUD_DE_APELACIÓN,
        TipoMensaje::RESOLUCIÓN_DE_APELACIÓN,
    ];

    /** Lo que escribe el alumno: si es lo último del grupo, espera respuesta. */
    private const TIPOS_DEL_ALUMNO = [
        TipoMensaje::MENSAJE_AL_PROFESOR,
        TipoMensaje::SOLICITUD_DE_APELACIÓN,
    ];

    public function __construct(private readonly MensajeriaService $mensajeria)
    {
    }

    /**
     * La página del buzón: el semestre vigente, las filas (primero las que
     * tienen algo sin leer, después por el mensaje más reciente) y los cursos
     * vigentes con sus no leídos, para escribir un mensaje nuevo.
     *
     * @return array{periodo: ?array{sem:int, ano:int}, hilos: list<array<string,mixed>>, cursos: list<array<string,mixed>>}
     */
    public function bandeja(int $idUsuario, int $idDocente): array
    {
        $componentes = $this->componentes($idDocente)->keyBy(fn ($c) => (int) $c->id_componente);
        $ids = $componentes->keys()->all();
        $vigente = $this->periodoVigente($componentes);
        $esVigente = fn (object $c) => $vigente
            && (int) $c->agno_real === $vigente['ano']
            && (int) $c->semestre_real === $vigente['sem'];

        $curso = fn (object $c) => [
            'id_curso'    => (int) $c->id_curso,
            'nombre'      => $c->curso_nombre,
            'codigo'      => $c->cod_asignatura ?: $c->cod_curso,
            'letra_grupo' => $c->letra_grupo,
        ];
        $hrefMensajeria = fn (object $c, string $extra = '') =>
            "/docente/cursos/{$c->id_curso}/mensajeria?componente_id={$c->id_componente}{$extra}";

        $conversaciones = $this->mensajeria->conversacionesEn($ids, $idUsuario)
            ->map(function (array $cv) use ($componentes, $curso, $hrefMensajeria) {
                $c = $componentes[$cv['id_componente']];

                return [
                    'clave'         => "m-{$cv['id_componente']}-{$cv['id_usuario']}",
                    'origen'        => 'mensaje',
                    'id_componente' => $cv['id_componente'],
                    'curso'         => $curso($c),
                    'componente'    => $c->tipo_componente,
                    'titulo'        => $cv['nombre'],
                    'detalle'       => null,
                    'tipo'          => null,
                    'tema'          => $cv['tema'],
                    'texto'         => $cv['ultimo_mensaje'],
                    'emisor'        => $cv['ultimo_es_mio'] ? null : $cv['nombre'],
                    'es_mio'        => $cv['ultimo_es_mio'],
                    'fecha'         => (string) $cv['ultima_fecha'],
                    'no_leidos'     => $cv['no_leidos'],
                    // Lo último lo escribió el alumno.
                    'por_responder' => $cv['ultimo_es_mio'] ? 0 : 1,
                    'href'          => $hrefMensajeria($c, "&alumno_id={$cv['id_usuario']}"),
                ];
            });

        $avisos = $this->mensajeria->avisosEn($ids, $idUsuario)
            ->map(function (array $av) use ($componentes, $curso, $hrefMensajeria, $idUsuario) {
                $c = $componentes[$av['id_componente']];
                $esMio = $av['id_emisor'] === $idUsuario;

                return [
                    'clave'         => "a-{$av['id_componente']}",
                    'origen'        => 'aviso',
                    'id_componente' => $av['id_componente'],
                    'curso'         => $curso($c),
                    'componente'    => $c->tipo_componente,
                    'titulo'        => 'Avisos del curso',
                    'detalle'       => null,
                    'tipo'          => null,
                    'tema'          => $av['tema'],
                    'texto'         => $av['mensaje'],
                    'emisor'        => $esMio ? null : $av['emisor'],
                    'es_mio'        => $esMio,
                    'fecha'         => (string) $av['fecha'],
                    'no_leidos'     => $av['no_leidos'],
                    'por_responder' => 0,
                    'href'          => $hrefMensajeria($c),
                ];
            });

        $titulares = $componentes->filter(fn ($c) => (int) $c->id_docente_titular === $idDocente);

        $actividades = $this->hilosDeAgenda($titulares->keys()->all(), $idUsuario)
            ->groupBy(fn ($g) => (int) $g->id_actividad)
            ->map(function (Collection $grupos) use ($componentes, $curso, $idUsuario) {
                $ultimo = $grupos->sortByDesc('fecha_envio')->first();
                $conNuevos = $grupos->filter(fn ($g) => (int) $g->no_leidos > 0);
                // Abre la agenda del grupo con lo más reciente sin leer; si no hay, la del último mensaje.
                $abrir = $conNuevos->sortByDesc('fecha_envio')->first() ?? $ultimo;
                $c = $componentes[(int) $ultimo->id_componente];
                $esMio = (int) $ultimo->id_usuario_emisor === $idUsuario;
                $deAlumno = array_map(fn (TipoMensaje $t) => $t->value, self::TIPOS_DEL_ALUMNO);

                return [
                    'clave'         => "g-{$ultimo->id_actividad}",
                    'origen'        => 'actividad',
                    'id_componente' => (int) $ultimo->id_componente,
                    'curso'         => $curso($c),
                    'componente'    => $c->tipo_componente,
                    'titulo'        => $ultimo->actividad,
                    'detalle'       => $conNuevos->count() > 1 ? "En {$conNuevos->count()} grupos" : null,
                    'tipo'          => $ultimo->tipo_mensaje,
                    'tema'          => null,
                    'texto'         => $ultimo->mensaje,
                    'emisor'        => $esMio ? null : $ultimo->emisor,
                    'es_mio'        => $esMio,
                    'fecha'         => (string) $ultimo->fecha_envio,
                    'no_leidos'     => (int) $grupos->sum('no_leidos'),
                    'por_responder' => $grupos->filter(fn ($g) => in_array($g->tipo_mensaje, $deAlumno, true))->count(),
                    'href'          => "/docente/cursos/{$c->id_curso}/actividades/{$ultimo->id_actividad}/evaluacion?grupo={$abrir->grupo}",
                ];
            })
            ->values();

        $hilos = $conversaciones->concat($avisos)->concat($actividades)
            ->filter(fn (array $h) => $h['no_leidos'] > 0 || $esVigente($componentes[$h['id_componente']]))
            ->sort(fn (array $a, array $b) => [$b['no_leidos'] > 0, $b['fecha']] <=> [$a['no_leidos'] > 0, $a['fecha']])
            ->map(fn (array $h) => collect($h)->except('id_componente')->all())
            ->values()
            ->all();

        return [
            'periodo' => $vigente,
            'hilos'   => $hilos,
            'cursos'  => $this->cursosVigentes($componentes->filter($esVigente), $idUsuario),
        ];
    }

    /**
     * Lo que el docente tiene sin leer en todo el buzón: la cifra del menú
     * lateral. Cuenta lo mismo que suman las filas de {@see bandeja()}.
     */
    public function noLeidos(int $idUsuario, int $idDocente): int
    {
        $componentes = $this->componentes($idDocente);

        if ($componentes->isEmpty()) {
            return 0;
        }

        $ids = $componentes->pluck('id_componente')->map(fn ($id) => (int) $id)->all();
        $deMensajeria = array_sum($this->mensajeria->noLeidosPorComponente($ids, $idUsuario));

        $titulares = $componentes->filter(fn ($c) => (int) $c->id_docente_titular === $idDocente)
            ->pluck('id_componente')->map(fn ($id) => (int) $id)->all();

        if (empty($titulares)) {
            return $deMensajeria;
        }

        $deAgenda = (int) DB::selectOne("
            SELECT COUNT(*) AS n
            FROM ({$this->sqlGrupos()}) g
            JOIN agenda.agenda a ON a.id_actividad_asignada_grupo = g.grupo
            WHERE a.tipo_mensaje::text = ANY(string_to_array(:tipos, '|'))
              AND a.id_usuario_emisor <> :id_emisor
              AND NOT EXISTS (
                    SELECT 1 FROM agenda.lectura_agenda l
                    WHERE l.id_agenda = a.id_agenda AND l.id_usuario_lector = :id_lector
              )
        ", [
            'ids'       => implode(',', $titulares),
            'tipos'     => $this->tiposAgenda(),
            'id_emisor' => $idUsuario,
            'id_lector' => $idUsuario,
        ])->n;

        return $deMensajeria + $deAgenda;
    }

    /**
     * Componentes donde el docente participa (titular o asignado, colegiados
     * incluidos), sin los cursos plantilla.
     *
     * @return Collection<int,object>
     */
    private function componentes(int $idDocente): Collection
    {
        $componentes = $this->mensajeria->componentesDeDocente($idDocente);

        $plantillas = DB::table('curso.curso')
            ->whereIn('id_curso', $componentes->pluck('id_curso')->unique()->all())
            ->where('es_plantilla', true)
            ->pluck('id_curso')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $componentes->reject(fn ($c) => in_array((int) $c->id_curso, $plantillas, true))->values();
    }

    /**
     * El semestre más reciente con cursos: el mismo que el menú y el Inicio
     * llaman vigente.
     *
     * @param  Collection<int,object>  $componentes
     * @return array{sem:int, ano:int}|null
     */
    private function periodoVigente(Collection $componentes): ?array
    {
        $ultimo = $componentes
            ->filter(fn ($c) => $c->agno_real !== null && $c->semestre_real !== null)
            ->sortByDesc(fn ($c) => (int) $c->agno_real * 10 + (int) $c->semestre_real)
            ->first();

        return $ultimo ? ['sem' => (int) $ultimo->semestre_real, 'ano' => (int) $ultimo->agno_real] : null;
    }

    /**
     * Los cursos vigentes con sus mensajes sin leer de la mensajería del curso:
     * desde ahí se escribe un mensaje nuevo (lo que antes era «Mensajes para»).
     *
     * @param  Collection<int,object>  $vigentes
     * @return list<array<string,mixed>>
     */
    private function cursosVigentes(Collection $vigentes, int $idUsuario): array
    {
        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $vigentes->pluck('id_componente')->map(fn ($id) => (int) $id)->values()->all(),
            $idUsuario,
        );

        return $vigentes
            ->groupBy('id_curso')
            ->map(function (Collection $delCurso) use ($noLeidos) {
                $c = $delCurso->first();

                return [
                    'id_curso'    => (int) $c->id_curso,
                    'nombre'      => $c->curso_nombre,
                    'codigo'      => $c->cod_asignatura ?: $c->cod_curso,
                    'letra_grupo' => $c->letra_grupo,
                    'no_leidos'   => $delCurso->sum(fn ($cmp) => $noLeidos[(int) $cmp->id_componente] ?? 0),
                ];
            })
            ->sortBy(fn ($c) => $c['codigo'] . ' ' . $c['letra_grupo'])
            ->values()
            ->all();
    }

    /**
     * Una fila por grupo de actividad con algo conversado: el último mensaje
     * (de los tipos del buzón) y cuántos de esos le faltan por leer.
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
            FROM ({$this->sqlGrupos()}) g
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
        ", [
            'ids'       => implode(',', array_map('intval', $componenteIds)),
            'tipos'     => $this->tiposAgenda(),
            'tipos2'    => $this->tiposAgenda(),
            'id_emisor' => $idUsuario,
            'id_lector' => $idUsuario,
        ]));
    }

    /** Los grupos de las actividades (no plantilla) de los componentes dados. */
    private function sqlGrupos(): string
    {
        return "
            SELECT aag.id_actividad_asignada_grupo AS grupo,
                   act.id_actividad, act.nombre AS actividad, act.id_componente
            FROM agenda.actividad act
            JOIN agenda.actividad_asignada_grupo aag ON aag.id_actividad = act.id_actividad
            WHERE NOT act.es_plantilla
              AND act.id_componente = ANY(string_to_array(:ids, ',')::int[])
        ";
    }

    private function tiposAgenda(): string
    {
        return implode('|', array_map(fn (TipoMensaje $t) => $t->value, self::TIPOS_AGENDA));
    }
}
