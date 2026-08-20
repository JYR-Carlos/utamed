<?php

namespace App\Console\Commands;

use App\Models\Agenda\ActividadAsignadaGrupo;
use Illuminate\Console\Command;

/**
 * Recalcula y persiste agenda.actividad_asignada_grupo.estado_actividad_asignada
 * para los grupos ya existentes.
 *
 * La columna se escribe en 'PLANIFICADA' al crear el grupo y nadie la volvía a
 * tocar (ver ActividadAsignadaGrupo::sincronizarEstado), así que todo lo creado
 * antes de ese fix quedó congelado ahí sin importar la fecha límite o la
 * visibilidad real de la actividad. Este comando corrige esos datos históricos;
 * de ahí en adelante la sincronización ocurre sola en los puntos de lectura y
 * escritura del controlador.
 */
class SincronizarEstadosActividad extends Command
{
    protected $signature = 'agenda:sincronizar-estados
                            {--curso= : Limitar a un id_curso concreto}
                            {--dry-run : Sólo informar de lo que cambiaría, sin escribir}';

    protected $description = 'Recalcula y persiste el estado de cada grupo de actividad según fecha límite y visibilidad';

    public function handle(): int
    {
        $grupos = ActividadAsignadaGrupo::with('actividad')
            ->when(
                $this->option('curso'),
                fn ($q, $idCurso) => $q->whereHas(
                    'actividad.componente',
                    fn ($qq) => $qq->where('id_curso', $idCurso)
                )
            )
            ->get();

        if ($grupos->isEmpty()) {
            $this->warn('No hay grupos que revisar.');

            return self::SUCCESS;
        }

        $esSimulacion = (bool) $this->option('dry-run');
        $actualizados = 0;

        foreach ($grupos as $grupo) {
            $estadoActual = $grupo->estado_actividad_asignada?->value;
            $estadoCalculado = $grupo->calcularEstadoGrupo();

            $cambia = $estadoActual !== $estadoCalculado
                && \App\Enums\DB\EstadoActividadAsignada::tryFrom($estadoCalculado) !== null;

            if (!$cambia) {
                continue;
            }

            $actualizados++;

            if ($esSimulacion) {
                $this->line("  grupo #{$grupo->id_actividad_asignada_grupo}: {$estadoActual} -> {$estadoCalculado}");
                continue;
            }

            $grupo->sincronizarEstado();
        }

        $verbo = $esSimulacion ? 'Se actualizarían' : 'Actualizados';
        $this->info("{$verbo} {$actualizados} de {$grupos->count()} grupo(s).");

        return self::SUCCESS;
    }
}
