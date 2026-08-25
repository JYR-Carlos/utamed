<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tests\Integration\External\IntranetTestHelper;

class InspectIntranetData extends Command
{
    protected $signature = 'intranet:inspect {--multicomponent : Buscar asignaturas con múltiples componentes}';
    protected $description = 'Inspecciona la estructura real y cómo se resuelven componentes en Oracle';

    public function handle()
    {
        IntranetTestHelper::loadOracleConfig();
        $conn = DB::connection('oracle');

        $this->info("==================================================");
        $this->info(" ASIGNATURAS REALES CON MÚLTIPLES COMPONENTES");
        $this->info("==================================================");

        try {
            $results = $conn->select("
                SELECT c.ASIG_CODIGO, c.CURSO_ANO, c.CURSO_SEMESTRE_ASIG, c.CARRERA_COD, c.PLAN_ANO, c.CURSO_GRUPO_ASIG,
                       COUNT(DISTINCT c.CURSO_TIPO_ASIG) as num_tipos,
                       COUNT(*) as total_filas
                FROM (
                    SELECT * FROM CARRERA_CURSO WHERE ROWNUM <= 5000
                ) c
                GROUP BY c.ASIG_CODIGO, c.CURSO_ANO, c.CURSO_SEMESTRE_ASIG, c.CARRERA_COD, c.PLAN_ANO, c.CURSO_GRUPO_ASIG
                HAVING COUNT(DISTINCT c.CURSO_TIPO_ASIG) > 1
                ORDER BY c.CURSO_ANO DESC
            ");

            if (empty($results)) {
                $this->warn("No se encontraron en la muestra de 5000 filas. Buscando en general...");
                $results = $conn->select("
                    SELECT ASIG_CODIGO, CURSO_ANO, CURSO_SEMESTRE_ASIG, CARRERA_COD, PLAN_ANO, CURSO_GRUPO_ASIG,
                           COUNT(*) as total_filas
                    FROM CARRERA_CURSO
                    WHERE ASIG_CODIGO IN ('IE124', 'EN156', 'BS362', 'NU215', 'DI021')
                    GROUP BY ASIG_CODIGO, CURSO_ANO, CURSO_SEMESTRE_ASIG, CARRERA_COD, PLAN_ANO, CURSO_GRUPO_ASIG
                ");
            }

            $this->info("Ejemplos de asignaturas con múltiples componentes:");
            $sampleAsigs = array_slice($results, 0, 5);

            foreach ($sampleAsigs as $s) {
                $s = (array)$s;
                $asig = trim($s['ASIG_CODIGO'] ?? $s['asig_codigo']);
                $ano = $s['CURSO_ANO'] ?? $s['curso_ano'];
                $sem = $s['CURSO_SEMESTRE_ASIG'] ?? $s['curso_semestre_asig'];
                $carrera = $s['CARRERA_COD'] ?? $s['carrera_cod'];
                $plan = $s['PLAN_ANO'] ?? $s['plan_ano'];
                $grupo = trim($s['CURSO_GRUPO_ASIG'] ?? $s['curso_grupo_asig'] ?? '');

                $this->line("");
                $this->info("▶ Asignatura: {$asig} (Año: {$ano}, Sem: {$sem}, Carrera: {$carrera}, Plan: {$plan}, Grupo: {$grupo})");

                $componentes = $conn->select("
                    SELECT CUR_CODIGO, ASIG_CODIGO, CURSO_TIPO_ASIG, CURSO_GRUPO_ASIG
                    FROM CARRERA_CURSO
                    WHERE ASIG_CODIGO = ? AND CURSO_ANO = ? AND CURSO_SEMESTRE_ASIG = ? AND CARRERA_COD = ? AND PLAN_ANO = ? AND CURSO_GRUPO_ASIG = ?
                ", [$asig, $ano, $sem, $carrera, $plan, $grupo]);

                $compRows = [];
                foreach ($componentes as $comp) {
                    $comp = (array)$comp;
                    $curCod = $comp['CUR_CODIGO'] ?? $comp['cur_codigo'];
                    $tipo = $comp['CURSO_TIPO_ASIG'] ?? $comp['curso_tipo_asig'];

                    // Consultar cuántos inscritos tiene este cur_codigo en INSCRIPCION
                    $inscritos = $conn->select("
                        SELECT COUNT(*) as TOTAL FROM (SELECT 1 FROM INSCRIPCION WHERE CUR_CODIGO = ? AND ROWNUM <= 500)
                    ", [$curCod]);
                    $totalIns = ((array)($inscritos[0] ?? []))['TOTAL'] ?? ((array)($inscritos[0] ?? []))['total'] ?? 0;

                    $compRows[] = [
                        'CUR_CODIGO'       => $curCod,
                        'Tipo'             => $tipo . ' (' . ($tipo === 'C' ? 'Cátedra' : ($tipo === 'L' ? 'Laboratorio' : 'Taller')) . ')',
                        'Grupo'            => $comp['CURSO_GRUPO_ASIG'] ?? $comp['curso_grupo_asig'],
                        'Inscritos Muestra' => $totalIns,
                    ];
                }

                $this->table(['CUR_CODIGO', 'Tipo', 'Grupo', 'Inscritos Muestra'], $compRows);
            }
        } catch (\Throwable $e) {
            $this->error("Error: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
