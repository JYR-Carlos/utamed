<?php

namespace App\Services\Agenda;

use App\Enums\DB\TipoMensaje;
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
 *  - con_feedback:        grupos a los que el docente les envió feedback
 *                         (formativas: así se da por revisada, decisión del
 *                         equipo del 2026-10-07).
 *  - por_revisar:         grupos que entregaron y aún no reciben feedback.
 *  - promedio:            promedio de las notas puestas, o null.
 *
 * Un mensaje grupal del titular («todos los grupos», MensajesController::send)
 * también se guarda como «Feedback», una fila idéntica por grupo. Eso es un
 * aviso, no la revisión de una entrega: las filas con el mismo emisor, fecha y
 * texto repetidas en más de un grupo de la actividad no cuentan.
 */
class AvanceEvaluacionService
{
    /**
     * @param  iterable<int>  $idsActividad
     * @return Collection<int, array{total_grupos:int, grupos_con_entrega:int, calificados:int, por_calificar:int, con_feedback:int, por_revisar:int, promedio:?float}>
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
        $actividadDeGrupo = $grupos->pluck('id_actividad', 'id_actividad_asignada_grupo');

        $conEntrega = $idsGrupo->isEmpty() ? collect() : DB::table('agenda.agenda as a')
            ->whereIn('a.id_actividad_asignada_grupo', $idsGrupo)
            ->where(fn ($q) => Agenda::soloEntregasVigentes($q, 'a'))
            ->distinct()
            ->pluck('a.id_actividad_asignada_grupo')
            ->flip();

        $conFeedback = $this->gruposConFeedback($idsGrupo, $actividadDeGrupo);

        return $grupos->groupBy('id_actividad')->map(function (Collection $deActividad) use ($conEntrega, $conFeedback) {
            $notas = $deActividad->pluck('nota')->filter(fn ($n) => $n !== null);
            $entregaron = $deActividad->filter(fn ($g) => $conEntrega->has($g->id_actividad_asignada_grupo));

            return [
                'total_grupos'       => $deActividad->count(),
                'grupos_con_entrega' => $entregaron->count(),
                'calificados'        => $notas->count(),
                'por_calificar'      => $entregaron->whereNull('nota')->count(),
                'con_feedback'       => $deActividad->filter(fn ($g) => $conFeedback->has($g->id_actividad_asignada_grupo))->count(),
                'por_revisar'        => $entregaron->reject(fn ($g) => $conFeedback->has($g->id_actividad_asignada_grupo))->count(),
                'promedio'           => $notas->isEmpty() ? null : round((float) $notas->avg(), 1),
            ];
        });
    }

    /**
     * Grupos con feedback dirigido a ellos (no un mensaje masivo), como mapa
     * id_grupo => índice para usar con has().
     */
    private function gruposConFeedback(Collection $idsGrupo, Collection $actividadDeGrupo): Collection
    {
        if ($idsGrupo->isEmpty()) {
            return collect();
        }

        $filas = DB::table('agenda.agenda')
            ->whereIn('id_actividad_asignada_grupo', $idsGrupo)
            ->where('tipo_mensaje', TipoMensaje::FEEDBACK->value)
            ->get(['id_actividad_asignada_grupo', 'id_usuario_emisor', 'fecha_envio', 'mensaje']);

        return $filas
            ->groupBy(fn ($f) => implode('|', [
                $actividadDeGrupo[$f->id_actividad_asignada_grupo] ?? '',
                $f->id_usuario_emisor,
                $f->fecha_envio,
                md5((string) $f->mensaje),
            ]))
            ->filter(fn (Collection $mismas) => $mismas->pluck('id_actividad_asignada_grupo')->unique()->count() === 1)
            ->flatten(1)
            ->pluck('id_actividad_asignada_grupo')
            ->unique()
            ->flip();
    }
}
