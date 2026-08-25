<?php

namespace App\Console\Commands;

use App\Models\External\VwAlumno;
use App\Models\External\VwCarreraCurso;
use App\Models\External\VwInscripcion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestIntranetConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intranet:test {--limit=3 : Cantidad de registros a consultar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la conexión y ejecuta queries con LIMIT a los modelos Eloquent de Oracle sin COUNTs pesados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=====================================================');
        $this->info('  TEST DE CONEXIÓN A INTRANET EXTERNA (ORACLE)');
        $this->info('=====================================================');

        $limit = (int) $this->option('limit');

        // 1. Probar conexión básica (SELECT 1 FROM DUAL)
        $this->line('');
        $this->info('1. Probando conexión básica (SELECT 1 FROM DUAL)...');
        $startTime = microtime(true);

        try {
            $rawResult = DB::connection('oracle')->select('SELECT 1 AS TEST, SYSDATE AS FECHA_ACTUAL, USER AS USUARIO_ACTUAL FROM DUAL');
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("   [OK] Conexión establecida exitosamente en {$elapsedMs} ms.");
            if (!empty($rawResult)) {
                $row = (array) $rawResult[0];
                $this->line("   Info Sesión: " . json_encode($row));
            }
        } catch (\Throwable $e) {
            $this->error("   [ERROR] Falló la conexión básica a Oracle:");
            $this->error("   " . $e->getMessage());
            return Command::FAILURE;
        }

        // 2. Probar Modelo VwAlumno (CON LIMIT ESTRICTO)
        $this->line('');
        $this->info("2. Probando modelo VwAlumno (Tabla: " . (new VwAlumno())->getTable() . ", Limit: {$limit})...");
        $t0 = microtime(true);
        try {
            $alumnos = VwAlumno::take($limit)->get();
            $ms = round((microtime(true) - $t0) * 1000, 2);

            if ($alumnos->isNotEmpty()) {
                $this->info("   [OK] Consulta exitosa en {$ms} ms. Retornó {$alumnos->count()} registros:");
                $rows = $alumnos->map(function ($a) {
                    $rut = $a->ALUM_RUT . '-' . $a->ALUM_DIGITO;
                    $nombre = trim($a->ALUM_NOMBRE ?? '');
                    $paterno = trim($a->ALUM_APELLIDO_PAT ?? '');
                    $materno = trim($a->ALUM_APELLIDO_MAT ?? '');
                    return [
                        'RUT' => $rut,
                        'Nombre' => $nombre,
                        'Paterno' => $paterno,
                        'Materno' => $materno,
                    ];
                })->toArray();

                $this->table(['RUT', 'Nombre', 'Paterno', 'Materno'], $rows);
            } else {
                $this->warn("   [VACÍO] La consulta no retornó registros (en {$ms} ms).");
            }
        } catch (\Throwable $e) {
            $this->error("   [ERROR] Falló la consulta en VwAlumno:");
            $this->error("   " . $e->getMessage());
        }

        // 3. Probar Modelo VwCarreraCurso (CON LIMIT ESTRICTO)
        $this->line('');
        $this->info("3. Probando modelo VwCarreraCurso (Tabla: " . (new VwCarreraCurso())->getTable() . ", Limit: {$limit})...");
        $t0 = microtime(true);
        try {
            $cursos = VwCarreraCurso::take($limit)->get();
            $ms = round((microtime(true) - $t0) * 1000, 2);

            if ($cursos->isNotEmpty()) {
                $this->info("   [OK] Consulta exitosa en {$ms} ms. Retornó {$cursos->count()} registros:");
                $rows = $cursos->map(function ($c) {
                    return [
                        'CUR_CODIGO' => $c->CUR_CODIGO,
                        'ASIG_CODIGO' => trim($c->ASIG_CODIGO ?? ''),
                        'Tipo' => $c->CURSO_TIPO_ASIG,
                        'Grupo' => $c->CURSO_GRUPO_ASIG,
                        'Semestre' => $c->CURSO_SEMESTRE_ASIG,
                        'Año' => $c->CURSO_ANO,
                    ];
                })->toArray();

                $this->table(['CUR_CODIGO', 'ASIG_CODIGO', 'Tipo', 'Grupo', 'Semestre', 'Año'], $rows);
            } else {
                $this->warn("   [VACÍO] La consulta no retornó registros (en {$ms} ms).");
            }
        } catch (\Throwable $e) {
            $this->error("   [ERROR] Falló la consulta en VwCarreraCurso:");
            $this->error("   " . $e->getMessage());
        }

        // 4. Probar Modelo VwInscripcion (CON LIMIT ESTRICTO Y RELACIONES)
        $this->line('');
        $this->info("4. Probando modelo VwInscripcion (Tabla: " . (new VwInscripcion())->getTable() . ", Limit: {$limit})...");
        $t0 = microtime(true);
        try {
            $inscripciones = VwInscripcion::with(['alumno', 'carreraCurso'])->take($limit)->get();
            $ms = round((microtime(true) - $t0) * 1000, 2);

            if ($inscripciones->isNotEmpty()) {
                $this->info("   [OK] Consulta exitosa en {$ms} ms. Retornó {$inscripciones->count()} registros:");
                $rows = $inscripciones->map(function ($i) {
                    $alumno = $i->alumno;
                    $alumnoNom = $alumno ? trim($alumno->ALUM_NOMBRE . ' ' . $alumno->ALUM_APELLIDO_PAT) : '(sin relación cargada)';
                    return [
                        'INS_ID' => $i->INS_ID,
                        'RUT' => $i->ALUM_RUT,
                        'Alumno (Eager Loaded)' => $alumnoNom,
                        'CUR_CODIGO' => $i->CUR_CODIGO,
                        'Asignatura' => trim($i->ASIG_CODIGO ?? ''),
                        'Año/Sem' => $i->CURSO_ANO . '-' . $i->CURSO_SEMESTRE_ASIG,
                    ];
                })->toArray();

                $this->table(['INS_ID', 'RUT', 'Alumno (Eager Loaded)', 'CUR_CODIGO', 'Asignatura', 'Año/Sem'], $rows);
            } else {
                $this->warn("   [VACÍO] La consulta no retornó registros (en {$ms} ms).");
            }
        } catch (\Throwable $e) {
            $this->error("   [ERROR] Falló la consulta en VwInscripcion:");
            $this->error("   " . $e->getMessage());
        }

        $this->line('');
        $this->info('=====================================================');
        $this->info('  FIN DE PRUEBAS');
        $this->info('=====================================================');

        return Command::SUCCESS;
    }
}
