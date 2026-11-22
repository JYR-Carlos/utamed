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
 *  - Conversación privada (tipo_mensaje = MENSAJE_INDIVIDUAL)
 *    Es el trío (componente, alumno, interlocutor), donde el interlocutor es
 *    una persona del equipo del componente: titular, docente o ayudante. La
 *    ven sólo esos dos (OBS-49, decisión del 20-11-2026): el titular no lee las
 *    conversaciones de los colegiados ni las de los ayudantes. El par sale de
 *    id_usuario_emisor e id_usuario_receptor, así que no hay cambio de esquema.
 *    Los mensajes de cuando el canal era compartido por todo el equipo quedan en
 *    la conversación del par que los escribió.
 *
 * NO HAY MENSAJES ENTRE ESTUDIANTES. Uno de los dos lados de cada conversación
 * es siempre alguien del equipo del componente. El chat entre compañeros y por
 * grupos de trabajo (OBS-53, T50) no se aplica porque UTAmed no es una red
 * social; queda para debatir (decisión del 22-11-2026).
 *
 * QUIÉN ES EL ALUMNO: se resuelve por la inscripción a ESE componente
 * (curso.inscripcion_componente), no por el rol global. Así un ayudante que
 * además sea alumno avanzado no puede confundirse con el alumno de la
 * conversación.
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
                asig.cod_asignatura,
                cur.agno_real,
                cur.semestre_real,
                cur.letra_grupo,
                cur.id_docente_titular
            FROM curso.componente cmp
            JOIN curso.curso cur           ON cur.id_curso = cmp.id_curso
            JOIN curso.tipo_componente tc  ON tc.id_tipo_componente = cmp.id_tipo_componente
            LEFT JOIN administrativo.asignacion_plan ap ON ap.id_asignacion_plan = cur.id_asignacion_plan
            LEFT JOIN administrativo.asignatura asig    ON asig.id_asignatura = ap.id_asignatura
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
     * Lo que alcanza a un lector: las difusiones del componente y los mensajes
     * individuales donde él es emisor o receptor. Vale igual para el alumno y
     * para el equipo, porque cada conversación es de dos.
     */
    private const ALCANCE = "(m.tipo_mensaje = 'MENSAJE_PARA_TODO_EL_CURSO'
              OR :id_alcance IN (m.id_usuario_emisor, m.id_usuario_receptor))";

    /**
     * No leídos por componente para un usuario: cuenta difusiones y mensajes
     * de sus conversaciones que aún no tienen acuse de lectura suyo.
     *
     * @param  int[]  $componenteIds
     * @return array<int,int>  [id_componente => no leídos]
     */
    public function noLeidosPorComponente(array $componenteIds, int $idUsuario): array
    {
        if (empty($componenteIds)) {
            return [];
        }

        $alcance = self::ALCANCE;

        $bindings = [
            'ids'        => $this->pgIntArray($componenteIds),
            'id_lector'  => $idUsuario,
            'id_emisor'  => $idUsuario,
            'id_alcance' => $idUsuario,
        ];

        $filas = DB::select("
            SELECT m.id_componente, COUNT(*) AS no_leidos
            FROM curso.mensaje m
            WHERE m.id_componente = ANY(string_to_array(:ids, ',')::int[])
              AND m.fecha_eliminacion IS NULL
              AND m.id_usuario_emisor <> :id_emisor
              AND {$alcance}
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
     * Instante del mensaje SIN LEER más reciente por componente. Mismo alcance
     * y mismos filtros que {@see noLeidosPorComponente}, así que un componente
     * sin no leídos no aparece en el resultado: esto data la bandeja, no la
     * cuenta.
     *
     * @param  int[]  $componenteIds
     * @return array<int,string>  [id_componente => fecha_creacion]
     */
    public function ultimoNoLeidoPorComponente(array $componenteIds, int $idUsuario): array
    {
        if (empty($componenteIds)) {
            return [];
        }

        $alcance = self::ALCANCE;

        $filas = DB::select("
            SELECT m.id_componente, MAX(m.fecha_creacion) AS ultimo
            FROM curso.mensaje m
            WHERE m.id_componente = ANY(string_to_array(:ids, ',')::int[])
              AND m.fecha_eliminacion IS NULL
              AND m.id_usuario_emisor <> :id_emisor
              AND {$alcance}
              AND NOT EXISTS (
                    SELECT 1 FROM curso.interaccion_mensaje im
                    WHERE im.id_mensaje = m.id_mensaje
                      AND im.id_usuario_lector = :id_lector
                      AND im.fecha_eliminacion IS NULL
              )
            GROUP BY m.id_componente
        ", [
            'ids'        => $this->pgIntArray($componenteIds),
            'id_lector'  => $idUsuario,
            'id_emisor'  => $idUsuario,
            'id_alcance' => $idUsuario,
        ]);

        return collect($filas)->pluck('ultimo', 'id_componente')->all();
    }

    /**
     * Condición SQL de una conversación: los mensajes individuales entre dos
     * personas, en cualquier sentido. $a y $b son expresiones SQL (una columna
     * o un placeholder; cada placeholder con nombre propio, porque PDO no deja
     * repetirlos).
     */
    private function sqlPar(string $a, string $b): string
    {
        return "m.tipo_mensaje = 'MENSAJE_INDIVIDUAL'
                  AND {$a} IN (m.id_usuario_emisor, m.id_usuario_receptor)
                  AND {$b} IN (m.id_usuario_emisor, m.id_usuario_receptor)";
    }

    /**
     * Conversaciones del equipo con los alumnos de un componente, vistas por
     * una persona del equipo: una fila por alumno inscrito (existan o no
     * mensajes, para poder iniciar la conversación) con el último mensaje y
     * los no leídos de SU conversación con ese alumno. Lo que el alumno habló
     * con otros docentes no entra.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function canalesDeComponente(int $idComponente, int $idUsuarioActor): Collection
    {
        $parUltimo = $this->sqlPar('u.id_usuario', ':id_actor_ult');
        $parNoLeidos = $this->sqlPar('u.id_usuario', ':id_actor_nl');

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
                  AND m.fecha_eliminacion IS NULL
                  AND {$parUltimo}
                ORDER BY m.fecha_creacion DESC, m.id_mensaje DESC
                LIMIT 1
            ) ult ON TRUE
            LEFT JOIN LATERAL (
                SELECT COUNT(*) AS no_leidos
                FROM curso.mensaje m
                WHERE m.id_componente = ic.id_componente
                  AND m.fecha_eliminacion IS NULL
                  AND {$parNoLeidos}
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
            'id_actor_ult'  => $idUsuarioActor,
            'id_actor_nl'   => $idUsuarioActor,
            'id_actor'      => $idUsuarioActor,
            'id_actor2'     => $idUsuarioActor,
        ]);

        $fotoIntranet = app(FotoIntranetService::class);

        return collect($filas)->map(fn($f) => [
            'id_alumno'      => (int) $f->id_alumno,
            'alumno'         => $f->alumno,
            'rut'            => $f->rut,
            'url_foto'       => $f->rut ? $fotoIntranet->getImagenPerfilURL($f->rut) : null,
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
     * Mensajes de la conversación entre el alumno y una persona del equipo, de
     * la más antigua a la más reciente. Lo que el alumno habló con otros del
     * equipo no entra.
     *
     * @param  int  $idUsuarioActor  quien mira (el alumno o el interlocutor), para `es_mio`.
     * @return Collection<int,array<string,mixed>>
     */
    public function mensajesDeConversacion(
        int $idComponente,
        int $idUsuarioAlumno,
        int $idUsuarioInterlocutor,
        int $idUsuarioActor,
    ): Collection {
        $par = $this->sqlPar(':id_alumno', ':id_interlocutor');

        $filas = DB::select("
            SELECT m.id_mensaje, m.tema, m.mensaje, m.fecha_creacion,
                   m.id_usuario_emisor, m.id_usuario_receptor,
                   TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS emisor,
                   TRIM(CONCAT(r.nombre1, ' ', COALESCE(r.apellido1, ''))) AS receptor
            FROM curso.mensaje m
            JOIN usuario.usuario u           ON u.id_usuario = m.id_usuario_emisor
            LEFT JOIN usuario.usuario r      ON r.id_usuario = m.id_usuario_receptor
            WHERE m.id_componente = :id_componente
              AND m.fecha_eliminacion IS NULL
              AND {$par}
            ORDER BY m.fecha_creacion ASC, m.id_mensaje ASC
        ", [
            'id_componente'   => $idComponente,
            'id_alumno'       => $idUsuarioAlumno,
            'id_interlocutor' => $idUsuarioInterlocutor,
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
     * Resumen de las conversaciones de un alumno en un componente, una por
     * interlocutor: último mensaje y cuántos le faltan por leer. Sólo trae a
     * quienes ya tienen mensajes con él; el equipo completo sale de
     * {@see equipoDeComponente()}.
     *
     * @return Collection<int,array<string,mixed>>  indexada por id_usuario del interlocutor
     */
    public function conversacionesDeAlumno(int $idComponente, int $idUsuarioAlumno): Collection
    {
        return $this->conversacionesDeAlumnoEn([$idComponente], $idUsuarioAlumno)
            ->keyBy('id_usuario');
    }

    /**
     * Las conversaciones de un alumno en varios componentes a la vez: una fila
     * por (componente, interlocutor) con el asunto, el último mensaje y lo que
     * le falta por leer. Sólo las que ya tienen mensajes. Es la base de
     * {@see conversacionesDeAlumno()} y del buzón del alumno.
     *
     * @param  int[]  $componenteIds
     * @return Collection<int,array{id_componente:int, id_usuario:int, nombre:string, tema:?string, ultimo_mensaje:?string, ultima_fecha:string, ultimo_es_mio:bool, no_leidos:int}>
     */
    public function conversacionesDeAlumnoEn(array $componenteIds, int $idUsuarioAlumno): Collection
    {
        if (empty($componenteIds)) {
            return collect();
        }

        $filas = DB::select("
            WITH suyos AS (
                SELECT m.id_mensaje, m.id_componente, m.tema, m.mensaje, m.fecha_creacion, m.id_usuario_emisor,
                       CASE WHEN m.id_usuario_emisor = :id_alumno
                            THEN m.id_usuario_receptor
                            ELSE m.id_usuario_emisor END AS interlocutor,
                       (m.id_usuario_emisor <> :id_alumno2 AND NOT EXISTS (
                            SELECT 1 FROM curso.interaccion_mensaje im
                            WHERE im.id_mensaje = m.id_mensaje
                              AND im.id_usuario_lector = :id_alumno3
                              AND im.fecha_eliminacion IS NULL
                       )) AS sin_leer
                FROM curso.mensaje m
                WHERE m.id_componente = ANY(string_to_array(:ids, ',')::int[])
                  AND m.tipo_mensaje = 'MENSAJE_INDIVIDUAL'
                  AND m.fecha_eliminacion IS NULL
                  AND :id_alumno4 IN (m.id_usuario_emisor, m.id_usuario_receptor)
            )
            SELECT DISTINCT ON (s.id_componente, s.interlocutor)
                   s.id_componente,
                   s.interlocutor,
                   TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS nombre,
                   s.tema,
                   s.mensaje         AS ultimo_mensaje,
                   s.fecha_creacion  AS ultima_fecha,
                   s.id_usuario_emisor AS ultimo_emisor,
                   COUNT(*) FILTER (WHERE s.sin_leer)
                       OVER (PARTITION BY s.id_componente, s.interlocutor) AS no_leidos
            FROM suyos s
            JOIN usuario.usuario u ON u.id_usuario = s.interlocutor
            ORDER BY s.id_componente, s.interlocutor, s.fecha_creacion DESC, s.id_mensaje DESC
        ", [
            'ids'        => $this->pgIntArray($componenteIds),
            'id_alumno'  => $idUsuarioAlumno,
            'id_alumno2' => $idUsuarioAlumno,
            'id_alumno3' => $idUsuarioAlumno,
            'id_alumno4' => $idUsuarioAlumno,
        ]);

        return collect($filas)->map(fn($f) => [
            'id_componente'  => (int) $f->id_componente,
            'id_usuario'     => (int) $f->interlocutor,
            'nombre'         => $f->nombre,
            'tema'           => $f->tema,
            'ultimo_mensaje' => $f->ultimo_mensaje,
            'ultima_fecha'   => $f->ultima_fecha,
            'ultimo_es_mio'  => (int) $f->ultimo_emisor === $idUsuarioAlumno,
            'no_leidos'      => (int) $f->no_leidos,
        ]);
    }

    /**
     * Los avisos de varios componentes, resumidos: por componente, el último
     * aviso y cuántos le faltan por leer al usuario. Sólo los componentes que
     * tienen avisos.
     *
     * @param  int[]  $componenteIds
     * @return Collection<int,array{id_componente:int, tema:?string, mensaje:?string, fecha:string, emisor:string, no_leidos:int}>
     */
    public function avisosEn(array $componenteIds, int $idUsuario): Collection
    {
        if (empty($componenteIds)) {
            return collect();
        }

        $filas = DB::select("
            SELECT DISTINCT ON (m.id_componente)
                   m.id_componente, m.tema, m.mensaje, m.fecha_creacion,
                   TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS emisor,
                   COUNT(*) FILTER (WHERE m.id_usuario_emisor <> :id_emisor AND NOT EXISTS (
                        SELECT 1 FROM curso.interaccion_mensaje im
                        WHERE im.id_mensaje = m.id_mensaje
                          AND im.id_usuario_lector = :id_lector
                          AND im.fecha_eliminacion IS NULL
                   )) OVER (PARTITION BY m.id_componente) AS no_leidos
            FROM curso.mensaje m
            JOIN usuario.usuario u ON u.id_usuario = m.id_usuario_emisor
            WHERE m.id_componente = ANY(string_to_array(:ids, ',')::int[])
              AND m.tipo_mensaje = 'MENSAJE_PARA_TODO_EL_CURSO'
              AND m.fecha_eliminacion IS NULL
            ORDER BY m.id_componente, m.fecha_creacion DESC, m.id_mensaje DESC
        ", [
            'ids'       => $this->pgIntArray($componenteIds),
            'id_emisor' => $idUsuario,
            'id_lector' => $idUsuario,
        ]);

        return collect($filas)->map(fn($f) => [
            'id_componente' => (int) $f->id_componente,
            'tema'          => $f->tema,
            'mensaje'       => $f->mensaje,
            'fecha'         => $f->fecha_creacion,
            'emisor'        => $f->emisor,
            'no_leidos'     => (int) $f->no_leidos,
        ]);
    }

    /**
     * Equipo del componente, a quien el alumno puede escribir: el titular del
     * curso, los docentes de curso.docente_componente y los ayudantes del curso
     * (mismo criterio que {@see componentesDeAyudante()}). Una fila por
     * persona; si alguien tiene dos papeles queda el de más rango.
     *
     * @return Collection<int,array{id_usuario:int, nombre:string, rol:string}>
     */
    public function equipoDeComponente(int $idComponente): Collection
    {
        $filas = DB::select("
            SELECT
                u.id_usuario,
                TRIM(CONCAT(u.nombre1, ' ', COALESCE(u.apellido1, ''))) AS nombre,
                MIN(x.orden) AS orden
            FROM (
                SELECT d.id_usuario, CASE WHEN dc.es_titular THEN 1 ELSE 2 END AS orden
                FROM curso.docente_componente dc
                JOIN usuario.docente d ON d.id_docente = dc.id_docente
                WHERE dc.id_componente = :id_componente

                UNION ALL

                SELECT d.id_usuario, 1
                FROM curso.componente cmp
                JOIN curso.curso cur   ON cur.id_curso = cmp.id_curso
                JOIN usuario.docente d ON d.id_docente = cur.id_docente_titular
                WHERE cmp.id_componente = :id_componente2

                UNION ALL

                SELECT ura.id_usuario, 3
                FROM curso.componente cmp
                JOIN curso.curso cur ON cur.id_curso = cmp.id_curso
                JOIN usuario.usuario_rol_asignacion ura ON ura.id_contexto = cur.id_contexto
                JOIN usuario.rol r ON r.id_rol = ura.id_rol
                WHERE cmp.id_componente = :id_componente3
                  AND ura.esta_activo = TRUE
                  AND ura.fue_eliminado = FALSE
                  AND LOWER(r.nombre) = 'ayudante'
            ) x
            JOIN usuario.usuario u ON u.id_usuario = x.id_usuario
            GROUP BY u.id_usuario, u.nombre1, u.apellido1
            ORDER BY orden, nombre
        ", [
            'id_componente'  => $idComponente,
            'id_componente2' => $idComponente,
            'id_componente3' => $idComponente,
        ]);

        $roles = [1 => 'Titular', 2 => 'Docente', 3 => 'Ayudante'];

        return collect($filas)->map(fn($f) => [
            'id_usuario' => (int) $f->id_usuario,
            'nombre'     => $f->nombre,
            'rol'        => $roles[(int) $f->orden],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escritura
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envía un mensaje a la conversación entre emisor y receptor (uno es el
     * alumno y el otro alguien del equipo). Quién puede escribirle a quién lo
     * deciden los controladores.
     *
     * El `tema` es NOT NULL en la tabla y aquí se usa como asunto de la
     * conversación: si no se entrega uno, se hereda el del primer mensaje entre
     * los dos, y si aún no hay ninguno se usa un valor por defecto.
     */
    public function enviarIndividual(
        int $idComponente,
        int $idUsuarioEmisor,
        int $idUsuarioReceptor,
        string $mensaje,
        ?string $tema = null,
    ): int {
        $tema = $this->resolverTema($idComponente, $idUsuarioEmisor, $idUsuarioReceptor, $tema);

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

    /** Asunto de la conversación: el explícito, si no el del primer mensaje del par, si no el default. */
    private function resolverTema(int $idComponente, int $idUsuarioA, int $idUsuarioB, ?string $tema): string
    {
        if ($tema !== null && trim($tema) !== '') {
            return trim($tema);
        }

        $par = $this->sqlPar(':id_a', ':id_b');

        $primero = DB::select("
            SELECT m.tema
            FROM curso.mensaje m
            WHERE m.id_componente = :id_componente
              AND m.fecha_eliminacion IS NULL
              AND {$par}
            ORDER BY m.fecha_creacion ASC, m.id_mensaje ASC
            LIMIT 1
        ", ['id_componente' => $idComponente, 'id_a' => $idUsuarioA, 'id_b' => $idUsuarioB]);

        return $primero[0]->tema ?? self::TEMA_POR_DEFECTO;
    }

    /**
     * De esos mensajes, los de otros que el lector todavía no abre: lo que
     * marcarLeidos() marcaría ahora. Para destacarlos como «Nuevo» antes de
     * marcarlos.
     *
     * @param  int[]  $idsMensaje
     * @return array<int,true>  id_mensaje => true
     */
    public function sinLeer(array $idsMensaje, int $idUsuarioLector): array
    {
        if (empty($idsMensaje)) {
            return [];
        }

        $ids = DB::select("
            SELECT m.id_mensaje
            FROM curso.mensaje m
            WHERE m.id_mensaje = ANY(string_to_array(:ids, ',')::int[])
              AND m.id_usuario_emisor <> :id_emisor
              AND NOT EXISTS (
                    SELECT 1 FROM curso.interaccion_mensaje im
                    WHERE im.id_mensaje = m.id_mensaje
                      AND im.id_usuario_lector = :id_lector
                      AND im.fecha_eliminacion IS NULL
              )
        ", [
            'id_emisor' => $idUsuarioLector,
            'id_lector' => $idUsuarioLector,
            'ids'       => $this->pgIntArray($idsMensaje),
        ]);

        return collect($ids)->mapWithKeys(fn($f) => [(int) $f->id_mensaje => true])->all();
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
