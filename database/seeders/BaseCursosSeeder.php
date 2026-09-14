<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\Unidad;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Services\Authorization\RoleAssignmentBuilder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Seeder para la creación estructurada de cursos e inscripciones.
 * 
 * Reglas de negocio aplicadas:
 * - Cursos plantilla: 1 por cada AsignacionPlan, sin alumnos inscritos.
 * - Bolsas de cohortes: 10 alumnos por año de carrera.
 * - Cursos de "Hoy": Cursos activos del período actual, máximo 6 por alumno, estado INSCRITO.
 * - Cursos de "Antes": Cursos históricos cerrados según los semestres previos de la cohorte,
 *   con calificaciones pasadas (mayoría APROBADO, algunos REPROBADO).
 * - Tamaño de curso: Grupos compactos de 10 estudiantes por sección.
 */
class BaseCursosSeeder extends Seeder
{
    private Collection $tiposComponente;
    private Collection $docentes;
    private ?Rol $rolDocenteTitular = null;
    private ?Usuario $superAdmin = null;

    private int $cursosCreados = 0;
    private int $componentesCreados = 0;
    private int $unidadesCreadas = 0;
    private int $inscripcionesCreadas = 0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Iniciando BaseCursosSeeder con arquitectura de cohortes y cursos de 10 personas...');

        // 1. Cargar dependencias en memoria
        $this->tiposComponente = TipoComponente::pluck('id_tipo_componente');
        if ($this->tiposComponente->isEmpty()) {
            $this->command->warn('No hay tipos de componente en la BD. Ejecuta TipoComponenteSeeder primero.');
            return;
        }

        $this->docentes = Docente::whereHas('usuario')->with('usuario')->get();
        if ($this->docentes->isEmpty()) {
            $this->command->warn('No hay docentes en la BD. Creando docentes de respaldo...');
            Usuario::factory(10)->docente()->create();
            $this->docentes = Docente::whereHas('usuario')->with('usuario')->get();
        }

        $this->rolDocenteTitular = Rol::where('nombre', 'Docente Titular')->first();
        $this->superAdmin = Usuario::where('username', 'superadmin')->first();

        // 2. Crear Cursos Plantilla (1 por AsignacionPlan, sin alumnos)
        $this->crearCursosPlantilla();

        // 3. Crear Cursos Reales (Hoy y Antes) con Bolsas de 10 Alumnos
        $this->crearCursosPorCohortes();

