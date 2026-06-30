<?php

namespace App\Http\Controllers\Docente;

use Illuminate\Support\Facades\DB;

/**
 * Trait compartido para el cálculo de mensajes pendientes de respuesta.
 *
 * Un grupo se considera "pendiente" cuando su último mensaje de tipo conversación
 * docente↔estudiante es de tipo "Mensaje al profesor" (es decir, el docente aún
 * no ha respondido).  Se usa DISTINCT ON de PostgreSQL para obtener eficientemente
 * el último mensaje por grupo.
 *
 * Usado por: MensajesController, DashboardController.
 */
trait ContaPendientesMensajes
{
    /**
     * Devuelve [id_actividad => nº de grupos cuyo último mensaje es del estudiante],
     * acotado a los cursos indicados.
     *
     * @param  int[]  $cursoIds
     * @return array<int,int>
     */
    protected function pendientesPorActividad(array $cursoIds): array
    {
        if (empty($cursoIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cursoIds), '?'));

        $rows = DB::select(
            "SELECT ultimo.id_actividad, COUNT(*) AS pendientes
             FROM (
                 SELECT DISTINCT ON (a.id_actividad_asignada_grupo)
                        aag.id_actividad,
                        a.tipo_mensaje
                 FROM agenda.agenda a
                 JOIN agenda.actividad_asignada_grupo aag
                      ON aag.id_actividad_asignada_grupo = a.id_actividad_asignada_grupo
                 JOIN agenda.actividad act ON act.id_actividad = aag.id_actividad
                 JOIN curso.componente c ON c.id_componente = act.id_componente
                 WHERE c.id_curso IN ($placeholders)
                   AND a.tipo_mensaje IN ('Mensaje al profesor', 'Feedback')
                 ORDER BY a.id_actividad_asignada_grupo, a.fecha_envio DESC
             ) ultimo
             WHERE ultimo.tipo_mensaje = 'Mensaje al profesor'
             GROUP BY ultimo.id_actividad",
            $cursoIds,
        );

        return collect($rows)->pluck('pendientes', 'id_actividad')
            ->map(fn($n) => (int) $n)
            ->all();
    }

    /**
     * Cuenta el total de grupos pendientes en un conjunto de cursos.
     * Útil para el badge del dashboard.
     *
     * @param  int[]  $cursoIds
     */
    protected function totalPendientes(array $cursoIds): int
    {
        return array_sum($this->pendientesPorActividad($cursoIds));
    }
}
