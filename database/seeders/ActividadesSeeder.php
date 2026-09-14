<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Curso\Curso;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Usuario\Estudiante;
use App\Enums\DB\TipoActividad;
use App\Enums\DB\EstadoActividadAsignada;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActividadesSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Obtener solo cursos activos reales (excluyendo plantillas y cursos cerrados del pasado)
    $cursos = Curso::where('es_plantilla', false)
      ->where('estado_interno', 'activo')
      ->get();

    if ($cursos->isEmpty()) {
      $this->command->warn('No hay cursos activos en la BD. Ejecuta BaseCursosSeeder primero.');
      return;
    }

    $actividadesCreadas = 0;
    $actividadAsignadaGrupoCreadas = 0;
    $integrantesCreados = 0;

    // Preparar datos de actividades por curso
    $actividadesParaInsertar = [];
    $cursosProcesados = [];

    foreach ($cursos as $curso) {
      $componentes = $curso->componentes()->inRandomOrder()->get();
      if ($componentes->isEmpty()) {
        continue;
      }

      $componentesIds = $componentes->pluck('id_componente')->toArray();
      // Si el curso ya tiene actividades creadas, omitir para evitar duplicados
      if (Actividad::whereIn('id_componente', $componentesIds)->exists()) {
        continue;
      }

      // Obtener cualquier unidad
      $unidades = $curso->unidades;
      if ($unidades->isEmpty()) {
        continue;
      }

      $tipos = [
        TipoActividad::SUMATIVA,
        TipoActividad::FORMATIVA
      ];

      $actividadesCurso = [];
      $unidadesUsadasIds = [];

      foreach ($componentes as $componente) {
        foreach ($tipos as $tipo) {
          // Seleccionar una unidad al azar fija para este set
          $unidadSeleccionada = $unidades->random();
          $unidadesUsadasIds[] = $unidadSeleccionada->id_unidad;

          // ACTIVIDAD GRUPAL
          $actividadesCurso[] = [
            'nombre' => 'Actividad Grupal ' . $tipo->value,
            'fecha_limite' => Carbon::now()->addDays(7),
            'visible' => true,
            'ponderacion' => 10,
            'exigencia' => 60,
            'tipo_actividad' => $tipo->value,
            'tipo_entrega' => 'online',
            'es_grupal' => true,
            'max_integrantes' => 5,
            'es_plantilla' => false,
            'id_componente' => $componente->id_componente,
            'id_unidad' => $unidadSeleccionada->id_unidad,
          ];

          // ACTIVIDAD INDIVIDUAL
          $actividadesCurso[] = [
            'nombre' => 'Actividad Individual ' . $tipo->value,
            'fecha_limite' => Carbon::now()->addDays(14),
            'visible' => true,
            'ponderacion' => 10,
            'exigencia' => 60,
            'tipo_actividad' => $tipo->value,
            'tipo_entrega' => 'online',
            'es_grupal' => false,
            'max_integrantes' => 1,
            'es_plantilla' => false,
            'id_componente' => $componente->id_componente,
            'id_unidad' => $unidadSeleccionada->id_unidad,
          ];
        }
      }

      $actividadesParaInsertar[] = [
        'curso' => $curso,
        'actividades' => $actividadesCurso,
      ];

      $cursosProcesados[] = $curso->id_curso;
    }

    // Insertar actividades en tandas
    foreach ($actividadesParaInsertar as $datoCurso) {
      try {
        $estudiantes = $datoCurso['curso']->estudiantesInscritos()->get();
        $cantidadEstudiantes = $estudiantes->count();

        if ($estudiantes->isEmpty()) {
          continue;
        }

        $curso = $datoCurso['curso'];
        $actividadesCurso = $datoCurso['actividades'];

        $actividadesInsertadas = [];
        foreach ($actividadesCurso as $actividadData) {
          $actividadesInsertadas[] = Actividad::create($actividadData);
          $actividadesCreadas++;
        }

        // Procesar asignaciones de grupos e integrantes
        foreach ($actividadesInsertadas as $index => $actividad) {
          $actividadOriginal = $actividadesCurso[$index];
          $fechaLimite = $actividadOriginal['fecha_limite'];
          $ahora = Carbon::now();

          // Calcular el estado
          if ($fechaLimite < $ahora) {
            $estado = EstadoActividadAsignada::CERRADA->value;
          } elseif ($ahora->diffInDays($fechaLimite) > 7) {
            $estado = EstadoActividadAsignada::PLANIFICADA->value;
          } else {
            $estado = EstadoActividadAsignada::ACTIVA->value;
          }

          // CALCULAR DATOS REALES DE GRUPOS
          if ($actividadOriginal['es_grupal']) {
            $maxIntegrantes = 5;
            $numGrupos = (int) ceil($cantidadEstudiantes / $maxIntegrantes);
          } else {
            $maxIntegrantes = 1;
            $numGrupos = $cantidadEstudiantes; // Un grupo exacto por alumno
          }

          // 1. Crear los grupos primero en la base de datos para obtener sus IDs reales generados por Postgres
          $asignacionesGrupo = [];
          for ($i = 1; $i <= $numGrupos; $i++) {
            $nombreGrupo = $maxIntegrantes === 1 ? "Estudiante $i" : "Grupo $i";
            $asignacionesGrupo[] = [
              'nombre_grupo' => $nombreGrupo,
              'nota' => null,
              'estado_actividad_asignada' => $estado,
              'id_actividad' => $actividad->id_actividad,
            ];
          }

          foreach (array_chunk($asignacionesGrupo, 10) as $chunk) {
            DB::table('actividad_asignada_grupo')->insert($chunk);
            $actividadAsignadaGrupoCreadas += count($chunk);
          }

          // Recuperar los grupos recién creados de la BD
          $asignacionesInsertadas = ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
            ->orderBy('id_actividad_asignada_grupo', 'asc')
            ->get();

          // 2. REPARTICIÓN REAL DE ALUMNOS (Sin repeticiones ni vacíos)
          // Desordenamos los estudiantes al inicio de la actividad
          $estudiantesMezclados = $estudiantes->shuffle();
          $integrantesParaInsertar = [];

          foreach ($asignacionesInsertadas as $grupoIndex => $asignacion) {
            
            // Cortamos el lote de alumnos que le corresponde a este grupo
            // chunk() de colecciones mantiene el control y evita que un alumno se repita en otro grupo
            $alumnosDelGrupo = $estudiantesMezclados->slice($grupoIndex * $maxIntegrantes, $maxIntegrantes);

            foreach ($alumnosDelGrupo as $estudiante) {
              $integrantesParaInsertar[] = [
                'nota_individual' => null,
                'diferencia_decimas' => 0,
                'id_actividad_asignada_grupo' => $asignacion->id_actividad_asignada_grupo,
                'id_estudiante' => $estudiante->id_estudiante,
              ];
            }
          }

          // Insertar todos los integrantes de la actividad en batch
          foreach (array_chunk($integrantesParaInsertar, 50) as $chunk) {
            DB::table('integrante_grupo')->insert($chunk);
            $integrantesCreados += count($chunk);
          }
        }

        $this->command->info("✓ Actividades para curso {$curso->cod_curso} creadas exitosamente con distribución real.");
      } catch (\Exception $e) {
        $this->command->error("✗ Error al procesar Curso {$datoCurso['curso']->cod_curso}: " . $e->getMessage());
        break; 
      }
    }

    $this->command->info("\n📊 Resumen:");
    $this->command->info("   Actividades creadas: {$actividadesCreadas}");
    $this->command->info("   ActividadAsignadaGrupo creadas: {$actividadAsignadaGrupoCreadas}");
    $this->command->info("   Integrantes de Grupo creados: {$integrantesCreados}");
  }
}
