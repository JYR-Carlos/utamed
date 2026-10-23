<?php

namespace Database\Seeders;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\CursoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de datos para verificar y probar interactivamente los Guardarraíles
 * de Componentes e Intranet en el entorno de desarrollo y pruebas.
 *
 * Datos generados:
 * 1. Asignatura Monocomponente (`DM-MONO`): Solo Taller (4 hrs, 0 hrs Cátedra/Lab) en Carrera 23 / Plan 2016.
 * 2. Asignatura Regular (`DM-DEMO`): Cátedra (3 hrs) y Taller (2 hrs).
 * 3. Curso Demo con Componente Huérfana: Cátedra oficial (Acta #555101) + Laboratorio local (sin acta en Intranet).
 * 4. Estudiantes de prueba para verificar el comportamiento del trigger según origen (Manual vs Federado).
 *
 * Uso:
 *   php artisan db:seed --class=Database\\Seeders\\GuardarrailesDemoSeeder
 */
class GuardarrailesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Sembrando datos de prueba para Guardarraíles de Componentes e Intranet...');

        DB::transaction(function () {
            // 1. Obtener o crear carrera Diseño Multimedia (Carrera 23) y Plan 2016
            $carrera = Carrera::firstOrCreate(
                ['nombre' => 'Diseño Multimedia'],
                ['jornada' => 'DIURNA', 'sede' => 'ARICA', 'modalidad' => 'PRESENCIAL']
            );

            $plan = Plan::where('id_carrera', $carrera->id_carrera)
                ->whereIn('agno_plan', [2016, 2026])
                ->where('version_plan', 1)
                ->first();

            if (!$plan) {
                $plan = Plan::create([
                    'id_carrera'           => $carrera->id_carrera,
                    'agno_plan'            => 2016,
                    'version_plan'         => 1,
                    'creditos_sct_totales' => 290,
                ]);
            } elseif ((int) $plan->agno_plan !== 2016) {
                $plan->update(['agno_plan' => 2016]);
            }

            // 2. Obtener tipos de componentes
            $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
            $tipoTaller = TipoComponente::firstOrCreate(['tipo' => 'Taller']);
            $tipoLaboratorio = TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);

            // 3. Crear usuario docente de demostración
            $usuarioDocente = Usuario::firstOrCreate(
                ['rut' => '33333333-3'],
                [
                    'username'   => 'demo_docente_guardarrail',
                    'passhash'   => bcrypt('password123'),
                    'nombre1'    => 'Profesor',
                    'apellido1'  => 'Guardarraíles',
                    'esta_activo'=> true,
                ]
            );
            $usuarioDocente->forceFill(['fecha_cambio_passhash' => now()])->save();
            $docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

            // 4. Crear Asignatura Monocomponente (Solo Taller, 0h Cátedra, 0h Lab)
            $asigMono = Asignatura::firstOrCreate(
                ['cod_asignatura' => 'DM-MONO'],
                [
                    'nombre'            => 'Taller Monocomponente de Prototipado',
                    'creditos_sct'      => 4,
                    'horas_catedra'     => 0,
                    'horas_taller'      => 4,
                    'horas_laboratorio' => 0,
                    'horas_dirigidas'   => 0,
                    'horas_autonomas'   => 0,
                ]
            );
            AsignacionPlan::firstOrCreate(
                ['id_plan' => $plan->id_plan, 'id_asignatura' => $asigMono->id_asignatura],
                ['agno_planificado' => 2, 'semestre_planificado' => 1, 'id_contexto' => 1]
            );

            // 5. Crear Asignatura Regular para Curso con Componente Sobrante
            $asigRegular = Asignatura::firstOrCreate(
                ['cod_asignatura' => 'DM-DEMO'],
                [
                    'nombre'            => 'Diseño de Experiencia de Usuario',
                    'creditos_sct'      => 5,
                    'horas_catedra'     => 3,
                    'horas_taller'      => 2,
                    'horas_laboratorio' => 0,
                    'horas_dirigidas'   => 0,
                    'horas_autonomas'   => 0,
                ]
            );
            $asigPlanRegular = AsignacionPlan::firstOrCreate(
                ['id_plan' => $plan->id_plan, 'id_asignatura' => $asigRegular->id_asignatura],
                ['agno_planificado' => 3, 'semestre_planificado' => 1, 'id_contexto' => 1]
            );

            // 6. Crear Curso Demo con Componente Huérfana / Sobrante
            $codCursoDemo = (int) (DB::table('curso.curso')->max('cod_curso') ?? 1000) + 1;
            $cursoDemo = Curso::where('id_asignacion_plan', $asigPlanRegular->id_asignacion_plan)
                ->where('nombre', 'DEMO Guardarraíles - UX Grupo A')
                ->first();

            if (!$cursoDemo) {
                $cursoDemo = Curso::create([
                    'cod_curso'                    => $codCursoDemo,
                    'nombre'                       => 'DEMO Guardarraíles - UX Grupo A',
                    'indice_grupo'                 => 1,
                    'letra_grupo'                  => 'A',
                    'agno_real'                    => (int) now()->year,
                    'semestre_real'                => 1,
                    'id_asignacion_plan'           => $asigPlanRegular->id_asignacion_plan,
                    'id_docente_titular'           => $docente->id_docente,
                    'id_tipo_componente_principal' => $tipoCatedra->id_tipo_componente,
                    'fecha_inicio'                 => now()->toDateString(),
                    'fecha_fin'                    => now()->addMonths(5)->toDateString(),
                ]);

                // Componente 1: Cátedra oficial con CUR_CODIGO
                Componente::create([
                    'id_curso'                          => $cursoDemo->id_curso,
                    'id_tipo_componente'                => $tipoCatedra->id_tipo_componente,
                    'genera_acta'                       => true,
                    'aprobacion_obligatoria'            => false,
                    'porcentaje_aprobacion'             => 60.00,
                    'porcentaje_asistencia_obligatoria' => 75.00,
                    'intranet_cur_codigo'               => 555101, // Acta oficial simulada
                ]);

                // Componente 2: Laboratorio sobrante (0 horas en plan y sin acta en Intranet)
                Componente::create([
                    'id_curso'                          => $cursoDemo->id_curso,
                    'id_tipo_componente'                => $tipoLaboratorio->id_tipo_componente,
                    'genera_acta'                       => true,
                    'aprobacion_obligatoria'            => false,
                    'porcentaje_aprobacion'             => 60.00,
                    'porcentaje_asistencia_obligatoria' => 75.00,
                    'intranet_cur_codigo'               => null, // Componente local huérfana
                ]);
            }

            // 7. Crear Alumnos de prueba para probar Trigger
            $usuarioManual = Usuario::firstOrCreate(
                ['rut' => '11111111-1'],
                [
                    'username'   => 'demo_alumno_manual',
                    'passhash'   => bcrypt('password123'),
                    'nombre1'    => 'Alumno',
                    'apellido1'  => 'Manual',
                    'esta_activo'=> true,
                ]
            );
            $usuarioManual->forceFill(['fecha_cambio_passhash' => now()])->save();
            Estudiante::firstOrCreate(
                ['id_usuario' => $usuarioManual->id_usuario],
                ['id_carrera' => $carrera->id_carrera]
            );

            $usuarioFederado = Usuario::firstOrCreate(
                ['rut' => '22222222-2'],
                [
                    'username'   => 'demo_alumno_federado',
                    'passhash'   => bcrypt('password123'),
                    'nombre1'    => 'Alumno',
                    'apellido1'  => 'Federado',
                    'esta_activo'=> true,
                ]
            );
            $usuarioFederado->forceFill(['fecha_cambio_passhash' => now()])->save();
            Estudiante::firstOrCreate(
                ['id_usuario' => $usuarioFederado->id_usuario],
                ['id_carrera' => $carrera->id_carrera]
            );
        });

        $this->command?->info('✓ Datos sembrados con éxito.');
        $this->command?->line('  - Asignatura Monocomponente: DM-MONO (Solo Taller)');
        $this->command?->line('  - Curso con Componente Huérfana: DEMO Guardarraíles - UX Grupo A (Cátedra #555101 + Lab sin acta)');
        $this->command?->line('  - Alumno Manual (RUT: 11111111-1) vs Federado (RUT: 22222222-2)');
    }
}
