<?php

namespace App\Services;

use App\Enums\DB\TipoMensajeCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mensajería del curso — lógica compartida por docentes, ayudantes y estudiantes.
 *
 * MODELO (sin cambios de esquema: usa curso.mensaje e curso.interaccion_mensaje
 * tal como existen).
 *
 * El hilo se sostiene en el COMPONENTE. Hay dos formas:
 *
 *  - Difusión  (tipo_mensaje = MENSAJE_PARA_TODO_EL_CURSO, receptor NULL)
 *    La ven todos los inscritos del componente y su equipo docente.
 *
 *  - Canal individual (tipo_mensaje = MENSAJE_INDIVIDUAL)
 *    El canal es el par (componente, alumno). Todos los docentes del componente
 *    comparten ese canal: si responde un colegiado, el mensaje entra en la misma
 *    conversación, porque la identidad del canal no depende de quién contestó.
 *    id_usuario_receptor sólo registra a quién iba dirigido ese mensaje puntual.
 *
 * QUIÉN ES EL ALUMNO DEL CANAL: se resuelve por la inscripción a ESE componente
 * (curso.inscripcion_componente), no por el rol global. Así un ayudante que
 * además sea alumno avanzado no puede confundirse con el alumno del canal.
 *
 * LECTURA: curso.interaccion_mensaje guarda una fila por (mensaje, lector). Sin
 * fila = no leído.
 */
class MensajeriaService
{
    /** Asunto por defecto cuando un canal aún no tiene uno. */
    private const TEMA_POR_DEFECTO = 'Consulta';

    // ─────────────────────────────────────────────────────────────────────────
    // Componentes visibles según el rol
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Componentes donde el docente participa: los que tiene asignados en
     * curso.docente_componente (colegiados incluidos, porque la tabla admite
     * varias filas por componente) más todos los de los cursos donde es titular.
     *
     * @return Collection<int,object>
     */
    public function componentesDeDocente(int $idDocente): Collection
    {
        return collect(DB::select(
            $this->sqlComponentes(
                'LEFT JOIN curso.docente_componente dc ON dc.id_componente = cmp.id_componente',
                '(dc.id_docente = :id_docente OR cur.id_docente_titular = :id_docente2)'
            ),
            ['id_docente' => $idDocente, 'id_docente2' => $idDocente]
        ));
    }

    /**
     * Componentes de los cursos donde el usuario tiene el rol "Ayudante" sobre
     * el contexto del curso (mismo criterio que UserCoursesService).
     *
     * @return Collection<int,object>
     */
    public function componentesDeAyudante(int $idUsuario): Collection
    {
        return collect(DB::select(
            $this->sqlComponentes(
                'JOIN usuario.usuario_rol_asignacion ura ON ura.id_contexto = cur.id_contexto
                 JOIN usuario.rol r ON r.id_rol = ura.id_rol',
                "ura.id_usuario = :id_usuario
                 AND ura.esta_activo = TRUE
                 AND ura.fue_eliminado = FALSE
                 AND LOWER(r.nombre) = 'ayudante'"
            ),
            ['id_usuario' => $idUsuario]
        ));
    }

    /**
     * Componentes donde el estudiante está inscrito.
     *
     * @return Collection<int,object>
     */
    public function componentesDeEstudiante(int $idUsuario): Collection
    {
        return collect(DB::select(
            $this->sqlComponentes(
                'JOIN curso.inscripcion_componente ic ON ic.id_componente = cmp.id_componente
                 JOIN usuario.estudiante est ON est.id_estudiante = ic.id_estudiante',
                'est.id_usuario = :id_usuario'
            ),
            ['id_usuario' => $idUsuario]
        ));
    }

