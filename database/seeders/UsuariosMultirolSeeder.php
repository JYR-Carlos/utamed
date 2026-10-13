<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Enums\DB\EstadoActividadAsignada;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para usuarios de prueba multirol compartiendo cursos y actividades activas.
 *
 * Crea dos usuarios específicos para pruebas de entornos combinados:
 * 1. Docente + Estudiante (RUT: 99999991-1 / password123)
 * 2. Admin + Docente + Estudiante (RUT: 99999992-2 / password123)
 *
 * Cursos compartidos del período activo:
 * - Curso A: multirol_docente es el Docente Titular y multirol_admin es el Estudiante.
 * - Curso B: multirol_admin es el Docente Titular y multirol_docente es el Estudiante.
 * - Curso C: Ambos son Estudiantes inscritos compartiendo grupos en las actividades.
 *
 * Uso:
 *   php artisan db:seed --class=Database\\Seeders\\UsuariosMultirolSeeder
 */
class UsuariosMultirolSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Configurando usuarios multirol con cursos y actividades compartidas...');

        DB::transaction(function () {
            $rolSuperAdmin = Rol::where('nombre', 'SuperAdmin')
                ->orWhere('nombre', 'Super Admin')
                ->first();
            $rolDocente = Rol::where('nombre', 'Docente Titular')->firstOrFail();
            $rolEstudiante = Rol::where('nombre', 'Estudiante')->firstOrFail();
            $carrera = Carrera::first();

            // 1. Resolver el período vigente real
            $periodo = DB::table('curso.curso')
                ->select('agno_real as ano', 'semestre_real as sem')
                ->where('es_plantilla', false)
                ->whereNull('fecha_eliminacion')
                ->orderByDesc('agno_real')
                ->orderByDesc('semestre_real')
                ->first();

            $ano = $periodo ? (int) $periodo->ano : now()->year;
            $sem = $periodo ? (int) $periodo->sem : 1;

            // 2. Obtener 3 cursos activos con componentes y actividades
            $cursos = Curso::where('agno_real', $ano)
                ->where('semestre_real', $sem)
                ->where('es_plantilla', false)
                ->whereNull('fecha_eliminacion')
                ->whereHas('componentes.actividades')
                ->with(['componentes.actividades'])
                ->take(3)
                ->get();

            if ($cursos->count() < 3) {
                // Fallback a cualquier curso con componentes
                $cursos = Curso::where('es_plantilla', false)
                    ->whereNull('fecha_eliminacion')
                    ->whereHas('componentes')
                    ->with('componentes')
                    ->take(3)
                    ->get();
            }

            $cursoA = $cursos[0] ?? null;
            $cursoB = $cursos[1] ?? null;
            $cursoC = $cursos[2] ?? null;

            if (!$cursoA || !$cursoB) {
                throw new \RuntimeException('No se encontraron cursos suficientes en la base de datos.');
            }

            // ─────────────────────────────────────────────────────────────
            // Creación / Actualización de Usuarios
            // ─────────────────────────────────────────────────────────────
            $u1 = Usuario::updateOrCreate(
                ['rut' => '99999991-1'],
                [
                    'username' => 'multirol_docente',
                    'email' => 'multirol_docente@utamed.local',
                    'nombre1' => 'Docente',
                    'nombre2' => 'Y',
                    'apellido1' => 'Estudiante',
                    'apellido2' => 'Test',
                    'passhash' => Hash::make('password123'),
                    'esta_activo' => true,
                    'fecha_cambio_passhash' => now(),
                    'fecha_verificacion_email' => now(),
                ]
            );

            $u2 = Usuario::updateOrCreate(
                ['rut' => '99999992-2'],
                [
                    'username' => 'multirol_admin',
                    'email' => 'multirol_admin@utamed.local',
                    'nombre1' => 'Admin',
                    'nombre2' => 'Multirol',
                    'apellido1' => 'Completo',
                    'apellido2' => 'Test',
                    'passhash' => Hash::make('password123'),
                    'esta_activo' => true,
                    'fecha_cambio_passhash' => now(),
                    'fecha_verificacion_email' => now(),
                ]
            );

            // Perfiles
            $d1 = Docente::updateOrCreate(
                ['id_usuario' => $u1->id_usuario],
                ['grado' => 'Magíster', 'titulo' => 'Profesor de Multimedia']
            );

            $e1 = Estudiante::updateOrCreate(
                ['id_usuario' => $u1->id_usuario],
                [
                    'id_carrera' => $carrera?->id_carrera ?? 1,
                    'agno_ingreso' => 2024,
                    'correo_personal' => 'estudiante1@test.local',
                ]
            );

            $d2 = Docente::updateOrCreate(
                ['id_usuario' => $u2->id_usuario],
                ['grado' => 'Doctor', 'titulo' => 'Director y Académico']
            );

            $e2 = Estudiante::updateOrCreate(
                ['id_usuario' => $u2->id_usuario],
                [
                    'id_carrera' => $carrera?->id_carrera ?? 1,
                    'agno_ingreso' => 2023,
                    'correo_personal' => 'estudiante2@test.local',
                ]
            );

            // Asignar SuperAdmin global a u2
            if ($rolSuperAdmin) {
                UsuarioRolAsignacion::updateOrCreate(
                    [
                        'id_usuario' => $u2->id_usuario,
                        'id_rol' => $rolSuperAdmin->id_rol,
                        'id_contexto' => 1,
                    ],
                    [
                        'esta_activo' => true,
                        'fue_eliminado' => false,
                        'asignado_por' => 1,
                        'creado_por' => 1,
                        'fecha_inicio_planificada' => now()->subMonth(),
                        'fecha_fin_planificada' => now()->addYear(),
                    ]
                );
            }

            // ─────────────────────────────────────────────────────────────
            // 3. Configurar CURSO A: Docente = u1, Estudiante = u2
            // ─────────────────────────────────────────────────────────────
            $this->vincularDocenteCurso($u1, $d1, $cursoA, $rolDocente);
            $this->vincularEstudianteCurso($u2, $e2, $cursoA, $rolEstudiante);

            // ─────────────────────────────────────────────────────────────
            // 4. Configurar CURSO B: Docente = u2, Estudiante = u1
            // ─────────────────────────────────────────────────────────────
            $this->vincularDocenteCurso($u2, $d2, $cursoB, $rolDocente);
            $this->vincularEstudianteCurso($u1, $e1, $cursoB, $rolEstudiante);

            // ─────────────────────────────────────────────────────────────
            // 5. Configurar CURSO C: Ambos Estudiantes en el mismo curso
            // ─────────────────────────────────────────────────────────────
            if ($cursoC) {
                $this->vincularEstudianteCurso($u1, $e1, $cursoC, $rolEstudiante);
                $this->vincularEstudianteCurso($u2, $e2, $cursoC, $rolEstudiante);
                $this->unificarGruposEnCurso($e1, $e2, $cursoC);
            }

            $this->command->info("✓ Cursos y actividades configurados exitosamente:");
            $this->command->line("  • Curso A ({$cursoA->nombre}): Docente = multirol_docente | Alumno = multirol_admin");
            $this->command->line("  • Curso B ({$cursoB->nombre}): Docente = multirol_admin | Alumno = multirol_docente");
            if ($cursoC) {
                $this->command->line("  • Curso C ({$cursoC->nombre}): Ambos alumnos compañeros de equipo");
            }
        });
    }

    /**
     * Asigna al docente como titular del curso y de sus componentes.
     */
    private function vincularDocenteCurso(Usuario $user, Docente $docente, Curso $curso, Rol $rolDocente): void
    {
        $curso->id_docente_titular = $docente->id_docente;
        $curso->save();

        UsuarioRolAsignacion::updateOrCreate(
            [
                'id_usuario' => $user->id_usuario,
                'id_rol' => $rolDocente->id_rol,
                'id_contexto' => $curso->id_contexto,
            ],
            [
                'esta_activo' => true,
                'fue_eliminado' => false,
                'asignado_por' => 1,
                'creado_por' => 1,
                'fecha_inicio_planificada' => now()->subMonth(),
                'fecha_fin_planificada' => now()->addYear(),
            ]
        );

        foreach ($curso->componentes as $comp) {
            DocenteComponente::updateOrCreate(
                ['id_componente' => $comp->id_componente, 'id_docente' => $docente->id_docente],
                ['es_titular' => true]
            );
        }
    }

    /**
     * Inscribe al estudiante en el curso, sus componentes y en las actividades existentes.
     */
    private function vincularEstudianteCurso(Usuario $user, Estudiante $estudiante, Curso $curso, Rol $rolEstudiante): void
    {
        InscripcionCurso::updateOrCreate(
            ['id_curso' => $curso->id_curso, 'id_estudiante' => $estudiante->id_estudiante],
            ['estado_inscripcion' => 'INSCRITO', 'fecha_inscripcion' => now()]
        );

        UsuarioRolAsignacion::updateOrCreate(
            [
                'id_usuario' => $user->id_usuario,
                'id_rol' => $rolEstudiante->id_rol,
                'id_contexto' => $curso->id_contexto,
            ],
            [
                'esta_activo' => true,
                'fue_eliminado' => false,
                'asignado_por' => 1,
                'creado_por' => 1,
                'fecha_inicio_planificada' => now()->subMonth(),
                'fecha_fin_planificada' => now()->addYear(),
            ]
        );

        foreach ($curso->componentes as $comp) {
            InscripcionComponente::updateOrCreate(
                ['id_componente' => $comp->id_componente, 'id_estudiante' => $estudiante->id_estudiante]
            );

            // Asignar al estudiante en las actividades del componente
            $actividades = Actividad::where('id_componente', $comp->id_componente)->get();
            foreach ($actividades as $act) {
                if ($act->es_grupal) {
                    // Asignar al primer grupo de la actividad
                    $grupo = ActividadAsignadaGrupo::where('id_actividad', $act->id_actividad)
                        ->orderBy('id_actividad_asignada_grupo', 'asc')
                        ->first();

                    if ($grupo) {
                        IntegranteGrupo::firstOrCreate(
                            [
                                'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
                                'id_estudiante' => $estudiante->id_estudiante,
                            ],
                            ['diferencia_decimas' => 0]
                        );
                    }
                } else {
                    // Actividad individual: asegurar que el estudiante tenga su propio grupo asignado
                    $grupo = ActividadAsignadaGrupo::where('id_actividad', $act->id_actividad)
                        ->whereHas('miembros', fn($q) => $q->where('id_estudiante', $estudiante->id_estudiante))
                        ->first();

                    if (!$grupo) {
                        $nuevoGrupo = ActividadAsignadaGrupo::create([
                            'nombre_grupo' => "Estudiante {$user->nombre1} {$user->apellido1}",
                            'nota' => null,
                            'estado_actividad_asignada' => EstadoActividadAsignada::ACTIVA->value,
                            'id_actividad' => $act->id_actividad,
                        ]);

                        IntegranteGrupo::create([
                            'id_actividad_asignada_grupo' => $nuevoGrupo->id_actividad_asignada_grupo,
                            'id_estudiante' => $estudiante->id_estudiante,
                            'diferencia_decimas' => 0,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Asegura que en las actividades grupales de un curso compartido, ambos estudiantes estén juntos.
     */
    private function unificarGruposEnCurso(Estudiante $e1, Estudiante $e2, Curso $curso): void
    {
        foreach ($curso->componentes as $comp) {
            $actividadesGrupales = Actividad::where('id_componente', $comp->id_componente)
                ->where('es_grupal', true)
                ->get();

            foreach ($actividadesGrupales as $act) {
                $primerGrupo = ActividadAsignadaGrupo::where('id_actividad', $act->id_actividad)
                    ->orderBy('id_actividad_asignada_grupo', 'asc')
                    ->first();

                if ($primerGrupo) {
                    IntegranteGrupo::firstOrCreate(
                        [
                            'id_actividad_asignada_grupo' => $primerGrupo->id_actividad_asignada_grupo,
                            'id_estudiante' => $e1->id_estudiante,
                        ],
                        ['diferencia_decimas' => 0]
                    );

                    IntegranteGrupo::firstOrCreate(
                        [
                            'id_actividad_asignada_grupo' => $primerGrupo->id_actividad_asignada_grupo,
                            'id_estudiante' => $e2->id_estudiante,
                        ],
                        ['diferencia_decimas' => 0]
                    );
                }
            }
        }
    }
}
