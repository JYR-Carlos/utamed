<?php

namespace App\Console\Commands;

use App\DTOs\External\ComponenteDetectada;
use App\DTOs\External\ResultadoInscripcionAutomatica;
use App\DTOs\External\ResultadoPreviewComponentes;
use App\DTOs\External\ResultadoSincronizacionComponentes;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Services\IntranetService;
use App\Support\LetraGrupo;
use Illuminate\Console\Command;

class RunIntranetServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intranet:service
        {action? : Acción a ejecutar: [preview | sync | inscribir | auto]}
        {--curso= : Código del curso en UTAMED (cod_curso)}
        {--asig= : ID o Código de asignatura en UTAMED (ej. IE124 o ID numérico)}
        {--plan= : ID o Año de plan de estudios en UTAMED (ej. 2020 o ID numérico)}
        {--agno= : Año académico (ej. 2024)}
        {--semestre= : Semestre académico (1 o 2)}
        {--grupo= : Letra del grupo/paralelo (ej. A, B, C)}
        {--tipos=* : IDs de tipos de componente a sincronizar (ej. --tipos=1 --tipos=2)}
        {--sync : Ejecutar creación de componentes en base de datos}
        {--inscribir : Ejecutar inscripción de alumnos en base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta y prueba los métodos de negocio de IntranetService (preview, sincronización e inscripción) mostrando una descripción detallada de los parámetros.';

    public function handle(IntranetService $intranetService): int
    {
        $this->displayBanner();

        $action = $this->argument('action');

        if (!$action) {
            $action = $this->choice(
                'Seleccione el flujo de IntranetService que desea ejecutar:',
                [
                    'preview'   => '1. preview (Previsualizar componentes desde parámetros o curso sin tocar BD)',
                    'sync'      => '2. sync (Sincronizar/crear componentes de un curso en UTAMED)',
                    'inscribir' => '3. inscribir (Inscribir automáticamente alumnos desde Oracle a un curso)',
                    'auto'      => '4. auto (Flujo completo interactivo: Preview -> Sync -> Inscribir)',
                ],
                'preview'
            );
        }

        return match (strtolower($action)) {
            'preview', 'previsualizar', '1' => $this->handlePreview($intranetService),
            'sync', 'sincronizar', '2'      => $this->handleSync($intranetService),
            'inscribir', 'inscripcion', '3' => $this->handleInscribir($intranetService),
            'auto', 'completo', '4'         => $this->handleAuto($intranetService),
            default => $this->handleUnknownAction($action),
        };
    }

    /**
     * Muestra el banner principal del comando.
     */
    protected function displayBanner(): void
    {
        $this->line('');
        $this->info('╔═══════════════════════════════════════════════════════════════════════════╗');
        $this->info('║                  INTRANET SERVICE (Business Logic) - CLI                  ║');
        $this->info('╚═══════════════════════════════════════════════════════════════════════════╝');
        $this->line('Este comando ejecuta la lógica de negocio de UTAMED para resolución de componentes e inscripciones.');
        $this->line('');
    }

    /**
     * Imprime una tabla descriptiva con los parámetros que se están configurando.
     *
     * @param array<string, array{desc: string, rol: string, val: mixed}> $parametros
     */
    protected function printParametrosTable(string $metodo, array $parametros): void
    {
        $this->line('');
        $this->comment("► Parámetros configurados para [{$metodo}]:");

        $rows = [];
        foreach ($parametros as $nombre => $info) {
            $valorDisplay = is_array($info['val'])
                ? implode(', ', $info['val'])
                : (is_null($info['val']) || $info['val'] === '' ? '<vacío / derivado>' : (string)$info['val']);

            $rows[] = [
                'Parámetro'       => $nombre,
                'Descripción'     => $info['desc'],
                'Rol en el Flujo' => $info['rol'],
                'Valor Asignado'  => $valorDisplay,
            ];
        }

        $this->table(['Parámetro', 'Descripción', 'Rol en el Flujo', 'Valor Asignado'], $rows);
        $this->line('');
    }

    /**
     * 1. PREVIEW: Previsualizar componentes (Mirar antes de tocar).
     */
    protected function handlePreview(IntranetService $intranetService): int
    {
        $cursoInput = $this->option('curso');
        $curso = null;

        if ($cursoInput) {
            $curso = Curso::with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera', 'componentes.tipoComponente'])
                ->where('cod_curso', $cursoInput)
                ->first();

            if (!$curso && is_numeric($cursoInput)) {
                $curso = Curso::with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera', 'componentes.tipoComponente'])
                    ->find((int)$cursoInput);
            }

            if (!$curso) {
                $this->error("No se encontró el curso con código '{$cursoInput}' en UTAMED.");
                return Command::FAILURE;
            }
        }

        if ($curso) {
            $asignacion = $curso->asignacionPlan;
            if (!$asignacion) {
                $this->error("El curso #{$curso->id_curso} no tiene una asignación de plan vinculada.");
                return Command::FAILURE;
            }

            $idAsignatura = $asignacion->id_asignatura;
            $idPlan = $asignacion->id_plan;
            $agno = $curso->agno_real ?? (int)now()->year;
            $semestre = $curso->semestre_real ?? 1;
            $letraGrupo = $curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo);

            $this->info("Usando datos del curso seleccionado: #{$curso->id_curso} - {$curso->nombre}");
        } else {
            $asigInput = $this->option('asig');
            if (!$asigInput) {
                $asigInput = $this->ask('Código o ID de Asignatura [asig] (ej. IE124)');
            }

            $planInput = $this->option('plan');
            if (!$planInput) {
                $planInput = $this->ask('ID o Año del Plan de Estudios [plan] (ej. 2020)', '2020');
            }

            $agno = $this->option('agno') !== null
                ? (int)$this->option('agno')
                : (int)$this->ask('Año académico [agno] (ej. 2024)', (string)now()->year);

            $semestre = $this->option('semestre') !== null
                ? (int)$this->option('semestre')
                : (int)$this->ask('Semestre académico [semestre] (1 o 2)', '1');

            $letraGrupo = $this->option('grupo')
                ? strtoupper(trim((string)$this->option('grupo')))
                : strtoupper(trim((string)$this->ask('Letra del grupo / paralelo [grupo] (A, B, C...) [Opcional]', 'A')));

            $asignatura = is_numeric($asigInput)
                ? Asignatura::find((int)$asigInput)
                : Asignatura::where('cod_asignatura', trim((string)$asigInput))->first();

            $plan = is_numeric($planInput)
                ? Plan::where('id_plan', (int)$planInput)->orWhere('agno_plan', (int)$planInput)->first()
                : null;

            $asigCodigo = trim((string)$asigInput);
            $planCod = is_numeric($planInput) ? (int)$planInput : ($plan?->agno_plan ?? 2020);

            if ($asignatura && $plan) {
                $idAsignatura = $asignatura->id_asignatura;
                $idPlan = $plan->id_plan;
            } else {
                $this->comment("ℹ La asignatura '{$asigCodigo}' o plan '{$planInput}' no están en la BD local de UTAMED.");
                $this->comment("  Consultando directamente desde la Intranet (Oracle)...");
                $idAsignatura = null;
                $idPlan = null;
            }
        }

        $this->printParametrosTable('IntranetService::previsualizarComponentes', [
            'idAsignatura / asigCodigo' => ['desc' => 'Asignatura (ID local o Código)', 'rol' => 'Busca horas planificadas y cod_asignatura', 'val' => $idAsignatura ?? $asigCodigo],
            'idPlan / planCod'          => ['desc' => 'Plan de estudios (ID local o Código)', 'rol' => 'Resuelve el código/año de plan (PLAN_ANO)', 'val' => $idPlan ?? $planCod],
            'agno'                      => ['desc' => 'Año lectivo del periodo', 'rol' => 'Filtra la oferta en Oracle (CURSO_ANO)', 'val' => $agno],
            'semestre'                  => ['desc' => 'Semestre académico (1 o 2)', 'rol' => 'Filtra la oferta en Oracle (CURSO_SEMESTRE_ASIG)', 'val' => $semestre],
            'letraGrupo'                => ['desc' => 'Paralelo / Letra del grupo', 'rol' => 'Filtra el grupo en Oracle (CURSO_GRUPO_ASIG)', 'val' => $letraGrupo],
        ]);

        try {
            $startTime = microtime(true);

            if ($idAsignatura !== null && $idPlan !== null) {
                $preview = $intranetService->previsualizarComponentes($idAsignatura, $idPlan, $agno, $semestre, $letraGrupo);
            } else {
                // Consulta directa a Oracle cuando no está registrado en PostgreSQL local
                $oracleService = app('OracleDataService');
                $componentesIntranet = $oracleService->traer_cur_codigos(
                    semestre: $semestre,
                    agno: $agno,
                    planCod: $planCod,
                    asigCodigo: $asigCodigo,
                    grupoAsig: $letraGrupo !== '' ? $letraGrupo : null
                );

                $componentes = [];
                foreach ($componentesIntranet as $comp) {
                    $tipoNombre = match ($comp->curso_tipo_asig->value) {
                        'C' => 'Cátedra',
                        'T' => 'Taller',
                        'L' => 'Laboratorio',
                        default => $comp->curso_tipo_asig->value,
                    };

                    $tipoModel = TipoComponente::all()->first(fn($t) => strtoupper(trim($t->tipo)) === strtoupper(trim($tipoNombre)));
                    $idTipo = $tipoModel?->id_tipo_componente ?? 1;

                    $componentes[] = new ComponenteDetectada(
                        id_tipo_componente: $idTipo,
                        tipo: $tipoNombre,
                        origen: 'INTRANET',
                        cur_codigo: $comp->cur_codigo
                    );
                }

                $preview = new ResultadoPreviewComponentes(
                    origen: $componentes !== [] ? 'INTRANET' : 'PLAN',
                    componentes: $componentes,
                    id_tipo_componente_principal: !empty($componentes) ? $componentes[0]->id_tipo_componente : null,
                    advertencias: empty($componentes) ? ['No se encontraron componentes en Oracle para estos parámetros.'] : []
                );
            }

            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("✔ Previsualización completada en {$elapsedMs} ms.");
            $this->line("• Origen detectado: <fg=cyan;options=bold>{$preview->origen}</>");
            $this->line("• ID Tipo Componente Principal recomendado: " . ($preview->id_tipo_componente_principal ?? '(Ninguno)'));

            $this->line('');
            $this->comment('Componentes detectadas:');

            if (empty($preview->componentes)) {
                $this->warn('  No se detectaron componentes para esta configuración.');
            } else {
                $rows = array_map(function (ComponenteDetectada $c) use ($preview) {
                    $esPrincipal = ($c->id_tipo_componente === $preview->id_tipo_componente_principal) ? '⭐ Sí (Principal)' : 'No';
                    return [
                        'ID Tipo'      => $c->id_tipo_componente,
                        'Tipo'         => $c->tipo,
                        'Origen'       => $c->origen,
                        'CUR_CODIGO'   => $c->cur_codigo ?? '(Derivado de Plan)',
                        'Es Principal' => $esPrincipal,
                    ];
                }, $preview->componentes);

                $this->table(['ID Tipo', 'Tipo', 'Origen', 'CUR_CODIGO (Oracle)', 'Principal'], $rows);
            }

            if (!empty($preview->advertencias)) {
                $this->line('');
                $this->warn('Advertencias reportadas por el servicio:');
                foreach ($preview->advertencias as $adv) {
                    $this->line("  ⚠ {$adv}");
                }
            }

        } catch (\Throwable $e) {
            $this->error("❌ Error durante la previsualización: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 2. SYNC: Sincronizar componentes en base de datos.
     */
    protected function handleSync(IntranetService $intranetService): int
    {
        $curso = $this->resolveCurso();
        if (!$curso) {
            return Command::FAILURE;
        }

        $tiposOption = (array)$this->option('tipos');
        $inscribir = (bool)$this->option('inscribir');

        if (empty($tiposOption)) {
            // Ejecutar preview para sugerir componentes detectadas
            $asignacion = $curso->asignacionPlan;
            $letra = $curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo);
            $preview = $intranetService->previsualizarComponentes(
                $asignacion->id_asignatura,
                $asignacion->id_plan,
                $curso->agno_real ?? (int)now()->year,
                $curso->semestre_real ?? 1,
                $letra
            );

            $idsDetectados = array_map(fn(ComponenteDetectada $c) => $c->id_tipo_componente, $preview->componentes);

            $this->info("Componentes detectadas automáticamente para el curso: " . implode(', ', $idsDetectados));
            if ($this->confirm("¿Deseas sincronizar estos tipos de componente (" . implode(', ', array_map(fn($c) => $c->tipo, $preview->componentes)) . ")?", true)) {
                $tiposOption = $idsDetectados;
            } else {
                $input = $this->ask('Ingresa los IDs de TipoComponente separados por comas (ej. 1, 2)');
                $tiposOption = array_filter(array_map('intval', explode(',', (string)$input)));
            }
        }

        if (empty($tiposOption)) {
            $this->error('No se especificaron tipos de componente a sincronizar.');
            return Command::FAILURE;
        }

        $this->printParametrosTable('IntranetService::sincronizarComponentes', [
            'curso'                        => ['desc' => "Curso #{$curso->id_curso} ({$curso->nombre})", 'rol' => 'Curso destino en PostgreSQL', 'val' => $curso->id_curso],
            'idsTipoComponenteAceptados'   => ['desc' => 'Lista de IDs de TipoComponente a crear', 'rol' => 'Crea registros en tabla componente', 'val' => $tiposOption],
            'inscribirAlumnos'             => ['desc' => 'Flag booleana para ejecutar inscripción automática', 'rol' => 'Inscribe alumnos en el mismo paso si es true', 'val' => $inscribir ? 'true' : 'false'],
        ]);

        try {
            $startTime = microtime(true);
            $resultado = $intranetService->sincronizarComponentes($curso, $tiposOption, $inscribir);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("✔ Sincronización finalizada en {$elapsedMs} ms.");
            $this->line("• Origen de datos: <fg=cyan;options=bold>{$resultado->origen}</>");
            $this->line("• Componentes Creadas: " . (empty($resultado->componentes_creadas) ? '(Ninguna nueva)' : implode(', ', $resultado->componentes_creadas)));
            $this->line("• Componentes Ya Existentes: " . (empty($resultado->componentes_existentes) ? '(Ninguna)' : implode(', ', $resultado->componentes_existentes)));

            if (!empty($resultado->advertencias)) {
                $this->line('');
                $this->warn('Advertencias:');
                foreach ($resultado->advertencias as $adv) {
                    $this->line("  ⚠ {$adv}");
                }
            }

        } catch (\Throwable $e) {
            $this->error("❌ Error durante la sincronización: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 3. INSCRIBIR: Inscripción automática de estudiantes desde Oracle.
     */
    protected function handleInscribir(IntranetService $intranetService): int
    {
        $curso = $this->resolveCurso();
        if (!$curso) {
            return Command::FAILURE;
        }

        $this->printParametrosTable('IntranetService::inscribirAutomaticamente', [
            'curso' => [
                'desc' => "Curso #{$curso->id_curso} - {$curso->nombre}",
                'rol'  => 'Curso en PostgreSQL con componentes ya configuradas',
                'val'  => $curso->id_curso,
            ],
        ]);

        if (!$this->confirm("¿Confirma la inscripción automática de alumnos desde Oracle al curso #{$curso->id_curso}?", true)) {
            $this->warn('Operación cancelada por el usuario.');
            return Command::SUCCESS;
        }

        try {
            $startTime = microtime(true);
            $resultado = $intranetService->inscribirAutomaticamente($curso);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("✔ Proceso de inscripción automática finalizado en {$elapsedMs} ms.");

            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Total Registros Procesados', (string)$resultado->total_procesados],
                    ['Alumnos Creados en UTAMED', (string)$resultado->alumnos_creados],
                    ['Inscritos Exitosamente', (string)$resultado->inscritos_exitosamente],
                    ['Ya Inscritos Previamente', (string)$resultado->ya_inscritos],
                    ['Total Errores', (string)count($resultado->errores)],
                ]
            );

            if (!empty($resultado->componentes_procesadas)) {
                $this->line('');
                $this->comment('Detalle por Componente:');
                $this->table(
                    ['CUR_CODIGO', 'Tipo', 'Grupo', 'Inscritos'],
                    array_map(fn($c) => [
                        $c['cur_codigo'],
                        $c['tipo'],
                        $c['grupo'] ?? '(Sin grupo)',
                        $c['inscritos'],
                    ], $resultado->componentes_procesadas)
                );
            }

            if (!empty($resultado->advertencias)) {
                $this->line('');
                $this->warn('Advertencias:');
                foreach ($resultado->advertencias as $adv) {
                    $this->line("  ⚠ {$adv}");
                }
            }

            if (!empty($resultado->errores)) {
                $this->line('');
                $this->error('Errores encontrados al procesar alumnos:');
                $this->table(
                    ['RUT Alumno', 'Motivo del Error'],
                    array_map(fn($e) => [$e['rut'] ?? 'N/A', $e['motivo'] ?? 'Error desconocido'], $resultado->errores)
                );
            }

        } catch (\Throwable $e) {
            $this->error("❌ Error durante la inscripción automática: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 4. AUTO: Flujo guiado completo.
     */
    protected function handleAuto(IntranetService $intranetService): int
    {
        $this->info('--- Flujo Completo Guiado: Preview -> Sync -> Inscribir ---');

        $curso = $this->resolveCurso();
        if (!$curso) {
            return Command::FAILURE;
        }

        // 1. Preview
        $this->line('');
        $this->info('Paso 1: Previsualizar componentes...');
        $asignacion = $curso->asignacionPlan;
        $letra = $curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo);

        $preview = $intranetService->previsualizarComponentes(
            $asignacion->id_asignatura,
            $asignacion->id_plan,
            $curso->agno_real ?? (int)now()->year,
            $curso->semestre_real ?? 1,
            $letra
        );

        $this->line("• Origen detectado: {$preview->origen}");
        $idsDetectados = array_map(fn($c) => $c->id_tipo_componente, $preview->componentes);

        // 2. Sync + Inscribir
        if ($this->confirm("¿Deseas sincronizar las componentes detectadas e inscribir a los alumnos?", true)) {
            $this->line('');
            $this->info('Paso 2: Sincronizando componentes e inscribiendo alumnos...');
            $resultadoSync = $intranetService->sincronizarComponentes($curso, $idsDetectados, true);

            $this->info('✔ Sincronización e inscripción completadas.');
            $this->line('• Componentes creadas: ' . implode(', ', $resultadoSync->componentes_creadas ?: ['(Ninguna)']));
            $this->line('• Componentes existentes: ' . implode(', ', $resultadoSync->componentes_existentes ?: ['(Ninguna)']));
        }

        return Command::SUCCESS;
    }

    /**
     * Resuelve un modelo Curso desde la opción --curso o preguntando al usuario.
     */
    protected function resolveCurso(): ?Curso
    {
        $cursoInput = $this->option('curso');

        if (!$cursoInput) {
            $cursosRecientes = Curso::with(['asignacionPlan.asignatura', 'asignacionPlan.plan'])
                ->orderBy('id_curso', 'desc')
                ->take(5)
                ->get();

            if ($cursosRecientes->isNotEmpty()) {
                $this->line('Cursos recientes en UTAMED:');
                $choices = [];
                foreach ($cursosRecientes as $c) {
                    $asigCod = $c->asignacionPlan?->asignatura?->cod_asignatura ?? 'N/A';
                    $choices[$c->cod_curso] = "Código [{$c->cod_curso}] - {$c->nombre} (Asig: {$asigCod}, Año: {$c->agno_real}, Sem: {$c->semestre_real})";
                }
                $choices['manual'] = 'Ingresar código de curso manualmente';

                $seleccion = $this->choice('Seleccione un curso:', $choices, array_key_first($choices));

                if ($seleccion === 'manual') {
                    $cursoInput = trim((string) $this->ask('Ingrese el código del curso (cod_curso)'));
                } else {
                    $cursoInput = (string) $seleccion;
                }
            } else {
                $cursoInput = trim((string) $this->ask('Ingrese el código del curso (cod_curso)'));
            }
        }

        $curso = Curso::with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera', 'componentes.tipoComponente'])
            ->where('cod_curso', $cursoInput)
            ->first();

        if (!$curso && is_numeric($cursoInput)) {
            $curso = Curso::with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera', 'componentes.tipoComponente'])
                ->find((int) $cursoInput);
        }

        if (!$curso) {
            $this->error("No se encontró el curso con código '{$cursoInput}' en UTAMED.");
            return null;
        }

        return $curso;
    }

    protected function handleUnknownAction(string $action): int
    {
        $this->error("Acción '{$action}' no reconocida. Opciones disponibles: preview, sync, inscribir, auto.");
        return Command::FAILURE;
    }
}
