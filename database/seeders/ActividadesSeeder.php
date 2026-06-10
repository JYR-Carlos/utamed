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
    // Obtener todos los cursos
    $cursos = Curso::all();

    if ($cursos->isEmpty()) {
      $this->command->warn('No hay cursos en la BD. Ejecuta CursosSeeder primero.');
      return;
    }

    // Obtener todos los estudiantes
    $estudiantes = Estudiante::all();

    if ($estudiantes->isEmpty()) {
      $this->command->warn('No hay estudiantes en la BD.');
      return;
    }

    $actividadesCreadas = 0;
    $actividadAsignadaGrupoCreadas = 0;
    $integrantesCreados = 0;

    // Preparar datos de actividades por curso
    $actividadesParaInsertar = [];
    $cursosProcesados = [];

    foreach ($cursos as $curso) {
      // Obtener una unidad y componente del curso
      $unidad = $curso->unidades()->first();
      $componente = $curso->componentes()->first();

      if (!$unidad || !$componente) {
        $this->command->warn("Curso {$curso->cod_curso} no tiene unidades o componentes. Saltando...");
        continue;
      }

      $tipos = [
        TipoActividad::SUMATIVA,
        TipoActividad::FORMATIVA
      ];

      $actividadesCurso = [];

      foreach ($tipos as $tipo) {
        // ACTIVIDAD GRUPAL
        $actividadesCurso[] = [
          'nombre' => 'Actividad Grupal ' . $tipo->value,
          'fecha_limite' => Carbon::now()->addDays(7),
          'visible' => true,
          'ponderacion' => 10,
          'exigencia' => 60,
          'tipo_actividad' => $tipo->value,
          'tipo_entrega' => 'archivo',
          'es_grupal' => true,
          'max_integrantes' => 5,
          'es_plantilla' => false,
          'id_componente' => $componente->id_componente,
          'id_unidad' => $unidad->id_unidad,
        ];

        // ACTIVIDAD INDIVIDUAL
        $actividadesCurso[] = [
          'nombre' => 'Actividad Individual ' . $tipo->value,
          'fecha_limite' => Carbon::now()->addDays(14),
          'visible' => true,
          'ponderacion' => 10,
          'exigencia' => 60,
          'tipo_actividad' => $tipo->value,
          'tipo_entrega' => 'archivo',
          'es_grupal' => false,
          'max_integrantes' => 1,
          'es_plantilla' => false,
          'id_componente' => $componente->id_componente,
          'id_unidad' => $unidad->id_unidad,
        ];
      }

      $actividadesParaInsertar[] = [
        'curso' => $curso,
        'unidad' => $unidad,
        'componente' => $componente,
        'actividades' => $actividadesCurso,
      ];

      $cursosProcesados[] = $curso->id_curso;
    }

    // Insertar actividades en tandas
    foreach ($actividadesParaInsertar as $datoCurso) {
      try {
        $curso = $datoCurso['curso'];
        $actividadesCurso = $datoCurso['actividades'];

        // Insertar las 4 actividades del curso en una sola query
        DB::table('actividad')->insert($actividadesCurso);
        $actividadesCreadas += count($actividadesCurso);

        // Obtener las actividades insertadas para este curso
        $actividadesInsertadas = Actividad::where('id_componente', $datoCurso['componente']->id_componente)
          ->where('id_unidad', $datoCurso['unidad']->id_unidad)
          ->orderByDesc('id_actividad')
          ->take(4)
          ->get();

        // Procesar asignaciones de grupos e integrantes
        foreach ($actividadesInsertadas as $index => $actividad) {
          // Obtener la actividad original del array $actividadesCurso
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

          if ($index < 2) {
            // Actividades grupales (índices 0 y 1)
            $numGrupos = 5;
            $numIntegrantesGrupo = 5;
          } else {
            // Actividades individuales (índices 2 y 3)
            $numGrupos = 20;
            $numIntegrantesGrupo = 1;
          }

          // Preparar datos para ActividadAsignadaGrupo
          $asignacionesGrupo = [];
          for ($i = 1; $i <= $numGrupos; $i++) {
            $nombreGrupo = $numIntegrantesGrupo === 1 ? "Estudiante $i" : "Grupo $i";
            $asignacionesGrupo[] = [
              'nombre_grupo' => $nombreGrupo,
              'nota' => null,
              'estado_actividad_asignada' => $estado,  // Usar el estado calculado
              'id_actividad' => $actividad->id_actividad,
            ];
          }

          // Insertar asignaciones en batch
          $asignacionesInsertadas = [];
          foreach (array_chunk($asignacionesGrupo, 10) as $chunk) {
            DB::table('actividad_asignada_grupo')->insert($chunk);
            $actividadAsignadaGrupoCreadas += count($chunk);
          }

          // Obtener las asignaciones insertadas
          $asignacionesInsertadas = ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
            ->get();

          // Preparar integrantes por grupo
          $integrantesParaInsertar = [];
          foreach ($asignacionesInsertadas as $asignacion) {
            if ($numIntegrantesGrupo === 1) {
              // Para actividades individuales: 1 estudiante al azar
              $estudiante = $estudiantes->random();
              $integrantesParaInsertar[] = [
                'nota_individual' => null,
                'diferencia_decimas' => 0,
                'id_actividad_asignada_grupo' => $asignacion->id_actividad_asignada_grupo,
                'id_estudiante' => $estudiante->id_estudiante,
              ];
            } else {
              // Para actividades grupales: 5 estudiantes únicos al azar
              $estudiantesUnicos = $estudiantes->random($numIntegrantesGrupo);
              foreach ($estudiantesUnicos as $estudiante) {
                $integrantesParaInsertar[] = [
                  'nota_individual' => null,
                  'diferencia_decimas' => 0,
                  'id_actividad_asignada_grupo' => $asignacion->id_actividad_asignada_grupo,
                  'id_estudiante' => $estudiante->id_estudiante,
                ];
              }
            }
          }

          // Insertar integrantes en batch
          foreach (array_chunk($integrantesParaInsertar, 50) as $chunk) {
            DB::table('integrante_grupo')->insert($chunk);
            $integrantesCreados += count($chunk);
          }
        }

        $this->command->info("✓ Actividades para curso {$curso->cod_curso} creadas exitosamente.");
      } catch (\Exception $e) {
        $this->command->error("✗ Error al procesar Curso {$datoCurso['curso']->cod_curso}: " . $e->getMessage());
        break; // Detener el proceso si ocurre un error para evitar datos inconsistentes
      }
    }

    $this->command->info("\n📊 Resumen:");
    $this->command->info("   Actividades creadas: {$actividadesCreadas}");
    $this->command->info("   ActividadAsignadaGrupo creadas: {$actividadAsignadaGrupoCreadas}");
    $this->command->info("   Integrantes de Grupo creados: {$integrantesCreados}");
  }
}
