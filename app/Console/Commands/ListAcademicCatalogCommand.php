<?php

namespace App\Console\Commands;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Plan;
use App\Models\External\VwCarreraCurso;
use Illuminate\Console\Command;

class ListAcademicCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intranet:catalogo
        {view? : Tipo de vista: [ambas | local | oracle | planes | asignaturas]}
        {--carrera= : Filtrar por nombre o ID de carrera}
        {--plan= : Filtrar por ID o Año de Plan (ej. 2026, 2020, 2011)}
        {--asig= : Buscar por código o nombre de asignatura (ej. DM050, DI021, IE124)}
        {--semestre= : Filtrar por semestre (1 o 2)}
        {--limit=20 : Cantidad máxima de registros a mostrar por sección}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta y busca planes de estudio y códigos de asignaturas simultáneamente en UTAMED (Local) y en Oracle (Intranet).';

    public function handle(): int
    {
        $this->displayBanner();

        $view = $this->argument('view') ?? 'ambas';

        return match (strtolower($view)) {
            'ambas', 'all', 'todo', 'resumen' => $this->handleAmbas(),
            'local', 'utamed'                 => $this->handleLocal(),
            'oracle', 'intranet'              => $this->handleOracle(),
            'planes'                          => $this->handlePlanes(),
            'asignaturas', 'asig'             => $this->handleAsignaturas(),
            default                           => $this->handleUnknownView($view),
        };
    }

    protected function displayBanner(): void
    {
        $this->line('');
        $this->info('╔═══════════════════════════════════════════════════════════════════════════╗');
        $this->info('║       CATÁLOGO ACADÉMICO SIMULTÁNEO (UTAMED Local + Oracle Intranet)      ║');
        $this->info('╚═══════════════════════════════════════════════════════════════════════════╝');
    }

    /**
     * Vista principal: Muestra resultados de ambas bases de datos.
     */
    protected function handleAmbas(): int
    {
        $this->line('');
        $this->comment('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->comment(' 1. BASE DE DATOS LOCAL (UTAMED - PostgreSQL)');
        $this->comment('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->handleLocal();

        $this->line('');
        $this->comment('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->comment(' 2. INTRANET EXTERNA (ORACLE - CARRERA_CURSO)');
        $this->comment('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->handleOracle();

        return Command::SUCCESS;
    }

    /**
     * Consulta planes y asignaturas locales en UTAMED.
     */
    protected function handleLocal(): int
    {
        $limit = (int)$this->option('limit') ?: 20;

        // 1. Planes en UTAMED
        $queryPlan = Plan::with('carrera');
        $planFilter = $this->option('plan');
        if ($planFilter && is_numeric($planFilter)) {
            $queryPlan->where(fn($q) => $q->where('id_plan', (int)$planFilter)->orWhere('agno_plan', (int)$planFilter));
        }

        $carreraFilter = $this->option('carrera');
        if ($carreraFilter) {
            $queryPlan->whereHas('carrera', function ($q) use ($carreraFilter) {
                is_numeric($carreraFilter) ? $q->where('id_carrera', (int)$carreraFilter) : $q->where('nombre', 'ILIKE', "%{$carreraFilter}%");
            });
        }

        $planes = $queryPlan->get();

        if ($planes->isNotEmpty()) {
            $this->info("► Planes de Estudio registrados en UTAMED ({$planes->count()}):");
            $rowsPlan = $planes->map(function (Plan $p) {
                $totalAsig = AsignacionPlan::where('id_plan', $p->id_plan)->whereNull('fecha_eliminacion')->count();
                return [
                    'ID Plan'       => $p->id_plan,
                    'Año Plan'      => $p->agno_plan ?? '(Sin año)',
                    'Versión'       => $p->version_plan ?? 1,
                    'Carrera'       => $p->carrera?->nombre ?? 'N/A',
                    'Asignaturas'   => $totalAsig,
                ];
            })->toArray();
            $this->table(['ID Plan', 'Año Plan', 'Versión', 'Carrera', 'Total Asignaturas'], $rowsPlan);
        }

        // 2. Asignaturas en UTAMED
        $queryAsig = AsignacionPlan::with(['asignatura', 'plan.carrera'])->whereNull('fecha_eliminacion');
        if ($planFilter && is_numeric($planFilter)) {
            $queryAsig->whereHas('plan', fn($q) => $q->where('id_plan', (int)$planFilter)->orWhere('agno_plan', (int)$planFilter));
        }
        $asigFilter = $this->option('asig');
        if ($asigFilter) {
            $queryAsig->whereHas('asignatura', fn($q) => $q->where('cod_asignatura', 'ILIKE', "%{$asigFilter}%")->orWhere('nombre', 'ILIKE', "%{$asigFilter}%"));
        }
        $semestreFilter = $this->option('semestre');
        if ($semestreFilter) {
            $queryAsig->where('semestre_planificado', (int)$semestreFilter);
        }

        $asignaciones = $queryAsig->take($limit)->get();

        if ($asignaciones->isNotEmpty()) {
            $this->line('');
            $this->info("► Asignaturas en UTAMED (Mostrando {$asignaciones->count()} de muestra):");
            $rowsAsig = $asignaciones->map(function (AsignacionPlan $ap) {
                $a = $ap->asignatura;
                $p = $ap->plan;
                return [
                    'ID Asig'        => $a?->id_asignatura ?? 'N/A',
                    'Código'         => $a?->cod_asignatura ?? 'N/A',
                    'Nombre'         => mb_strimwidth($a?->nombre ?? 'N/A', 0, 30, '...'),
                    'Plan Año'       => $p?->agno_plan ?? 'N/A',
                    'Sem. Plan'      => $ap->semestre_planificado ?? 1,
                    'Horas (C|T|L)'  => ($a?->horas_catedra ?? 0) . '|' . ($a?->horas_taller ?? 0) . '|' . ($a?->horas_laboratorio ?? 0),
                ];
            })->toArray();
            $this->table(['ID Asig', 'Código', 'Nombre', 'Plan Año', 'Sem. Plan', 'Horas (C|T|L)'], $rowsAsig);
        } else {
            $this->warn('  No se encontraron asignaturas locales en UTAMED para los filtros especificados.');
        }

        return Command::SUCCESS;
    }

    /**
     * Consulta registros de oferta académica y códigos de asignatura directamente en Oracle.
     */
    protected function handleOracle(): int
    {
        $limit = (int)$this->option('limit') ?: 20;
        $asigFilter = $this->option('asig');
        $planFilter = $this->option('plan');
        $semestreFilter = $this->option('semestre');

        $this->info("► Consultando catálogo de asignaturas y componentes en Oracle (Intranet)...");

        try {
            $query = VwCarreraCurso::select([
                'ASIG_CODIGO',
                'PLAN_ANO',
                'CURSO_ANO',
                'CURSO_SEMESTRE_ASIG',
                'CARRERA_COD',
                'CURSO_TIPO_ASIG',
                'CURSO_GRUPO_ASIG',
                'CUR_CODIGO',
            ]);

            if ($asigFilter) {
                $query->where('ASIG_CODIGO', 'LIKE', '%' . strtoupper(trim($asigFilter)) . '%');
            }

            if ($planFilter && is_numeric($planFilter)) {
                $query->where('PLAN_ANO', (int)$planFilter);
            }

            if ($semestreFilter && is_numeric($semestreFilter)) {
                $query->where('CURSO_SEMESTRE_ASIG', (int)$semestreFilter);
            }

            $cursos = $query->take($limit)->get();

            if ($cursos->isEmpty()) {
                $this->warn("  No se encontraron registros en Oracle con los filtros ingresados.");
                return Command::SUCCESS;
            }

            $this->info("✔ Se encontraron {$cursos->count()} registros en Oracle (Mostrando hasta {$limit}):");

            $rows = $cursos->map(function (VwCarreraCurso $c) {
                $tipoDesc = match ($c->CURSO_TIPO_ASIG) {
                    'C' => 'C (Cátedra)',
                    'T' => 'T (Taller)',
                    'L' => 'L (Laboratorio)',
                    default => $c->CURSO_TIPO_ASIG,
                };

                return [
                    'Código Asig' => trim($c->ASIG_CODIGO),
                    'Plan Año'    => $c->PLAN_ANO,
                    'Año Oferta'  => $c->CURSO_ANO,
                    'Semestre'    => $c->CURSO_SEMESTRE_ASIG,
                    'Grupo'       => $c->CURSO_GRUPO_ASIG ?: '(Sin grupo)',
                    'Tipo'        => $tipoDesc,
                    'CUR_CODIGO'  => $c->CUR_CODIGO,
                ];
            })->toArray();

            $this->table(['Código Asig', 'Plan Año', 'Año Oferta', 'Semestre', 'Grupo', 'Tipo', 'CUR_CODIGO (Oracle)'], $rows);

            $primer = $cursos->first();
            if ($primer) {
                $asig = trim($primer->ASIG_CODIGO);
                $plan = $primer->PLAN_ANO;
                $agno = $primer->CURSO_ANO;
                $sem = $primer->CURSO_SEMESTRE_ASIG;
                $grupo = $primer->CURSO_GRUPO_ASIG ?: 'A';
                $curCod = $primer->CUR_CODIGO;

                $this->line('');
                $this->comment('💡 Comandos listos para ejecutar con estos datos de Oracle:');
                $this->line("   php artisan intranet:provider cur-codigos --asig={$asig} --plan={$plan} --agno={$agno} --semestre={$sem} --grupo={$grupo}");
                $this->line("   php artisan intranet:provider inscripciones --cur-codigo={$curCod}");
                $this->line("   php artisan intranet:service preview --asig={$asig} --plan={$plan} --agno={$agno} --semestre={$sem} --grupo={$grupo}");
            }

        } catch (\Throwable $e) {
            $this->error("  Error al consultar Oracle: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function handlePlanes(): int
    {
        $this->handleLocal();
        return Command::SUCCESS;
    }

    protected function handleAsignaturas(): int
    {
        $this->handleLocal();
        return Command::SUCCESS;
    }

    protected function handleUnknownView(string $view): int
    {
        $this->error("Vista '{$view}' no reconocida. Opciones: ambas, local, oracle, planes, asignaturas.");
        return Command::FAILURE;
    }
}
