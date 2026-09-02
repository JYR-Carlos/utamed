<?php

namespace App\Console\Commands;

use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Services\IntranetService;
use App\Support\LetraGrupo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnosticoCursoIntranet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intranet:diag-curso
        {curso : ID numérico del curso en UTAMED (id_curso)}
        {--dry-run : Ejecuta una simulación completa de sincronización en PostgreSQL con rollback inmediato}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta un diagnóstico completo en 5 etapas para un curso específico sin dependencias de tests, ideal para producción.';

    public function handle(IntranetService $intranetService): int
    {
        $cursoId = $this->argument('curso');

        $this->line('');
        $this->info('╔═══════════════════════════════════════════════════════════════════════════╗');
        $this->info('║         DIAGNÓSTICO INTEGRAL DE CURSO (INTRANET / PRODUCCIÓN)            ║');
        $this->info('╚═══════════════════════════════════════════════════════════════════════════╝');
        $this->line("Analizando Curso ID #{$cursoId}...");
        $this->line('');

        // -------------------------------------------------------------
        // ETAPA 1: Verificación de Datos en PostgreSQL
        // -------------------------------------------------------------
        $this->comment('► ETAPA 1: Integridad de Datos en PostgreSQL (Base Local)');

        $curso = Curso::with([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'componentes.tipoComponente',
            'docenteTitular.usuario',
        ])->where('cod_curso',$cursoId);

        if (!$curso) {
            $this->error("  [FALLO] No se encontró ningún curso con id_curso = {$cursoId} en PostgreSQL.");
            return Command::FAILURE;
        }

        $this->info("  ✔ Curso encontrado: #{$curso->id_curso} | Código: [{$curso->cod_curso}] | Nombre: '{$curso->nombre}'");
        $this->line("    - Año Real: " . ($curso->agno_real ?? '<null>'));
        $this->line("    - Semestre Real: " . ($curso->semestre_real ?? '<null>'));
        $this->line("    - Índice Grupo: " . ($curso->indice_grupo ?? '<null>'));
        $this->line("    - Letra Grupo: " . ($curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo) ?: '<vacío>'));

        $asignacionPlan = $curso->asignacionPlan;
        if (!$asignacionPlan) {
            $this->error("  [FALLO] El curso no tiene una asignacionPlan vinculada (id_asignacion_plan nulo o huérfano).");
            return Command::FAILURE;
        }

        $asignatura = $asignacionPlan->asignatura;
        $plan = $asignacionPlan->plan;
        $carrera = $plan?->carrera;

        if (!$asignatura) {
            $this->error("  [FALLO] La asignación #{$asignacionPlan->id_asignacion_plan} no tiene asignatura vinculada en PostgreSQL.");
            return Command::FAILURE;
        }
        if (!$plan) {
            $this->error("  [FALLO] La asignación #{$asignacionPlan->id_asignacion_plan} no tiene plan vinculado en PostgreSQL.");
            return Command::FAILURE;
        }

        $planCod = (int)($plan->agno_plan ?? $plan->cod_plan ?? $plan->id_plan ?? 0);
        $asigCodigo = trim((string)($asignatura->cod_asignatura ?? ''));

        $this->info("  ✔ Asignatura: [{$asigCodigo}] {$asignatura->nombre}");
        $this->info("  ✔ Plan: Año/Código [{$planCod}]");
        $this->info("  ✔ Carrera: " . ($carrera ? "[#{$carrera->id_carrera}] {$carrera->nombre}" : '<Sin carrera vinculada>'));

        $componentesActuales = $curso->componentes;
        $this->line("    - Componentes actuales en BD: " . ($componentesActuales->isNotEmpty() ? $componentesActuales->pluck('tipoComponente.tipo')->filter()->implode(', ') : '(Ninguna creada aún)'));

        // -------------------------------------------------------------
        // ETAPA 2: Verificación de Conexión a Oracle (Intranet)
        // -------------------------------------------------------------
        $this->line('');
        $this->comment('► ETAPA 2: Conectividad y Acceso a Oracle');

        $oracleHost = config('database.connections.oracle.host');
        $oraclePort = config('database.connections.oracle.port', '1521');
        $oracleService = config('database.connections.oracle.service_name');
        $oracleUser = config('database.connections.oracle.username');

        $this->line("    Configuración activa en Laravel: Host={$oracleHost}, Port={$oraclePort}, Service={$oracleService}, User={$oracleUser}");

        $oracleOk = false;
        try {
            $t0 = microtime(true);
            DB::connection('oracle')->select('SELECT 1 AS TEST, SYSDATE FROM DUAL');
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $this->info("  ✔ Conexión exitosa a Oracle (SELECT 1 FROM DUAL) en {$ms} ms.");
            $oracleOk = true;
        } catch (\Throwable $e) {
            $this->error("  [FALLO CRÍTICO] No fue posible conectar a Oracle:");
            $this->error("    " . $e->getMessage());
            $this->line("    Excepción: " . get_class($e));
            $this->line("    Archivo: " . $e->getFile() . ':' . $e->getLine());
            $this->warn("  ► RECOMENDACIÓN: Verifique firewall hacia el puerto {$oraclePort}, estado de la VPN, credenciales en .env o extensión PHP oci8.");
        }

        // -------------------------------------------------------------
        // ETAPA 3: Consulta de Actas / CUR_CODIGO en Oracle
        // -------------------------------------------------------------
        $this->line('');
        $this->comment('► ETAPA 3: Búsqueda de Componentes en Oracle para este Curso');

        $letraGrupo = $curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo);
        $agno = $curso->agno_real ?? (int) now()->year;
        $semestre = $curso->semestre_real ?? 1;

        $this->line("    Parámetros de búsqueda:");
        $this->line("    - ASIG_CODIGO: '{$asigCodigo}'");
        $this->line("    - PLAN_ANO:    {$planCod}");
        $this->line("    - CURSO_ANO:   {$agno}");
        $this->line("    - SEMESTRE:    {$semestre}");
        $this->line("    - GRUPO:       " . ($letraGrupo !== '' ? "'{$letraGrupo}'" : '<null>'));

        if (!$oracleOk) {
            $this->warn("  [OMITIDO] Se omite consulta directa a Oracle debido a falla de conexión previa.");
        } else {
            try {
                $oracleServiceApp = app('OracleDataService');
                $componentesOracle = $oracleServiceApp->traer_cur_codigos(
                    semestre: $semestre,
                    agno: $agno,
                    planCod: $planCod,
                    asigCodigo: $asigCodigo,
                    grupoAsig: $letraGrupo !== '' ? $letraGrupo : null
                );

                if ($componentesOracle->isNotEmpty()) {
                    $this->info("  ✔ Oracle retornó {$componentesOracle->count()} componente(s):");
                    $rows = $componentesOracle->map(fn($c) => [
                        'CUR_CODIGO' => $c->cur_codigo,
                        'Tipo'       => $c->curso_tipo_asig->value,
                        'Grupo'      => $c->curso_grupo_asig,
                    ])->toArray();
                    $this->table(['CUR_CODIGO', 'Tipo', 'Grupo'], $rows);
                } else {
                    $this->warn("  [VACÍO] Oracle no retornó registros para esta combinación de asignatura/plan/año/semestre.");
                    $this->line("    (El sistema recurrirá al fallback del Plan de Estudios si corresponde).");
                }
            } catch (\Throwable $e) {
                $this->error("  [FALLO] Error al invocar OracleDataService::traer_cur_codigos:");
                $this->error("    " . $e->getMessage());
                $this->line("    Archivo: " . $e->getFile() . ':' . $e->getLine());
            }
        }

        // -------------------------------------------------------------
        // ETAPA 4: Previsualización de Negocio (IntranetService)
        // -------------------------------------------------------------
        $this->line('');
        $this->comment('► ETAPA 4: Ejecución de previsualizarComponentes()');

        try {
            $preview = $intranetService->previsualizarComponentes(
                idAsignatura: $asignacionPlan->id_asignatura,
                idPlan: $asignacionPlan->id_plan,
                agno: $agno,
                semestre: $semestre,
                letraGrupo: $letraGrupo
            );

            $this->info("  ✔ Previsualización exitosa:");
            $this->line("    - Origen determinado: [{$preview->origen}]");
            $this->line("    - Tipo principal sugerido: ID #" . ($preview->id_tipo_componente_principal ?? '<ninguno>'));

            if (!empty($preview->componentes)) {
                $rowsComp = array_map(fn($c) => [
                    'ID Tipo'    => $c->id_tipo_componente,
                    'Tipo'       => $c->tipo,
                    'Origen'     => $c->origen,
                    'CUR_CODIGO' => $c->cur_codigo ?? '<N/A>',
                ], $preview->componentes);
                $this->table(['ID Tipo', 'Tipo', 'Origen', 'CUR_CODIGO'], $rowsComp);
            } else {
                $this->warn("    - No se detectaron componentes para crear.");
            }

            if (!empty($preview->advertencias)) {
                $this->warn("    - Advertencias reportadas:");
                foreach ($preview->advertencias as $adv) {
                    $this->line("      • {$adv}");
                }
            }
        } catch (\Throwable $e) {
            $this->error("  [FALLO] Excepción durante previsualizarComponentes():");
            $this->error("    " . $e->getMessage());
            $this->line("    Archivo: " . $e->getFile() . ':' . $e->getLine());
            $this->line("    Traza:\n" . $e->getTraceAsString());
            return Command::FAILURE;
        }

        // -------------------------------------------------------------
        // ETAPA 5: Simulación de Sincronización en BD (Opcional con --dry-run)
        // -------------------------------------------------------------
        if ($this->option('dry-run')) {
            $this->line('');
            $this->comment('► ETAPA 5: Simulación de sincronizarComponentes() (Dry-Run con Rollback)');

            $idsTipos = collect($preview->componentes)->pluck('id_tipo_componente')->all();
            if (empty($idsTipos)) {
                $this->warn("  [OMITIDO] No hay IDs de componentes para sincronizar.");
            } else {
                DB::beginTransaction();
                try {
                    $resultadoSync = $intranetService->sincronizarComponentes(
                        curso: $curso,
                        idsTipoComponenteAceptados: $idsTipos,
                        inscribirAlumnos: false
                    );

                    $this->info("  ✔ Simulación exitosa en PostgreSQL:");
                    $this->line("    - Componentes creadas: " . (implode(', ', $resultadoSync->componentes_creadas) ?: '<ninguna>'));
                    $this->line("    - Componentes existentes: " . (implode(', ', $resultadoSync->componentes_existentes) ?: '<ninguna>'));
                    if (!empty($resultadoSync->advertencias)) {
                        foreach ($resultadoSync->advertencias as $adv) {
                            $this->line("      • Advertencia: {$adv}");
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("  [FALLO EN BASE DE DATOS] Falló la creación de componentes en PostgreSQL:");
                    $this->error("    " . $e->getMessage());
                    $this->line("    Archivo: " . $e->getFile() . ':' . $e->getLine());
                    $this->line("    Traza:\n" . $e->getTraceAsString());
                } finally {
                    DB::rollBack();
                    $this->line("    (Transacción cancelada / Rollback efectuado; ningún dato fue alterado).");
                }
            }
        } else {
            $this->line('');
            $this->line("  Tip: Agregue '--dry-run' para simular la creación de componentes en PostgreSQL con rollback inmediato.");
        }

        $this->line('');
        $this->info('═══════════════════════════════════════════════════════════════════════════');
        $this->info('  DIAGNÓSTICO COMPLETADO');
        $this->info('═══════════════════════════════════════════════════════════════════════════');

        return Command::SUCCESS;
    }
}
