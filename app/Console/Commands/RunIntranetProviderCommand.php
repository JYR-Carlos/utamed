<?php

namespace App\Console\Commands;

use App\DTOs\External\AlumnoIntranetData;
use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\InscripcionData;
use App\Enums\External\TipoAsignatura;
use App\Models\External\VwInscripcion;
use Illuminate\Console\Command;

class RunIntranetProviderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intranet:provider
        {action? : Acción a ejecutar: [cur-codigos | inscripciones | alumno | muestra]}
        {--semestre= : Semestre académico (1 o 2)}
        {--agno= : Año académico (ej. 2024)}
        {--plan= : Código/Año del plan de estudios en Oracle (PLAN_ANO, ej. 2020)}
        {--asig= : Código de asignatura en Oracle (ASIG_CODIGO, ej. IE124)}
        {--tipo= : Filtro por tipo de asignatura opcional (C=Cátedra, T=Taller, L=Laboratorio)}
        {--grupo= : Grupo/Paralelo de la asignatura opcional (CURSO_GRUPO_ASIG, ej. A, B)}
        {--cur-codigo=* : Uno o más CUR_CODIGO para consultar actas de inscripción}
        {--rut= : RUT del alumno a consultar sin puntos ni DV (ALUM_RUT, ej. 12345678)}
        {--sample : Usar un registro de muestra real desde Oracle como ejemplo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta y prueba el IntranetViewConnectionProvider (OracleDataService) mostrando una descripción detallada de los parámetros.';

    public function handle(): int
    {
        $this->displayBanner();

        $action = $this->argument('action');

        if ($this->option('sample')) {
            $action = 'muestra';
        }

        if (!$action) {
            $action = $this->choice(
                'Seleccione el método del Intranet Provider que desea ejecutar:',
                [
                    'cur-codigos'   => '1. traer_cur_codigos (Obtener CUR_CODIGO de componentes por asignatura)',
                    'inscripciones' => '2. traer_ins_id (Obtener inscripciones de alumnos por CUR_CODIGO)',
                    'alumno'        => '3. traer_alumno (Obtener datos personales de un alumno por RUT)',
                    'muestra'       => '4. muestra (Ejecutar prueba completa con datos reales de muestra)',
                ],
                'cur-codigos'
            );
        }

        return match (strtolower($action)) {
            'cur-codigos', 'componentes', '1' => $this->handleCurCodigos(),
            'inscripciones', 'ins', '2'        => $this->handleInscripciones(),
            'alumno', 'estudiante', '3'       => $this->handleAlumno(),
            'muestra', 'sample', '4'          => $this->handleSample(),
            default => $this->handleUnknownAction($action),
        };
    }

    /**
     * Muestra el encabezado del comando y el glosario de parámetros.
     */
    protected function displayBanner(): void
    {
        $this->line('');
        $this->info('╔═══════════════════════════════════════════════════════════════════════════╗');
        $this->info('║             INTRANET PROVIDER (OracleDataService) - CLI                   ║');
        $this->info('╚═══════════════════════════════════════════════════════════════════════════╝');
        $this->line('Este comando interactúa con el proveedor de vistas Oracle (IntranetViewConnectionProvider).');
        $this->line('');
    }

    /**
     * Imprime una tabla descriptiva con los parámetros que se están llenando.
     *
     * @param array<string, array{desc: string, regla: string, val: mixed}> $parametros
     */
    protected function printParametrosTable(string $metodo, array $parametros): void
    {
        $this->line('');
        $this->comment("► Parámetros configurados para [{$metodo}]:");

        $rows = [];
        foreach ($parametros as $nombre => $info) {
            $valorDisplay = is_array($info['val'])
                ? implode(', ', $info['val'])
                : (is_null($info['val']) || $info['val'] === '' ? '<vacío / opcional>' : (string)$info['val']);

            $rows[] = [
                'Parámetro'       => $nombre,
                'Descripción'     => $info['desc'],
                'Tipo / Regla'    => $info['regla'],
                'Valor a Enviar'  => $valorDisplay,
            ];
        }

        $this->table(['Parámetro', 'Descripción', 'Tipo / Regla', 'Valor a Enviar'], $rows);
        $this->line('');
    }

    /**
     * 1. traer_cur_codigos: Obtiene CUR_CODIGO de las componentes de una asignatura.
     */
    protected function handleCurCodigos(): int
    {
        $semestre = $this->option('semestre') !== null ? (int)$this->option('semestre') : null;
        $agno = $this->option('agno') !== null ? (int)$this->option('agno') : null;
        $planCod = $this->option('plan') !== null ? (int)$this->option('plan') : null;
        $asigCodigo = $this->option('asig') ? trim((string)$this->option('asig')) : null;
        $tipoAsig = $this->option('tipo') ? strtoupper(trim((string)$this->option('tipo'))) : null;
        $grupoAsig = $this->option('grupo') ? strtoupper(trim((string)$this->option('grupo'))) : null;

        // Si faltan parámetros requeridos, pedirlos interactivamente con descripciones claras
        if ($semestre === null) {
            $semestre = (int)$this->ask('Semestre académico [semestre] (1 o 2)', '1');
        }
        if ($agno === null) {
            $agno = (int)$this->ask('Año académico [agno] (ej. 2024)', (string)now()->year);
        }
        if ($planCod === null) {
            $planCod = (int)$this->ask('Código/Año de Plan de Estudios en Oracle [planCod] (ej. 2020)', '2020');
        }
        if (empty($asigCodigo)) {
            $asigCodigo = trim((string)$this->ask('Código de Asignatura en Oracle [asigCodigo] (ej. IE124, EN156)'));
        }

        $this->printParametrosTable('OracleDataService::traer_cur_codigos', [
            'semestre'   => ['desc' => 'Semestre de la asignatura (1 o 2)', 'regla' => 'int (1 dígito)', 'val' => $semestre],
            'agno'       => ['desc' => 'Año académico lectivo', 'regla' => 'int (4 dígitos)', 'val' => $agno],
            'planCod'    => ['desc' => 'Código de plan de estudios (PLAN_ANO)', 'regla' => 'int (4 dígitos)', 'val' => $planCod],
            'asigCodigo' => ['desc' => 'Código oficial de asignatura (ASIG_CODIGO)', 'regla' => 'string (máx 10)', 'val' => $asigCodigo],
            'tipoAsig'   => ['desc' => 'Filtro de tipo (C=Cátedra, T=Taller, L=Laboratorio)', 'regla' => 'string/enum (opcional)', 'val' => $tipoAsig],
            'grupoAsig'  => ['desc' => 'Grupo o paralelo (CURSO_GRUPO_ASIG, ej. A, B)', 'regla' => 'string (máx 2, opcional)', 'val' => $grupoAsig],
        ]);

        try {
            $tipoEnum = null;
            if ($tipoAsig) {
                $tipoEnum = TipoAsignatura::tryFrom($tipoAsig) ?? $tipoAsig;
            }

            $oracleService = app('OracleDataService');
            $startTime = microtime(true);

            /** @var \Illuminate\Support\Collection<int, ComponenteCursoData> $componentes */
            $componentes = $oracleService->traer_cur_codigos(
                semestre: $semestre,
                agno: $agno,
                planCod: $planCod,
                asigCodigo: $asigCodigo,
                tipoAsig: $tipoEnum,
                grupoAsig: $grupoAsig
            );

            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            if ($componentes->isEmpty()) {
                $this->warn("⚠ No se encontraron componentes en Oracle para los parámetros ingresados ({$elapsedMs} ms).");
                return Command::SUCCESS;
            }

            $this->info("✔ Se encontraron {$componentes->count()} componente(s) en Oracle ({$elapsedMs} ms):");

            $rows = $componentes->map(function (ComponenteCursoData $c) {
                $tipoLabel = match ($c->curso_tipo_asig->value) {
                    'C' => 'C (Cátedra)',
                    'T' => 'T (Taller)',
                    'L' => 'L (Laboratorio)',
                    default => $c->curso_tipo_asig->value,
                };

                return [
                    'CUR_CODIGO'       => $c->cur_codigo,
                    'Tipo Componente'  => $tipoLabel,
                    'Grupo / Paralelo' => $c->curso_grupo_asig ?: '(Sin grupo)',
                ];
            })->toArray();

            $this->table(['CUR_CODIGO', 'Tipo Componente', 'Grupo / Paralelo'], $rows);

            // Tip para continuar con las inscripciones
            $curCodigosString = $componentes->pluck('cur_codigo')->implode(' --cur-codigo=');
            $this->line('');
            $this->comment("💡 Para consultar inscripciones de estos códigos, puedes ejecutar:");
            $this->line("   php artisan intranet:provider inscripciones --cur-codigo={$curCodigosString}");

        } catch (\InvalidArgumentException $e) {
            $this->error("❌ Error de validación de parámetros: " . $e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->error("❌ Error al consultar Oracle: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 2. traer_ins_id: Obtiene inscripciones de alumnos asociadas a uno o más CUR_CODIGO.
     */
    protected function handleInscripciones(): int
    {
        $curCodigos = (array)$this->option('cur-codigo');

        if (empty($curCodigos)) {
            $input = $this->ask('Ingrese uno o más CUR_CODIGO separados por comas o espacios [curCodigos]');
            if ($input) {
                $curCodigos = preg_split('/[\s,]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        }

        if (empty($curCodigos)) {
            $this->error('Debe ingresar al menos un CUR_CODIGO.');
            return Command::FAILURE;
        }

        $this->printParametrosTable('OracleDataService::traer_ins_id', [
            'curCodigos' => [
                'desc'  => 'Lista de identificadores de acta/componente en Oracle (CUR_CODIGO)',
                'regla' => 'iterable<int> (números de hasta 12 dígitos)',
                'val'   => $curCodigos,
            ],
        ]);

        try {
            $oracleService = app('OracleDataService');
            $startTime = microtime(true);

            /** @var \Illuminate\Support\Collection<int, InscripcionData> $inscripciones */
            $inscripciones = $oracleService->traer_ins_id($curCodigos);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            if ($inscripciones->isEmpty()) {
                $this->warn("⚠ No se encontraron inscripciones para los CUR_CODIGO indicados ({$elapsedMs} ms).");
                return Command::SUCCESS;
            }

            $this->info("✔ Se encontraron {$inscripciones->count()} inscripción(es) en Oracle ({$elapsedMs} ms):");

            $rows = $inscripciones->take(30)->map(function (InscripcionData $i) {
                return [
                    'INS_ID'   => $i->ins_id,
                    'ALUM_RUT' => $i->alum_rut,
                ];
            })->toArray();

            $this->table(['INS_ID (Cód. Inscripción)', 'ALUM_RUT (RUT Alumno)'], $rows);

            if ($inscripciones->count() > 30) {
                $restantes = $inscripciones->count() - 30;
                $this->comment("... y {$restantes} inscripción(es) adicionales más.");
            }

            // Tip para consultar datos de un alumno
            $primerRut = $inscripciones->first()?->alum_rut;
            if ($primerRut) {
                $this->line('');
                $this->comment("💡 Para consultar los datos del primer alumno encontrado, puedes ejecutar:");
                $this->line("   php artisan intranet:provider alumno --rut={$primerRut}");
            }

        } catch (\InvalidArgumentException $e) {
            $this->error("❌ Error de validación de parámetros: " . $e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->error("❌ Error al consultar Oracle: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 3. traer_alumno: Obtiene datos personales de un alumno desde Oracle por su RUT.
     */
    protected function handleAlumno(): int
    {
        $rutInput = $this->option('rut');

        if ($rutInput === null || $rutInput === '') {
            $rutInput = $this->ask('Ingrese el RUT del alumno sin puntos ni dígito verificador [alumRut] (ej. 12345678)');
        }

        // Limpiar el RUT en caso de que venga con puntos o guión
        $cleanRut = preg_replace('/[^0-9]/', '', (string)$rutInput);
        $alumRut = (int)$cleanRut;

        if ($alumRut <= 0) {
            $this->error('Debe ingresar un RUT numérico válido.');
            return Command::FAILURE;
        }

        $this->printParametrosTable('OracleDataService::traer_alumno', [
            'alumRut' => [
                'desc'  => 'RUT numérico del alumno sin puntos ni dígito verificador (ALUM_RUT)',
                'regla' => 'int (hasta 9 dígitos)',
                'val'   => $alumRut,
            ],
        ]);

        try {
            $oracleService = app('OracleDataService');
            $startTime = microtime(true);

            /** @var AlumnoIntranetData|null $alumno */
            $alumno = $oracleService->traer_alumno($alumRut);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            if (!$alumno) {
                $this->warn("⚠ No se encontró ningún alumno con el RUT {$alumRut} en Oracle ({$elapsedMs} ms).");
                return Command::SUCCESS;
            }

            $this->info("✔ Alumno encontrado exitosamente ({$elapsedMs} ms):");

            $rutCompleto = $alumno->alum_rut . '-' . $alumno->alum_digito;
            $nombreCompleto = trim("{$alumno->alum_nombre} {$alumno->alum_apellido_pat} " . ($alumno->alum_apellido_mat ?? ''));

            $this->table(
                ['Campo', 'Valor en Oracle'],
                [
                    ['RUT Formateado', $rutCompleto],
                    ['ALUM_RUT (Numérico)', (string)$alumno->alum_rut],
                    ['ALUM_DIGITO (DV)', (string)$alumno->alum_digito],
                    ['ALUM_NOMBRE', $alumno->alum_nombre],
                    ['ALUM_APELLIDO_PAT', $alumno->alum_apellido_pat],
                    ['ALUM_APELLIDO_MAT', $alumno->alum_apellido_mat ?? '(No registrado)'],
                    ['Nombre Completo', $nombreCompleto],
                ]
            );

        } catch (\InvalidArgumentException $e) {
            $this->error("❌ Error de validación de parámetros: " . $e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->error("❌ Error al consultar Oracle: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 4. muestra: Carga una muestra real desde Oracle y ejecuta los 3 métodos en secuencia.
     */
    protected function handleSample(): int
    {
        $this->info('Consultando registro real de muestra desde Oracle...');

        try {
            $sample = VwInscripcion::with(['alumno', 'carreraCurso'])->first();

            if (!$sample || !$sample->carreraCurso) {
                $this->warn('No se encontró ningún registro de muestra en las vistas de Oracle.');
                return Command::FAILURE;
            }

            $curso = $sample->carreraCurso;
            $semestre = (int)$curso->CURSO_SEMESTRE_ASIG;
            $agno = (int)$curso->CURSO_ANO;
            $planCod = (int)$curso->PLAN_ANO;
            $asigCodigo = trim($curso->ASIG_CODIGO);
            $curCodigo = (int)$sample->CUR_CODIGO;
            $rut = (int)$sample->ALUM_RUT;

            $this->info('✔ Registro de muestra obtenido exitosamente.');
            $this->line("• Asignatura: {$asigCodigo} | Año: {$agno} | Semestre: {$semestre} | Plan: {$planCod}");
            $this->line("• CUR_CODIGO: {$curCodigo} | RUT Alumno: {$rut}");

            // 1. Probar traer_cur_codigos
            $this->line('');
            $this->info('--- 1. Ejecutando traer_cur_codigos() con datos de muestra ---');
            $this->printParametrosTable('OracleDataService::traer_cur_codigos', [
                'semestre'   => ['desc' => 'Semestre de la asignatura', 'regla' => 'int (1 o 2)', 'val' => $semestre],
                'agno'       => ['desc' => 'Año académico', 'regla' => 'int (4 dígitos)', 'val' => $agno],
                'planCod'    => ['desc' => 'Código de plan (PLAN_ANO)', 'regla' => 'int (4 dígitos)', 'val' => $planCod],
                'asigCodigo' => ['desc' => 'Código de asignatura (ASIG_CODIGO)', 'regla' => 'string', 'val' => $asigCodigo],
            ]);

            $oracleService = app('OracleDataService');
            $componentes = $oracleService->traer_cur_codigos(
                semestre: $semestre,
                agno: $agno,
                planCod: $planCod,
                asigCodigo: $asigCodigo
            );

            $this->table(
                ['CUR_CODIGO', 'Tipo Asignatura', 'Grupo'],
                $componentes->map(fn($c) => [$c->cur_codigo, $c->curso_tipo_asig->value, $c->curso_grupo_asig])->toArray()
            );

            // 2. Probar traer_ins_id
            $this->line('');
            $this->info('--- 2. Ejecutando traer_ins_id() con CUR_CODIGO de muestra ---');
            $this->printParametrosTable('OracleDataService::traer_ins_id', [
                'curCodigos' => ['desc' => 'CUR_CODIGO seleccionado', 'regla' => 'iterable<int>', 'val' => [$curCodigo]],
            ]);

            $inscripciones = $oracleService->traer_ins_id([$curCodigo]);
            $this->table(
                ['INS_ID', 'ALUM_RUT'],
                $inscripciones->take(10)->map(fn($i) => [$i->ins_id, $i->alum_rut])->toArray()
            );
            $this->line("Total inscripciones en CUR_CODIGO #{$curCodigo}: " . $inscripciones->count());

            // 3. Probar traer_alumno
            $this->line('');
            $this->info('--- 3. Ejecutando traer_alumno() con RUT de muestra ---');
            $this->printParametrosTable('OracleDataService::traer_alumno', [
                'alumRut' => ['desc' => 'RUT del alumno de muestra', 'regla' => 'int', 'val' => $rut],
            ]);

            $alumno = $oracleService->traer_alumno($rut);
            if ($alumno) {
                $this->table(
                    ['RUT', 'Nombre', 'Paterno', 'Materno'],
                    [[$alumno->alum_rut . '-' . $alumno->alum_digito, $alumno->alum_nombre, $alumno->alum_apellido_pat, $alumno->alum_apellido_mat ?? '']]
                );
            }

        } catch (\Throwable $e) {
            $this->error("Error en la ejecución de muestra: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function handleUnknownAction(string $action): int
    {
        $this->error("Acción '{$action}' no reconocida. Opciones disponibles: cur-codigos, inscripciones, alumno, muestra.");
        return Command::FAILURE;
    }
}
