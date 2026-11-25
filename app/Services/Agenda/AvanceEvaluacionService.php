<?php

namespace App\Services\Agenda;

use App\Models\Agenda\Agenda;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Avance de evaluación de las actividades de un curso, para las tarjetas del
 * tablero del docente (lista de actividades y pestaña Actividades del curso).
 *
 * Cada grupo asignado (agenda.actividad_asignada_grupo) es una unidad
 * evaluable. Por actividad se cuenta:
 *
 *  - total_grupos:        grupos asignados.
 *  - grupos_con_entrega:  grupos con al menos una «Entrega de archivo» vigente
 *                         (no cancelada). Son grupos, no filas: un grupo que
 *                         reemplaza su archivo cuenta una vez.
 *  - calificados:         grupos con nota (sumativas).
 *  - por_calificar:       grupos que entregaron y aún no tienen nota.
 *  - revisados:           grupos con una evaluación del docente (formativas:
 *                         Bueno, Regular o Malo; revisión «Mi UTAmed»).
 *  - por_revisar:         grupos que entregaron y aún no se evalúan.
 *  - promedio:            promedio de las notas puestas, o null.
 *
 * Escribir en la agenda («Feedback», a un grupo o a todos) es conversación y no
 * cambia el estado: una formativa pasa a «Revisado» sólo cuando se evalúa, igual
 * que la ve el estudiante (ResumenActividadesEstudiante).
 */
class AvanceEvaluacionService
{
    /**
     * @param  iterable<int>  $idsActividad
     * @return Collection<int, array{total_grupos:int, grupos_con_entrega:int, calificados:int, por_calificar:int, revisados:int, por_revisar:int, promedio:?float}>
     *         indexada por id_actividad
     */
    public function porActividad(iterable $idsActividad): Collection
    {
        $ids = collect($idsActividad)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $grupos = DB::table('agenda.actividad_asignada_grupo')
            ->whereIn('id_actividad', $ids)
            ->get(['id_actividad', 'id_actividad_asignada_grupo', 'nota']);

        $idsGrupo = $grupos->pluck('id_actividad_asignada_grupo');

        $conEntrega = $idsGrupo->isEmpty() ? collect() : DB::table('agenda.agenda as a')
            ->whereIn('a.id_actividad_asignada_grupo', $idsGrupo)
            ->where(fn ($q) => Agenda::soloEntregasVigentes($q, 'a'))
            ->distinct()
            ->pluck('a.id_actividad_asignada_grupo')
            ->flip();

        $evaluados = $this->gruposEvaluados($idsGrupo);

        return $grupos->groupBy('id_actividad')->map(function (Collection $deActividad) use ($conEntrega, $evaluados) {
            $notas = $deActividad->pluck('nota')->filter(fn ($n) => $n !== null);
            $entregaron = $deActividad->filter(fn ($g) => $conEntrega->has($g->id_actividad_asignada_grupo));

            return [
                'total_grupos'       => $deActividad->count(),
                'grupos_con_entrega' => $entregaron->count(),
                'calificados'        => $notas->count(),
                'por_calificar'      => $entregaron->whereNull('nota')->count(),
                'revisados'          => $deActividad->filter(fn ($g) => $evaluados->has($g->id_actividad_asignada_grupo))->count(),
                'por_revisar'        => $entregaron->reject(fn ($g) => $evaluados->has($g->id_actividad_asignada_grupo))->count(),
                'promedio'           => $notas->isEmpty() ? null : round((float) $notas->avg(), 1),
            ];
        });
    }

    /**
     * Grupos con al menos una evaluación del docente, como mapa id_grupo =>
     * índice para usar con has(). Público porque la hoja de la actividad, la
     * ficha del estudiante y la pestaña Estudiantes marcan con el mismo criterio
     * cada entrega formativa como «Revisado».
     *
     * @param  Collection<int, int>  $idsGrupo
     */
    public function gruposEvaluados(Collection $idsGrupo): Collection
    {
        if ($idsGrupo->isEmpty()) {
            return collect();
        }

        return DB::table('agenda.evaluacion as ev')
            ->join('agenda.agenda as a', 'a.id_agenda', '=', 'ev.id_agenda')
            ->whereIn('a.id_actividad_asignada_grupo', $idsGrupo)
            ->distinct()
            ->pluck('a.id_actividad_asignada_grupo')
            ->flip();
    }
}
