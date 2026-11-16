<?php

namespace App\Console\Commands;

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAlumnosIntranetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intranet:sync-alumnos
                            {--rut= : Filtrar y sincronizar un único alumno por su RUT}
                            {--sin-correo : Sincronizar únicamente aquellos alumnos que actualmente tengan email nulo o vacío}
                            {--limit= : Limitar la cantidad máxima de alumnos a procesar}
                            {--chunk=100 : Tamaño del chunk para procesamiento en lote}
                            {--dry-run : Ejecutar en modo simulación sin persistir cambios en la base de datos}
                            {--force : Omitir confirmación interactiva}';

    /**
     * Command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['intranet:sync-emails', 'alumnos:sync-intranet'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realiza un barrido por los estudiantes registrados en UTAMED y sincroniza sus datos personales y correo desde la Intranet (Oracle)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->output->title('UTAMED - Sincronización y Barrido de Alumnos desde Intranet');

        $filtroRut = $this->option('rut');
        $soloSinCorreo = (bool) $this->option('sin-correo');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('⚠ Modo SIMULACIÓN activado (--dry-run). No se persistirán cambios en la base de datos.');
        }

        // Construir la consulta base
        $query = Estudiante::query()
            ->with(['usuario', 'carrera'])
            ->orderBy('id_estudiante');

        if ($filtroRut) {
            $rutNumerico = (int) preg_replace('/[^0-9]/', '', (string) $filtroRut);
            $query->where(function ($q) use ($rutNumerico) {
                $q->where('rut', 'like', "{$rutNumerico}%")
                  ->orWhereHas('usuario', function ($u) use ($rutNumerico) {
                      $u->where('username', (string) $rutNumerico)
                        ->orWhere('rut', 'like', "{$rutNumerico}%");
                  });
            });
        }

        if ($soloSinCorreo) {
            $query->whereHas('usuario', function ($u) {
                $u->whereNull('email')->orWhere('email', '');
            });
        }

        $totalRegistros = $query->count();

        if ($limit && $limit < $totalRegistros) {
            $totalRegistros = $limit;
        }

        if ($totalRegistros === 0) {
            $this->info('✔ No se encontraron estudiantes que coincidan con los criterios seleccionados.');
            return self::SUCCESS;
        }

        $this->line("• Total de alumnos a evaluar: <fg=cyan;options=bold>{$totalRegistros}</>");
        if ($soloSinCorreo) {
            $this->line('• Filtro activo: Solo alumnos con email vacío o null.');
        }
        if ($filtroRut) {
            $this->line("• Filtro de RUT: {$filtroRut}");
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm('¿Desea iniciar la sincronización de alumnos desde la Intranet?', true)) {
                $this->warn('Operación cancelada por el usuario.');
                return self::SUCCESS;
            }
        }

        $oracleService = app('OracleDataService');

        $procesados = 0;
        $actualizados = 0;
        $correosNuevos = 0;
        $sinCambios = 0;
        $noEncontradosOracle = 0;
        $errores = 0;

        $bar = $this->output->createProgressBar($totalRegistros);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% (%elapsed:6s%/%estimated:-6s%) %message%');
        $bar->setMessage('Iniciando...');
        $bar->start();

        $carreraFallback = Carrera::first();

        // Procesamiento por chunks
        $queryChunk = clone $query;
        if ($limit) {
            $queryChunk->limit($limit);
        }

        $startTime = microtime(true);

        $queryChunk->chunk($chunkSize, function ($estudiantes) use (
            $oracleService,
            $dryRun,
            $carreraFallback,
            &$procesados,
            &$actualizados,
            &$correosNuevos,
            &$sinCambios,
            &$noEncontradosOracle,
            &$errores,
            $limit,
            $bar
        ) {
            foreach ($estudiantes as $estudiante) {
                if ($limit && $procesados >= $limit) {
                    return false;
                }

                $procesados++;

                /** @var Usuario|null $usuario */
                $usuario = $estudiante->usuario;
                $rutCuerpo = null;

                if ($usuario && !empty($usuario->username) && ctype_digit($usuario->username)) {
                    $rutCuerpo = (int) $usuario->username;
                } elseif (!empty($estudiante->rut)) {
                    $rutCuerpo = (int) preg_replace('/[^0-9]/', '', explode('-', $estudiante->rut)[0]);
                }

                if (!$rutCuerpo) {
                    $errores++;
                    $bar->setMessage("<fg=red>RUT inválido en ID {$estudiante->id_estudiante}</>");
                    $bar->advance();
                    continue;
                }

                $bar->setMessage("Consultando RUT {$rutCuerpo}...");

                try {
                    $alumnoData = $oracleService->traer_alumno($rutCuerpo);
                } catch (\Throwable $e) {
                    $errores++;
                    Log::warning("[intranet:sync-alumnos] Error al consultar RUT {$rutCuerpo} en Oracle: " . $e->getMessage());
                    $bar->setMessage("<fg=red>Error en RUT {$rutCuerpo}</>");
                    $bar->advance();
                    continue;
                }

                if (!$alumnoData) {
                    $noEncontradosOracle++;
                    $bar->setMessage("<fg=yellow>RUT {$rutCuerpo} no está en Oracle</>");
                    $bar->advance();
                    continue;
                }

                $carrera = $estudiante->carrera ?? $carreraFallback;
                $emailAnterior = $usuario?->email;

                if ($dryRun) {
                    $emailNuevo = $alumnoData->alum_email;
                    if ($emailNuevo && $emailNuevo !== $emailAnterior) {
                        $correosNuevos++;
                        $actualizados++;
                    } else {
                        $sinCambios++;
                    }
                } else {
                    try {
                        $estudianteSincronizado = Estudiante::createFromIntranet(
                            $alumnoData,
                            $carrera,
                            $estudiante->agno_ingreso
                        );

                        $emailActual = $estudianteSincronizado->usuario?->email;

                        if ($emailActual && $emailActual !== $emailAnterior) {
                            $correosNuevos++;
                        }

                        $actualizados++;
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::error("[intranet:sync-alumnos] Excepción al sincronizar RUT {$rutCuerpo}: " . $e->getMessage());
                    }
                }

                $bar->advance();
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $elapsedSeconds = round(microtime(true) - $startTime, 2);

        $this->table(
            ['Métrica de Sincronización', 'Cantidad'],
            [
                ['Alumnos procesados', (string) $procesados],
                ['Actualizados exitosamente', (string) $actualizados],
                ['Correos institucionales asignados / renovados', (string) $correosNuevos],
                ['Sin cambios detectados', (string) $sinCambios],
                ['No encontrados en Oracle (Intranet)', (string) $noEncontradosOracle],
                ['Errores / Excepciones', (string) $errores],
                ['Tiempo total de ejecución', "{$elapsedSeconds} s"],
            ]
        );

        if ($dryRun) {
            $this->info('✔ Simulación completada sin escribir en la base de datos.');
        } else {
            $this->info('✔ Proceso de sincronización y barrido finalizado con éxito.');
        }

        return self::SUCCESS;
    }
}