    /**
     * Esqueleto común del listado de componentes. $join y $where cambian según
     * el rol; el SELECT y el orden son iguales para todos.
     */
    private function sqlComponentes(string $join, string $where): string
    {
        return "
            SELECT DISTINCT
                cmp.id_componente,
                cmp.id_curso,
                tc.tipo                AS tipo_componente,
                cur.nombre             AS curso_nombre,
                cur.cod_curso,
                cur.agno_real,
                cur.semestre_real,
                cur.letra_grupo,
                cur.id_docente_titular
            FROM curso.componente cmp
            JOIN curso.curso cur           ON cur.id_curso = cmp.id_curso
            JOIN curso.tipo_componente tc  ON tc.id_tipo_componente = cmp.id_tipo_componente
            {$join}
            WHERE cur.fecha_eliminacion IS NULL
              AND ({$where})
            ORDER BY cur.agno_real DESC, cur.semestre_real DESC, cur.nombre, tc.tipo
        ";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bandeja
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * No leídos por componente para un usuario: cuenta difusiones y mensajes
     * individuales que lo alcanzan y que aún no tienen acuse de lectura suyo.
     *
     * @param  int[]  $componenteIds
     * @param  bool   $esStaff  TRUE = ve todos los canales del componente;
     *                          FALSE = sólo su propio canal.
     * @return array<int,int>  [id_componente => no leídos]
     */
    public function noLeidosPorComponente(array $componenteIds, int $idUsuario, bool $esStaff): array
    {
        if (empty($componenteIds)) {
            return [];
        }

        // El staff ve todo el componente; el alumno sólo lo suyo (difusiones +
        // los individuales donde él aparece).
        $alcance = $esStaff
            ? '1 = 1'
            : "(m.tipo_mensaje = 'MENSAJE_PARA_TODO_EL_CURSO'
                OR :id_alcance IN (m.id_usuario_emisor, m.id_usuario_receptor))";

        $bindings = [
            'ids'      => $this->pgIntArray($componenteIds),
            'id_lector' => $idUsuario,
            'id_emisor' => $idUsuario,
        ];

        if (!$esStaff) {
            $bindings['id_alcance'] = $idUsuario;
        }

        $filas = DB::select("
            SELECT m.id_componente, COUNT(*) AS no_leidos
            FROM curso.mensaje m
            WHERE m.id_componente = ANY(string_to_array(:ids, ',')::int[])
              AND m.fecha_eliminacion IS NULL
              AND m.id_usuario_emisor <> :id_emisor
              AND ({$alcance})
              AND NOT EXISTS (
                    SELECT 1 FROM curso.interaccion_mensaje im
                    WHERE im.id_mensaje = m.id_mensaje
                      AND im.id_usuario_lector = :id_lector
                      AND im.fecha_eliminacion IS NULL
              )
            GROUP BY m.id_componente
        ", $bindings);

        return collect($filas)->pluck('no_leidos', 'id_componente')
            ->map(fn($n) => (int) $n)
            ->all();
    }

    /**
     * Canales de un componente para la vista del staff: un canal por alumno
     * inscrito (existan o no mensajes, para poder iniciar la conversación), con
     * el último mensaje y los no leídos del propio staff.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function canalesDeComponente(int $idComponente, int $idUsuarioActor): Collection
    {
        $filas = DB::select("
            SELECT
                u.id_usuario AS id_alumno,
                TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''), ' ', COALESCE(u.apellido2, ''))) AS alumno,
                u.rut,
                ult.mensaje       AS ultimo_mensaje,
                ult.fecha_creacion AS ultima_fecha,
                ult.id_usuario_emisor AS ultimo_emisor,
                COALESCE(nl.no_leidos, 0) AS no_leidos
            FROM curso.inscripcion_componente ic
            JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
            JOIN usuario.usuario u    ON u.id_usuario = e.id_usuario
            LEFT JOIN LATERAL (
                SELECT m.mensaje, m.fecha_creacion, m.id_usuario_emisor
                FROM curso.mensaje m
                WHERE m.id_componente = ic.id_componente
                  AND m.tipo_mensaje = 'MENSAJE_INDIVIDUAL'
                  AND m.fecha_eliminacion IS NULL
                  AND u.id_usuario IN (m.id_usuario_emisor, m.id_usuario_receptor)
                ORDER BY m.fecha_creacion DESC, m.id_mensaje DESC
                LIMIT 1
            ) ult ON TRUE
            LEFT JOIN LATERAL (
                SELECT COUNT(*) AS no_leidos
                FROM curso.mensaje m
                WHERE m.id_componente = ic.id_componente
                  AND m.tipo_mensaje = 'MENSAJE_INDIVIDUAL'
                  AND m.fecha_eliminacion IS NULL
                  AND u.id_usuario IN (m.id_usuario_emisor, m.id_usuario_receptor)
                  AND m.id_usuario_emisor <> :id_actor
                  AND NOT EXISTS (
                        SELECT 1 FROM curso.interaccion_mensaje im
                        WHERE im.id_mensaje = m.id_mensaje
                          AND im.id_usuario_lector = :id_actor2
                          AND im.fecha_eliminacion IS NULL
                  )
            ) nl ON TRUE
            WHERE ic.id_componente = :id_componente
            ORDER BY ult.fecha_creacion DESC NULLS LAST, alumno
        ", [
            'id_componente' => $idComponente,
            'id_actor'      => $idUsuarioActor,
            'id_actor2'     => $idUsuarioActor,
        ]);

        return collect($filas)->map(fn($f) => [
            'id_alumno'      => (int) $f->id_alumno,
            'alumno'         => $f->alumno,
            'rut'            => $f->rut,
            'ultimo_mensaje' => $f->ultimo_mensaje,
            'ultima_fecha'   => $f->ultima_fecha,
            'es_mio'         => $f->ultimo_emisor !== null && (int) $f->ultimo_emisor === $idUsuarioActor,
            'no_leidos'      => (int) $f->no_leidos,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contenido
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Difusiones del componente, de la más antigua a la más reciente.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function difusionesDeComponente(int $idComponente, int $idUsuarioActor): Collection
    {
        $filas = DB::select("
            SELECT m.id_mensaje, m.tema, m.mensaje, m.fecha_creacion,
                   m.id_usuario_emisor,
                   TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS emisor
            FROM curso.mensaje m
            JOIN usuario.usuario u ON u.id_usuario = m.id_usuario_emisor
            WHERE m.id_componente = :id_componente
              AND m.tipo_mensaje = 'MENSAJE_PARA_TODO_EL_CURSO'
              AND m.fecha_eliminacion IS NULL
            ORDER BY m.fecha_creacion ASC, m.id_mensaje ASC
        ", ['id_componente' => $idComponente]);

        return collect($filas)->map(fn($f) => [
            'id_mensaje'     => (int) $f->id_mensaje,
            'tema'           => $f->tema,
            'mensaje'        => $f->mensaje,
            'fecha_creacion' => $f->fecha_creacion,
            'emisor'         => $f->emisor,
            'es_mio'         => (int) $f->id_usuario_emisor === $idUsuarioActor,
        ]);
    }

    /**
     * Mensajes del canal (componente, alumno), de la más antigua a la más
     * reciente. No filtra por quién es el otro extremo: por eso un colegiado o
     * un ayudante que responde queda dentro de la misma conversación.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function mensajesDelCanal(int $idComponente, int $idUsuarioAlumno, int $idUsuarioActor): Collection
    {
        $filas = DB::select("
            SELECT m.id_mensaje, m.tema, m.mensaje, m.fecha_creacion,
                   m.id_usuario_emisor, m.id_usuario_receptor,
                   TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS emisor,
                   TRIM(CONCAT(r.nombre1, ' ', COALESCE(r.apellido1, ''))) AS receptor
            FROM curso.mensaje m
            JOIN usuario.usuario u           ON u.id_usuario = m.id_usuario_emisor
            LEFT JOIN usuario.usuario r      ON r.id_usuario = m.id_usuario_receptor
            WHERE m.id_componente = :id_componente
              AND m.tipo_mensaje = 'MENSAJE_INDIVIDUAL'
              AND m.fecha_eliminacion IS NULL
              AND :id_alumno IN (m.id_usuario_emisor, m.id_usuario_receptor)
            ORDER BY m.fecha_creacion ASC, m.id_mensaje ASC
        ", [
            'id_componente' => $idComponente,
            'id_alumno'     => $idUsuarioAlumno,
        ]);

        return collect($filas)->map(fn($f) => [
            'id_mensaje'     => (int) $f->id_mensaje,
            'tema'           => $f->tema,
            'mensaje'        => $f->mensaje,
            'fecha_creacion' => $f->fecha_creacion,
            'emisor'         => $f->emisor,
            'receptor'       => $f->receptor,
            'es_alumno'      => (int) $f->id_usuario_emisor === $idUsuarioAlumno,
            'es_mio'         => (int) $f->id_usuario_emisor === $idUsuarioActor,
        ]);
    }

    /**
     * Equipo docente del componente: los de curso.docente_componente más el
     * titular del curso. Sirve para que el alumno elija a quién dirigir el
     * mensaje (aunque todos lo vean).
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function docentesDeComponente(int $idComponente): Collection
    {
        $filas = DB::select("
            SELECT
                u.id_usuario,
                TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS nombre,
                bool_or(x.es_titular) AS es_titular
            FROM (
                SELECT dc.id_docente, dc.es_titular
                FROM curso.docente_componente dc
                WHERE dc.id_componente = :id_componente

                UNION ALL

                SELECT cur.id_docente_titular, TRUE
                FROM curso.componente cmp
                JOIN curso.curso cur ON cur.id_curso = cmp.id_curso
                WHERE cmp.id_componente = :id_componente2
            ) x
            JOIN usuario.docente d ON d.id_docente = x.id_docente
            JOIN usuario.usuario u ON u.id_usuario = d.id_usuario
            GROUP BY u.id_usuario, u.nombre1, u.apellido1
            ORDER BY es_titular DESC, nombre
        ", ['id_componente' => $idComponente, 'id_componente2' => $idComponente]);

        return collect($filas)->map(fn($f) => [
            'id_usuario' => (int) $f->id_usuario,
            'nombre'     => $f->nombre,
            'es_titular' => (bool) $f->es_titular,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escritura
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envía un mensaje al canal (componente, alumno).
     *
     * El `tema` es NOT NULL en la tabla y aquí se usa como asunto del canal: si
     * no se entrega uno, se hereda el del primer mensaje del canal, y si el
     * canal está vacío se usa un valor por defecto.
     */
    public function enviarIndividual(
        int $idComponente,
        int $idUsuarioEmisor,
        int $idUsuarioReceptor,
        int $idUsuarioAlumno,
        string $mensaje,
        ?string $tema = null,
    ): int {
        $tema = $this->resolverTema($idComponente, $idUsuarioAlumno, $tema);

        return (int) DB::table('curso.mensaje')->insertGetId([
            'mensaje'             => $mensaje,
            'tema'                => $tema,
            'tipo_mensaje'        => TipoMensajeCurso::MENSAJE_INDIVIDUAL->value,
            'id_componente'       => $idComponente,
            'id_usuario_emisor'   => $idUsuarioEmisor,
            'id_usuario_receptor' => $idUsuarioReceptor,
        ], 'id_mensaje');
    }

    /**
     * Envía una difusión a todo el componente. Una sola fila: la audiencia se
     * deriva de las inscripciones al leer, así que un alumno que se inscribe
     * después también la ve.
     */
    public function enviarDifusion(
        int $idComponente,
        int $idUsuarioEmisor,
        string $mensaje,
        string $tema,
    ): int {
        return (int) DB::table('curso.mensaje')->insertGetId([
            'mensaje'             => $mensaje,
            'tema'                => $tema,
            'tipo_mensaje'        => TipoMensajeCurso::MENSAJE_PARA_TODO_EL_CURSO->value,
            'id_componente'       => $idComponente,
            'id_usuario_emisor'   => $idUsuarioEmisor,
            'id_usuario_receptor' => null,
        ], 'id_mensaje');
    }

    /** Asunto del canal: el explícito, si no el del primer mensaje, si no el default. */
    private function resolverTema(int $idComponente, int $idUsuarioAlumno, ?string $tema): string
    {
        if ($tema !== null && trim($tema) !== '') {
            return trim($tema);
        }

        $primero = DB::select("
            SELECT m.tema
            FROM curso.mensaje m
            WHERE m.id_componente = :id_componente
              AND m.tipo_mensaje = 'MENSAJE_INDIVIDUAL'
              AND m.fecha_eliminacion IS NULL
              AND :id_alumno IN (m.id_usuario_emisor, m.id_usuario_receptor)
            ORDER BY m.fecha_creacion ASC, m.id_mensaje ASC
            LIMIT 1
        ", ['id_componente' => $idComponente, 'id_alumno' => $idUsuarioAlumno]);

        return $primero[0]->tema ?? self::TEMA_POR_DEFECTO;
    }

    /**
     * Registra el acuse de lectura de los mensajes indicados. Inserta sólo lo
     * que falta: no hay UNIQUE sobre (id_mensaje, id_usuario_lector), así que se
     * filtra con NOT EXISTS en vez de ON CONFLICT.
     *
     * @param  int[]  $idsMensaje
     */
    public function marcarLeidos(array $idsMensaje, int $idUsuarioLector): void
    {
        if (empty($idsMensaje)) {
            return;
        }

        DB::statement("
            INSERT INTO curso.interaccion_mensaje (id_mensaje, id_usuario_lector, fecha_creacion)
            SELECT m.id_mensaje, :id_lector, now()
            FROM curso.mensaje m
            WHERE m.id_mensaje = ANY(string_to_array(:ids, ',')::int[])
              AND m.id_usuario_emisor <> :id_emisor
              AND NOT EXISTS (
                    SELECT 1 FROM curso.interaccion_mensaje im
                    WHERE im.id_mensaje = m.id_mensaje
                      AND im.id_usuario_lector = :id_lector2
                      AND im.fecha_eliminacion IS NULL
              )
        ", [
            'id_lector'  => $idUsuarioLector,
            'id_lector2' => $idUsuarioLector,
            'id_emisor'  => $idUsuarioLector,
            'ids'        => $this->pgIntArray($idsMensaje),
        ]);
    }

    /** Serializa ints para `string_to_array(:x, ',')::int[]`. */
    private function pgIntArray(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }
}