        $this->command->info("\n📊 Resumen General de BaseCursosSeeder:");
        $this->command->info("   Cursos creados: {$this->cursosCreados}");
        $this->command->info("   Componentes creados: {$this->componentesCreados}");
        $this->command->info("   Unidades creadas: {$this->unidadesCreadas}");
        $this->command->info("   Inscripciones creadas: {$this->inscripcionesCreadas}");
    }

    /**
     * Genera cursos plantilla para cada AsignacionPlan si no existen.
     * No inscribe alumnos en cursos plantilla.
     */
    private function crearCursosPlantilla(): void
    {
        $asignacionesPlanes = AsignacionPlan::with('asignatura')->get();
        $plantillasCreadas = 0;

        foreach ($asignacionesPlanes as $asignacionPlan) {
            $cursoExistente = Curso::where('id_asignacion_plan', $asignacionPlan->id_asignacion_plan)
                ->where('es_plantilla', true)
                ->first();

            if ($cursoExistente) {
                $docente = $cursoExistente->docenteTitular;
                if ($docente) {
                    $this->asignarRolDocenteTitular($cursoExistente, $docente);
                }
                continue;
            }

            try {
                $docente = $this->docentes->random();
                $nombreAsignatura = $asignacionPlan->asignatura?->nombre ?? 'Asignatura';

                $curso = Curso::create([
                    'cod_curso' => $this->generarCodCursoUnico(),
                    'nombre' => 'Plantilla - ' . $nombreAsignatura,
                    'fecha_inicio' => now()->startOfYear(),
                    'fecha_fin' => now()->endOfYear(),
                    'agno_real' => now()->year,
                    'semestre_real' => $asignacionPlan->semestre_planificado,
                    'estado_interno' => 'activo',
                    'estado_acta' => 'pendiente',
                    'es_plantilla' => true,
                    'es_colegiado' => false,
                    'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                    'id_docente_titular' => $docente->id_docente,
                ])->refresh();

                $this->asignarRolDocenteTitular($curso, $docente);
                $this->crearComponentesYUnidades($curso, $docente);

                $plantillasCreadas++;
                $this->cursosCreados++;
            } catch (\Exception $e) {
                $this->command->error("✗ Error al crear plantilla para AsignacionPlan {$asignacionPlan->id_asignacion_plan}: " . $e->getMessage());
            }
        }

        $this->command->info("✓ Cursos Plantilla verificados/creados: {$plantillasCreadas}");
    }

    /**
     * Crea los cursos reales para cada cohorte (bolsa de año) tanto para el presente como para el pasado.
     */
    private function crearCursosPorCohortes(): void
    {
        $carrerasConPlanes = Carrera::whereHas('planes.asignacionPlanes')
            ->with(['planes' => function ($q) {
                $q->with(['asignacionPlanes.asignatura']);
            }])
            ->get();

        $agnoActual = now()->year;
        // Si el mes actual es >= 8 (Agosto en adelante), estamos en Semestre 2.
        $semestreActual = now()->month >= 8 ? 2 : 1;

        foreach ($carrerasConPlanes as $carrera) {
            $this->command->info("\n🎓 Procesando Carrera: {$carrera->nombre}");

            foreach ($carrera->planes as $plan) {
                $agnosPlanificados = $plan->asignacionPlanes
                    ->pluck('agno_planificado')
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($agnosPlanificados as $agno) {
                    $agnoIngreso = $agnoActual - ($agno - 1);
                    $estudiantesBolsa = $this->obtenerOCrearEstudiantesBolsa($carrera, $agnoIngreso);

                    $this->command->line("  -> Cohorte Año {$agno} (Ingreso {$agnoIngreso}, {$estudiantesBolsa->count()} estudiantes):");

                    // ----------------------------------------------------
                    // 1. CURSOS DE 'HOY' (Activos, Semestre Actual, max 6)
                    // ----------------------------------------------------
                    $asignacionesHoy = $plan->asignacionPlanes
                        ->where('agno_planificado', $agno)
                        ->where('semestre_planificado', $semestreActual)
                        ->take(6);

                    // Si no hubiese asignaciones específicas para ese semestre en ese año, tomar hasta 6 del año
                    if ($asignacionesHoy->isEmpty()) {
                        $asignacionesHoy = $plan->asignacionPlanes
                            ->where('agno_planificado', $agno)
                            ->take(6);
                    }

                    $fechaInicioHoy = $semestreActual === 1 ? Carbon::create($agnoActual, 3, 15) : Carbon::create($agnoActual, 8, 10);
                    $fechaFinHoy = $semestreActual === 1 ? Carbon::create($agnoActual, 7, 20) : Carbon::create($agnoActual, 12, 20);

                    foreach ($asignacionesHoy as $asignacionPlan) {
                        $docente = $this->docentes->random();
                        $nombreAsignatura = $asignacionPlan->asignatura?->nombre ?? 'Asignatura';

                        $cursoHoy = Curso::firstOrCreate(
                            [
                                'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                                'agno_real' => $agnoActual,
                                'semestre_real' => $semestreActual,
                                'es_plantilla' => false,
                            ],
                            [
                                'cod_curso' => $this->generarCodCursoUnico(),
                                'nombre' => $nombreAsignatura . ' (Sección 1)',
                                'fecha_inicio' => $fechaInicioHoy,
                                'fecha_fin' => $fechaFinHoy,
                                'estado_interno' => 'activo',
                                'estado_acta' => 'pendiente',
                                'es_colegiado' => false,
                                'id_docente_titular' => $docente->id_docente,
                            ]
                        )->refresh();

                        if ($cursoHoy->wasRecentlyCreated) {
                            $this->crearComponentesYUnidades($cursoHoy, $docente);
                            $this->cursosCreados++;
                        }

                        $this->asignarRolDocenteTitular($cursoHoy, $docente);

                        // Inscribir a los 10 estudiantes de la cohorte en el curso actual
                        foreach ($estudiantesBolsa as $estudiante) {
                            $yaInscrito = InscripcionCurso::where('id_curso', $cursoHoy->id_curso)
                                ->where('id_estudiante', $estudiante->id_estudiante)
                                ->exists();

                            if (!$yaInscrito) {
                                InscripcionCurso::create([
                                    'cod_inscripcion_uta' => $this->generarCodInscripcionUtaUnico(),
                                    'num_intento' => 1,
                                    'fecha_inscripcion' => $fechaInicioHoy,
                                    'estado_inscripcion' => 'INSCRITO',
                                    'promedio_parcial' => round(fake()->randomFloat(1, 3.5, 6.8), 1),
                                    'id_curso' => $cursoHoy->id_curso,
                                    'id_estudiante' => $estudiante->id_estudiante,
                                ]);
                                $this->inscripcionesCreadas++;
                            }
                        }
                    }
                    $this->command->line("     ✓ Cursos de HOY procesados: {$asignacionesHoy->count()} (cada estudiante con max 6 inscritos)");

                    // ----------------------------------------------------
                    // 2. CURSOS DE 'ANTES' (Historial cerrado, previo a hoy)
                    // ----------------------------------------------------
                    $cursosAntesCreados = 0;
                    for ($y = 1; $y <= $agno; $y++) {
                        for ($s = 1; $s <= 2; $s++) {
                            // ¿Es estrictamente anterior al período actual de esta cohorte?
                            $esPrevio = ($y < $agno) || ($y == $agno && $s < $semestreActual);
                            if (!$esPrevio) {
                                continue;
                            }

                            // Calcular año y semestre calendario en el que se cursó
                            $semestersAgo = (($agno - $y) * 2) + ($semestreActual - $s);
                            $pastHalfYears = (($agnoActual * 2) + ($semestreActual - 1)) - $semestersAgo;
                            $pastCalYear = intdiv($pastHalfYears, 2);
                            $pastCalSem = ($pastHalfYears % 2) + 1;

                            $fechaInicioPasada = $pastCalSem === 1 ? Carbon::create($pastCalYear, 3, 15) : Carbon::create($pastCalYear, 8, 10);
                            $fechaFinPasada = $pastCalSem === 1 ? Carbon::create($pastCalYear, 7, 20) : Carbon::create($pastCalYear, 12, 20);

                            $asignacionesPasadas = $plan->asignacionPlanes
                                ->where('agno_planificado', $y)
                                ->where('semestre_planificado', $s)
                                ->take(6);

                            foreach ($asignacionesPasadas as $asignacionPlan) {
                                $docente = $this->docentes->random();
                                $nombreAsignatura = $asignacionPlan->asignatura?->nombre ?? 'Asignatura';

                                $cursoPasado = Curso::firstOrCreate(
                                    [
                                        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                                        'agno_real' => $pastCalYear,
                                        'semestre_real' => $pastCalSem,
                                        'es_plantilla' => false,
                                    ],
                                    [
                                        'cod_curso' => $this->generarCodCursoUnico(),
                                        'nombre' => $nombreAsignatura . " ({$pastCalYear}-{$pastCalSem})",
                                        'fecha_inicio' => $fechaInicioPasada,
                                        'fecha_fin' => $fechaFinPasada,
                                        'estado_interno' => 'cerrado',
                                        'estado_acta' => 'enviada',
                                        'es_colegiado' => false,
                                        'id_docente_titular' => $docente->id_docente,
                                    ]
                                )->refresh();

                                if ($cursoPasado->wasRecentlyCreated) {
                                    $this->crearComponentesYUnidades($cursoPasado, $docente);
                                    $this->cursosCreados++;
                                    $cursosAntesCreados++;
                                }

                                $this->asignarRolDocenteTitular($cursoPasado, $docente);

                                // Inscribir a los 10 estudiantes con notas históricas (mayoría APROBADO, algunos REPROBADO)
                                foreach ($estudiantesBolsa as $estudiante) {
                                    $yaInscrito = InscripcionCurso::where('id_curso', $cursoPasado->id_curso)
                                        ->where('id_estudiante', $estudiante->id_estudiante)
                                        ->exists();

                                    if (!$yaInscrito) {
                                        // 10% de probabilidad de haber reprobado el ramo
                                        $esReprobado = (random_int(1, 10) === 1);
                                        $estado = $esReprobado ? 'REPROBADO' : 'APROBADO';
                                        $promedio = $esReprobado
                                            ? round(fake()->randomFloat(1, 2.0, 3.8), 1)
                                            : round(fake()->randomFloat(1, 4.0, 6.8), 1);

                                        InscripcionCurso::create([
                                            'cod_inscripcion_uta' => $this->generarCodInscripcionUtaUnico(),
                                            'num_intento' => 1,
                                            'fecha_inscripcion' => $fechaInicioPasada,
                                            'estado_inscripcion' => $estado,
                                            'promedio_parcial' => $promedio,
                                            'id_curso' => $cursoPasado->id_curso,
                                            'id_estudiante' => $estudiante->id_estudiante,
                                        ]);
                                        $this->inscripcionesCreadas++;
                                    }
                                }
                            }
                        }
                    }
                    $this->command->line("     ✓ Cursos de ANTES procesados: {$cursosAntesCreados} creados/verificados");
                }
            }
        }
    }

    /**
     * Obtiene los 10 estudiantes de la bolsa por carrera y año de ingreso,
     * creándolos si no existiesen suficientes.
     */
    private function obtenerOCrearEstudiantesBolsa(Carrera $carrera, int $agnoIngreso): Collection
    {
        $estudiantes = Estudiante::where('id_carrera', $carrera->id_carrera)
            ->where('agno_ingreso', $agnoIngreso)
            ->take(10)
            ->get();

        $faltantes = 10 - $estudiantes->count();
        if ($faltantes > 0) {
            for ($i = 0; $i < $faltantes; $i++) {
                Usuario::factory()
                    ->has(
                        \Database\Factories\Usuario\EstudianteFactory::new()->state([
                            'id_carrera' => $carrera->id_carrera,
                            'agno_ingreso' => $agnoIngreso,
                        ]),
                        'estudiante'
                    )
                    ->withRolEstudiante()
                    ->create();
            }

            $estudiantes = Estudiante::where('id_carrera', $carrera->id_carrera)
                ->where('agno_ingreso', $agnoIngreso)
                ->take(10)
                ->get();
        }

        return $estudiantes;
    }

    /**
     * Crea componentes y unidades para un curso.
     */
    private function crearComponentesYUnidades(Curso $curso, Docente $docente): void
    {
        // 1 o 2 componentes al azar
        $cantidadComponentes = min(random_int(1, 2), $this->tiposComponente->count());
        $tiposSeleccionados = $this->tiposComponente->shuffle()->take($cantidadComponentes);

        foreach ($tiposSeleccionados as $tipoComponente) {
            $componente = Componente::create([
                'genera_acta' => true,
                'porcentaje_aprobacion' => 60,
                'aprobacion_obligatoria' => false,
                'porcentaje_asistencia_obligatoria' => 0,
                'id_tipo_componente' => $tipoComponente,
                'id_curso' => $curso->id_curso,
            ]);

            DocenteComponente::create([
                'es_titular' => true,
                'id_docente' => $docente->id_docente,
                'id_componente' => $componente->id_componente,
            ]);

            $this->componentesCreados++;
        }

        // 3 Unidades por curso
        for ($i = 1; $i <= 3; $i++) {
            Unidad::create([
                'num_unidad' => $i,
                'nombre' => "Unidad {$i}",
                'descripcion' => "Descripción de la Unidad {$i}",
                'id_curso' => $curso->id_curso,
            ]);

            $this->unidadesCreadas++;
        }
    }

    /**
     * Asigna el rol de Docente Titular en el contexto del curso.
     */
    private function asignarRolDocenteTitular(Curso $curso, ?Docente $docente = null): void
    {
        if (!$this->rolDocenteTitular || !$this->superAdmin) {
            throw new \Exception("No se encontró el rol 'Docente Titular' o el usuario 'superadmin'.");
        }

        $docenteEfectivo = $curso->docenteTitular ?? $docente;
        if (!$docenteEfectivo) {
            throw new \Exception("El curso '{$curso->nombre}' no tiene docente titular asignado.");
        }

        $usuarioDocente = $docenteEfectivo->usuario;
        if (!$usuarioDocente) {
            throw new \Exception("El docente {$docenteEfectivo->id_docente} no tiene un usuario asociado.");
        }

        if (!$curso->id_contexto) {
            throw new \Exception("El curso '{$curso->nombre}' no tiene un contexto asociado.");
        }

        $yaTieneRol = \App\Models\Usuario\UsuarioRolAsignacion::where('id_usuario', $usuarioDocente->id_usuario)
            ->where('id_rol', $this->rolDocenteTitular->id_rol)
            ->where('id_contexto', $curso->id_contexto)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->exists();

        if (!$yaTieneRol) {
            $asignacionRol = (new RoleAssignmentBuilder($usuarioDocente, $this->rolDocenteTitular, $this->superAdmin))
                ->on($curso)
                ->save();

            if (!$asignacionRol) {
                throw new \Exception("Error al asignar rol de Docente Titular a usuario '{$usuarioDocente->username}' para el curso '{$curso->cod_curso}'");
            }
        }
    }

    /**
     * Genera un código de curso único de 9 dígitos.
     */
    private function generarCodCursoUnico(): int
    {
        do {
            $cod = random_int(100000000, 999999999);
        } while (Curso::where('cod_curso', $cod)->exists());

        return $cod;
    }

    /**
     * Genera un código de inscripción único tipo UTA123456.
     */
    private function generarCodInscripcionUtaUnico(): string
    {
        do {
            $cod = 'UTA' . random_int(100000, 999999);
        } while (InscripcionCurso::where('cod_inscripcion_uta', $cod)->exists());

        return $cod;
    }
}
