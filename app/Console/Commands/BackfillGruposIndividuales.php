<?php

namespace App\Console\Commands;

use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Agenda\GrupoIndividualService;
use Illuminate\Console\Command;

/**
 * Crea los grupos individuales que falten en actividades ya existentes.
 *
 * Hasta ahora esto se hacía al vuelo cada vez que alguien abría la pantalla de
 * evaluación o la de una actividad, lo que metía un bucle de consultas en el
 * camino de lectura. La creación pasó a los puntos de escritura; este comando
 * cubre lo que quedó de antes.
 */
class BackfillGruposIndividuales extends Command
{
    protected $signature = 'agenda:backfill-grupos-individuales
                            {--curso= : Limitar a un id_curso concreto}
                            {--dry-run : Sólo informar de lo que falta, sin crear nada}';

    protected $description = 'Crea los grupos individuales faltantes de las actividades individuales';

    public function handle(GrupoIndividualService $servicio): int
    {
        $cursos = Curso::query()
            ->when($this->option('curso'), fn ($q, $id) => $q->where('id_curso', $id))
            ->whereNull('fecha_eliminacion')
            ->get(['id_curso', 'nombre', 'cod_curso']);

        if ($cursos->isEmpty()) {
            $this->warn('No hay cursos que revisar.');

            return self::SUCCESS;
        }

        $esSimulacion = (bool) $this->option('dry-run');
        $totalActividades = 0;

        foreach ($cursos as $curso) {
            $actividades = Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
                ->where('es_grupal', false)
                ->get();

            if ($actividades->isEmpty()) {
                continue;
            }

            foreach ($actividades as $actividad) {
                $totalActividades++;

                if ($esSimulacion) {
                    continue;
                }

                $servicio->asegurarGruposDelCurso($curso, $actividad);
            }

            $this->line("  {$curso->cod_curso} — {$actividades->count()} actividad(es) individual(es)");
        }

        $verbo = $esSimulacion ? 'Se revisarían' : 'Revisadas';
        $this->info("{$verbo} {$totalActividades} actividad(es) individual(es) en {$cursos->count()} curso(s).");

        return self::SUCCESS;
    }
}
