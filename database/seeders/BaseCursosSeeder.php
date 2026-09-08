<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\Unidad;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\Authorization\RoleAssignmentBuilder;

class BaseCursosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los tipos de componente disponibles
        $tiposComponente = TipoComponente::pluck('id_tipo_componente');

        if (empty($tiposComponente)) {
            $this->command->warn('No hay tipos de componente en la BD. Ejecuta TipoComponenteSeeder primero.');
            return;
        }

        // Obtener todas las asignaciones de plan válidas
        $asignacionesPlanes = AsignacionPlan::all();

        if (empty($asignacionesPlanes)) {
            $this->command->warn('No hay asignaciones de plan en la BD.');
            return;
        }

        $cursosCreados = 0;
        $componentesCreados = 0;
        $unidadesCreadas = 0;

        // Por cada AsignacionPlan, crear dos Cursos con Componentes y Unidades
        foreach ($asignacionesPlanes as $asignacionPlan) {
            try {
                $rolDocenteTitular = \App\Models\Usuario\Rol::where('nombre', 'Docente Titular')->first();
                $superAdmin = Usuario::where('username', 'superadmin')->first();

                // Crear 2 cursos por asignación
                for ($c = 1; $c <= 2; $c++) {
                    $docente = Docente::inRandomOrder()->firstOrFail();

                    // Crear el Curso
                    $curso = Curso::create([
                        'cod_curso' => random_int(100000000, 999999999),
                        'nombre' => 'Curso ' . $c . ' para ' . $asignacionPlan->asignatura?->nombre ?? 'Asignatura',
                        'fecha_inicio' => now()->subMonths(random_int(1, 6)),
                        'fecha_fin' => now()->addMonths(random_int(1, 6)),
                        'agno_real' => now()->year,
                        'semestre_real' => $asignacionPlan->semestre_planificado,
                        'estado_interno' => 'activo',
                        'estado_acta' => 'pendiente',
                        'es_plantilla' => $c === 2, // El segundo es plantilla
                        'es_colegiado' => false,
                        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                        'id_docente_titular' => $docente->id_docente,
                    ])->refresh();

                    // ROL DOCENTE TITULAR

                    /** @var Usuario usuario del docente */
                    $usuarioDocente = $docente->usuario;


                    if (!$curso->id_contexto) {
                        throw new \Exception("El curso '{$curso->nombre}' no tiene un contexto asociado.");
                    }

                    // Construcción directa: este seeder corre sin usuario autenticado
                    // (giveRole() exige un actor autenticado desde H-3, ver AssignsPermissions).
                    $asignacionRol = (new RoleAssignmentBuilder($usuarioDocente, $rolDocenteTitular, $superAdmin))
                        ->on($curso)
                        ->save();

                    if (!$asignacionRol) {
                        throw new \Exception("Error al asignar rol de Docente Titular a usuario '{$usuarioDocente->username}' para el curso '{$curso->cod_curso}'");
                    }
                    echo "{$asignacionRol->id_usuario_rol} - Rol '{$asignacionRol->rol->nombre}' asignado a usuario '{$asignacionRol->id_usuario}' para curso '{$asignacionRol->contexto->id_contexto}'\n";

                    $cursosCreados++;

                    // Crear 1 o 2 Componentes al azar
                    $cantidadSolicitada = random_int(1, 2);

                    // Se asegura de no pedir más del máximo disponible
                    $componentsToCreate = min($cantidadSolicitada, $tiposComponente->count());

                    $tiposSeleccionados = $tiposComponente->shuffle()
                        ->take($componentsToCreate)
                        ->toArray();

                    $inscripcionesDocenteComponente = [];
                    foreach ($tiposSeleccionados as $tipoComponente) {
                        $componente = Componente::create([
                            'genera_acta' => true,
                            'porcentaje_aprobacion' => 60,
                            'aprobacion_obligatoria' => false,
                            'porcentaje_asistencia_obligatoria' => 0,
                            'id_tipo_componente' => $tipoComponente,
                            'id_curso' => $curso->id_curso,
                        ]);

                        $noTieneTitulares = $componente->docentesAsignados()
                            ->wherePivot('es_titular', true)
                            ->doesntExist();

                        $inscripcionesDocenteComponente[] = DocenteComponente::create([
                            'es_titular' => $noTieneTitulares,
                            'id_docente' => $docente->id_docente,
                            'id_componente' => $componente->id_componente,
                        ]);

                        // no se le asigna el rol Docente Componente
                        // es superseeded por el rol Docente Titular a nivel curso

                        $componentesCreados++;
                    }

                    if (empty($componente)) {
                        throw new \Exception("No se pudieron crear componentes para el curso '{$curso->cod_curso}'");
                    } else if (count($tiposSeleccionados) < $componentsToCreate) {
                        throw new \Exception("Solo se pudieron crear " . count($tiposSeleccionados) . " componentes para el curso '{$curso->cod_curso}'");
                    } else {
                        echo count($tiposSeleccionados) . " componentes creados para el curso '{$curso->cod_curso}'\n";
                    }

                    if (empty($inscripcionesDocenteComponente)) {
                        throw new \Exception("No se pudieron asignar docentes a los componentes del curso '{$curso->cod_curso}'");
                    } else if (count($inscripcionesDocenteComponente) < $componentsToCreate) {
                        throw new \Exception("Solo se pudieron asignar " . count($inscripcionesDocenteComponente) . " docentes a componentes para el curso '{$curso->cod_curso}'");
                    } else {
                        echo count($inscripcionesDocenteComponente) . " docentes asignados a componentes para el curso '{$curso->cod_curso}'\n";
                    }

                    // Crear 3 Unidades
                    for ($i = 1; $i <= 3; $i++) {
                        Unidad::create([
                            'num_unidad' => $i,
                            'nombre' => "Unidad {$i}",
                            'descripcion' => "Descripción de la Unidad {$i}",
                            'id_curso' => $curso->id_curso,
                        ]);

                        $unidadesCreadas++;
                    }

                    $this->command->info("✓ Curso {$curso->cod_curso} creado con {$componentsToCreate} componentes y 3 unidades.");

                    // INSCRIPCION ALUMNOS

                    $estudiantes = Estudiante::where('id_carrera', $asignacionPlan->plan->id_carrera)
                        ->inRandomOrder()
                        ->take(10)
                        ->get();

                    $inscripciones = [];
                    foreach ($estudiantes as $estudiante) {
                        $inscripcionAlumno = InscripcionCurso::create([
                            'cod_inscripcion_uta' => 'UTA' . fake()->unique()->numberBetween(100000, 999999),
                            'num_intento' => 1,
                            'fecha_inscripcion' => now(),
                            'estado_inscripcion' => 'INSCRITO',
                            'promedio_parcial' => random_int(30, 70) / 10,
                            'id_curso' => $curso->id_curso,
                            'id_estudiante' => $estudiante->id_estudiante,
                        ]);
                        $inscripciones[] = $inscripcionAlumno;
                        // por trigger se crean InscripcionComponente para cada componente del curso
                    }

                    $noTieneInscripciones = $curso->componentes()
                        ->whereDoesntHave('inscripcionComponentes')
                        ->exists();

                    if (empty($inscripciones)) {
                        throw new \Exception("No se pudieron crear inscripciones para el curso '{$curso->cod_curso}'");
                    } else if (count($inscripciones) < $estudiantes->count()) {
                        $this->command->warn("Solo se crearon " . count($inscripciones) . " inscripciones para el curso '{$curso->cod_curso}'");
                    } else if ($noTieneInscripciones) {
                        throw new \Exception("No se crearon inscripciones a componentes para el curso '{$curso->cod_curso}'");
                    } else {
                        echo count($inscripciones) . " inscripciones creadas para el curso '{$curso->cod_curso}'\n";
                    }
                }
            } catch (\Exception $e) {
                $this->command->error("✗ Error al procesar AsignacionPlan {$asignacionPlan->id_asignacion_plan}: " . $e->getMessage());
                break; // Detener el proceso si ocurre un error para evitar datos inconsistentes
            }
        }

        $this->command->info("\n📊 Resumen:");
        $this->command->info("   Cursos creados: {$cursosCreados}");
        $this->command->info("   Componentes creados: {$componentesCreados}");
        $this->command->info("   Unidades creadas: {$unidadesCreadas}");
    }
}
