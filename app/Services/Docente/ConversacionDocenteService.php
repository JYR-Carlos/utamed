<?php

namespace App\Services\Docente;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servicio reutilizable para la conversación docente ↔ estudiante sobre grupos
 * de actividad (agenda.agenda).
 *
 * Centraliza la lógica que antes estaba duplicada en DocenteActivityController
 * (showMensajesCurso, getMensajesEstudiante, getGrupoMensajes y el closure
 * interaccionesGrupo de showEvaluacion): resolver los grupos de un estudiante
 * dentro de un curso, traer los mensajes de un conjunto de grupos y decorar cada
 * registro con flags derivadas del tipo de mensaje.
 *
 * Tipos de mensaje (agenda.agenda.tipo_mensaje, ENUM):
 *   - 'Mensaje al profesor' : mensaje enviado por el estudiante.
 *   - 'Feedback'            : retroalimentación del docente.
 *   - 'Entrega de archivo'  : entrega del estudiante.
 *   - 'Evaluación'          : evaluación formal del docente.
 */
class ConversacionDocenteService
{
    /** Tipos de la conversación "ligera" (sólo mensajes y feedback). */
    public const TIPOS_CONVERSACION = ['Mensaje al profesor', 'Feedback'];

    /** Tipos del hilo completo de un grupo (incluye entregas y evaluaciones). */
    public const TIPOS_HILO_COMPLETO = ['Mensaje al profesor', 'Feedback', 'Entrega de archivo', 'Evaluación'];

    /**
     * IDs de los grupos (actividad_asignada_grupo) en los que participa un
     * estudiante dentro de un curso.
     *
     * @return Collection<int, int>
     */
    public function gruposDeEstudianteEnCurso(int $idCurso, int $idEstudiante): Collection
    {
        return DB::table('agenda.integrante_grupo as ig')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'ig.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->where('ig.id_estudiante', $idEstudiante)
            ->where('c.id_curso', $idCurso)
            ->pluck('ig.id_actividad_asignada_grupo');
    }

    /**
     * Conversación (mensajes + feedback) de un estudiante en un curso, agregando
     * el nombre de la actividad de cada grupo. Usada en las vistas/listados de
     * mensajería por estudiante.
     *
     * @param  Collection<int, int>|array<int>  $grupoIds
     * @return Collection<int, object>
     */
    public function conversacionEstudiante($grupoIds): Collection
    {
        $grupoIds = collect($grupoIds);
        if ($grupoIds->isEmpty()) {
            return collect();
        }

        return DB::table('agenda.agenda as a')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->whereIn('a.id_actividad_asignada_grupo', $grupoIds)
            ->whereIn('a.tipo_mensaje', self::TIPOS_CONVERSACION)
            ->orderBy('a.fecha_envio', 'asc')
            ->select(
                'a.id_agenda',
                'a.fecha_envio',
                'a.mensaje',
                'a.tipo_mensaje as tipo_registro',
                NombreUsuario::sqlConcat('u', 'emisor_nombre'),
                'u.id_usuario as emisor_id_usuario',
                'act.nombre as actividad_nombre',
                'act.id_actividad',
                'aag.id_actividad_asignada_grupo as grupo',
            )
            ->get();
    }

    /**
     * Hilo completo de un grupo (mensajes, feedback, entregas y evaluaciones),
     * con join a la evaluación y cada registro decorado con flags derivadas.
     *
     * @return Collection<int, array>
     */
    public function hiloCompletoGrupo(int $grupoId): Collection
    {
        return DB::table('agenda.agenda as a')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'a.id_usuario_emisor')
            ->leftJoin('agenda.evaluacion as ev', 'ev.id_agenda', '=', 'a.id_agenda')
            ->where('a.id_actividad_asignada_grupo', $grupoId)
            ->whereIn('a.tipo_mensaje', self::TIPOS_HILO_COMPLETO)
            ->orderBy('a.fecha_envio', 'asc')
            ->select(
                'a.id_agenda',
                'a.fecha_envio',
                'a.mensaje',
                'a.tipo_mensaje as tipo_registro',
                'u.id_usuario as emisor_id_usuario',
                NombreUsuario::sqlConcat('u', 'emisor_nombre'),
                'ev.puntaje_obtenido',
                'ev.evaluacion_obtenida',
                'ev.id_evaluacion',
                'ev.resultado',
            )
            ->get()
            ->map(fn ($m) => $this->decorar($m))
            ->values();
    }

    /**
     * Decora un registro de agenda con las flags derivadas del tipo de mensaje.
     * Incluye `resultado` decodificado si la columna está presente (hilo completo
     * con join a evaluacion).
     *
     * @param  object  $m
     * @return array
     */
    public function decorar($m): array
    {
        $datos = array_merge((array) $m, [
            'id_interaccion'       => $m->id_agenda,
            'fecha_emision'        => $m->fecha_envio,
            'tipo_interaccion'     => $m->tipo_registro,
            'emisor'               => $m->emisor_nombre,
            'es_de_docente'        => in_array($m->tipo_registro, ['Feedback', 'Evaluación']),
            'es_retroalimentacion' => $m->tipo_registro === 'Feedback',
            'es_entrega'           => $m->tipo_registro === 'Entrega de archivo',
            'tiene_evaluacion'     => ($m->id_evaluacion ?? null) !== null,
            'adjunta_rubrica'      => ($m->id_evaluacion ?? null) !== null,
        ]);

        if (property_exists($m, 'resultado')) {
            $datos['resultado'] = $m->resultado ? json_decode($m->resultado, true) : null;
        }

        return $datos;
    }
}
