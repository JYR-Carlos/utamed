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
    protected $signature = 'intranet:test {--limit=3 : Cantidad de registros a mostrar por modelo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la conexión a la base de datos Oracle de la Intranet y ejecuta queries con los modelos Eloquent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=====================================================');
        $this->info('  TEST DE CONEXIÓN A INTRANET EXTERNA (ORACLE)');
        $this->info('=====================================================');

        $limit = (int) $this->option('limit');

        // 1. Mostrar configuración detectada (sin revelar contraseña)
        $config = config('database.connections.oracle');
        $this->line('');
        $this->info('1. Configuración detectada en config/database.php:');
        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['Host', $config['host'] ?? '(vacío)'],
                ['Port', $config['port'] ?? '(vacío)'],
                ['Database (SID)', $config['database'] ?? '(vacío)'],
                ['Service Name', $config['service_name'] ?? '(vacío)'],
                ['Username', $config['username'] ?? '(vacío)'],
                ['Password', !empty($config['password']) ? '******** (definida)' : '(no definida)'],
                ['Charset', $config['charset'] ?? '(vacío)'],
            ]
        );

        // 2. Probar conexión básica (SELECT 1 FROM DUAL)
        $this->line('');
        $this->info('2. Probando conexión cruda a Oracle (SELECT 1 FROM DUAL)...');
        $startTime = microtime(true);

        try {
            $rawResult = DB::connection('oracle')->select('SELECT 1 AS TEST, SYSDATE AS FECHA_ACTUAL FROM DUAL');
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("   [OK] Conexión establecida exitosamente en {$elapsedMs} ms.");
            if (!empty($rawResult)) {
                $row = (array) $rawResult[0];
                $this->line("   Resultado DUAL: " . json_encode($row));
            }
        } catch (\Throwable $e) {
            $this->error("   [ERROR] Falló la conexión básica a Oracle:");
            $this->error("   " . $e->getMessage());
            return Command::FAILURE;
        }

        // 3. Probar Modelo VwAlumno
        $this->line('');
        $this->info("3. Probando modelo VwAlumno (Tabla: " . (new VwAlumno())->getTable() . ")...");
        try {
            $countAlumnos = VwAlumno::count();
            $this->info("   [OK] Total de registros encontrados: {$countAlumnos}");

            $alumnos = VwAlumno::limit($limit)->get();
            if ($alumnos->isNotEmpty()) {
                $rows = $alumnos->map(function ($a) {
                    return [
                        'RUT' => $a->ALUM_RUT . '-' . $a->ALUM_DIGITO,
                        'Nombre' => trim($a->ALUM_NOMBRE ?? ''),
                        'Paterno' => trim($a->ALUM_APELLIDO_PAT ?? ''),
                        'Materno' => trim($a->ALUM_APELLIDO_MAT ?? ''),
                        'F. Nacimiento' => $a->ALUM_FECHA_NACIMIENTO?->format('Y-m-d') ?? 'N/A',
                    ];
                })->toArray();

                $this->table(['RUT', 'Nombre', 'Paterno', 'Materno', 'F. Nacimiento'], $rows);
            } else {
                $this->warn("   No se retornaron registros en VwAlumno.");
            }
        } catch (\Throwable $e) {
            $this->error("   [ERROR] Falló la consulta en VwAlumno:");
            $this->error("   " . $e->getMessage());
        }

        // 4. Probar Modelo VwCarreraCurso
        $this->line('');
        $this->info("4. Probando modelo VwCarreraCurso (Tabla: " . (new VwCarreraCurso())->getTable() . ")...");
        try {
            $countCursos = VwCarreraCurso::count();
            $this->info("   [OK] Total de registros encontrados: {$countCursos}");

            $cursos = VwCarreraCurso::limit($limit)->get();
            if ($cursos->isNotEmpty()) {
                $rows = $cursos->map(function ($c) {
                    return [
                        'CUR_CODIGO' => $c->CUR_CODIGO,
                        'ASIG_CODIGO' => trim($c->ASIG_CODIGO ?? ''),
                        'Tipo' => $c->CURSO_TIPO_ASIG,
                        'Grupo' => $c->CURSO_GRUPO_ASIG,
                        'Semestre' => $c->CURSO_SEMESTRE_ASIG,
                        'Año' => $c->CURSO_ANO,
                        'Carrera' => $c->CARRERA_COD,
                        'Plan' => $c->PLAN_ANO,
                    ];
                })->toArray();

                $this->table(['CUR_CODIGO', 'ASIG_CODIGO', 'Tipo', 'Grupo', 'Semestre', 'Año', 'Carrera', 'Plan'], $rows);
            } else {
                $this->warn("   No se retornaron registros en VwCarreraCurso.");
            }
        } catch (\Throwable $e) {
            $this->error("   [ERROR] Falló la consulta en VwCarreraCurso:");
            $this->error("   " . $e->getMessage());
        }

        // 5. Probar Modelo VwInscripcion y Relaciones Eloquent
        $this->line('');
        $this->info("5. Probando modelo VwInscripcion (Tabla: " . (new VwInscripcion())->getTable() . ") con relaciones...");
        try {
            $countInscripciones = VwInscripcion::count();
            $this->info("   [OK] Total de inscripciones encontradas: {$countInscripciones}");

            $inscripciones = VwInscripcion::with(['alumno', 'carreraCurso'])->limit($limit)->get();
            if ($inscripciones->isNotEmpty()) {
                $rows = $inscripciones->map(function ($i) {
                    $alumnoNom = $i->alumno ? trim($i->alumno->ALUM_NOMBRE . ' ' . $i->alumno->ALUM_APELLIDO_PAT) : '(sin relación)';
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
                $this->warn("   No se retornaron registros en VwInscripcion.");
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
