<?php

namespace App\Services;

use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Curso\TipoSeccion;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Usuario\Contexto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CursoService
{
    /**
     * Create a new course.
     *
     * @param array $data
     * @return Curso
     * @throws \Exception
     */
    public function create(array $data): Curso
    {
        return DB::transaction(function () use ($data) {
            // Find the AsignacionPlan record
            $asignacionPlan = $this->findAsignacionPlan(
                $data['id_asignatura'],
                $data['id_plan']
            );

            // Create context for the course
            $contexto = $this->createOrUpdateContext($data['cod_curso']);

            // Prepare curso data
            $cursoData = [
                'cod_curso' => $data['cod_curso'],
                'nombre' => $data['nombre'] ?? '',
                'fecha_inicio' => $data['fecha_inicio'] ?? now()->format('Y-m-d'),
                'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                'id_contexto' => $contexto->id_contexto,
                'indice_grupo' => $data['indice_grupo'] ?? 1,
            ];

            // Set fecha_fin to 6 months after fecha_inicio
            $cursoData['fecha_fin'] = $this->calculateFechaFin($cursoData['fecha_inicio']);

            $curso = Curso::create($cursoData);

            // Auto-create "Cátedra" section with suggested docente when provided
            if (!empty($data['id_docente_sugerido'])) {
                $tipoSeccion = TipoSeccion::whereRaw("LOWER(tipo) LIKE '%catedra%' OR LOWER(tipo) LIKE '%cátedra%'")->first()
                    ?? TipoSeccion::first();

                if ($tipoSeccion) {
                    Seccion::create([
                        'id_curso'        => $curso->id_curso,
                        'id_tipo_seccion' => $tipoSeccion->id_tipo_seccion,
                        'id_docente'      => $data['id_docente_sugerido'],
                    ]);
                }
            }

            return $curso;
        });
    }

    /**
     * Update an existing course.
     *
     * @param Curso $curso
     * @param array $data
     * @return Curso
     * @throws \Exception
     */
    public function update(Curso $curso, array $data): Curso
    {
        return DB::transaction(function () use ($curso, $data) {
            // Find the AsignacionPlan record
            $asignacionPlan = $this->findAsignacionPlan(
                $data['id_asignatura'],
                $data['id_plan']
            );

            // Update or create context
            $contexto = $this->createOrUpdateContext($data['cod_curso'], $curso->id_contexto);

            // Prepare update data
            $updateData = [
                'cod_curso' => $data['cod_curso'],
                'nombre' => $data['nombre'] ?? '',
                'fecha_inicio' => $data['fecha_inicio'] ?? $curso->fecha_inicio,
                'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                'id_contexto' => $contexto->id_contexto,
                'indice_grupo' => $data['indice_grupo'] ?? $curso->indice_grupo,
            ];

            // Recalculate fecha_fin if fecha_inicio changed
            if ($updateData['fecha_inicio'] !== $curso->fecha_inicio) {
                $updateData['fecha_fin'] = $this->calculateFechaFin($updateData['fecha_inicio']);
            }

            $curso->update($updateData);

            return $curso;
        });
    }

    /**
     * Find AsignacionPlan by asignatura and plan.
     *
     * @param int $idAsignatura
     * @param int $idPlan
     * @return AsignacionPlan
     * @throws \Exception
     */
    private function findAsignacionPlan(int $idAsignatura, int $idPlan): AsignacionPlan
    {
        $asignacionPlan = AsignacionPlan::where('id_asignatura', $idAsignatura)
            ->where('id_plan', $idPlan)
            ->whereNull('fecha_eliminacion')
            ->first();

        if (!$asignacionPlan) {
            throw new \Exception('No se encontró la asignación del plan.');
        }

        return $asignacionPlan;
    }

    /**
     * Create or update a course context.
     *
     * @param string $codigoCurso
     * @param int|null $contextoExistente
     * @return Contexto
     */
    private function createOrUpdateContext(string $codigoCurso, ?int $contextoExistente = null): Contexto
    {
        $nombreContexto = "Curso: " . $codigoCurso;

        // If context already exists and is not the default global context
        if ($contextoExistente && $contextoExistente != 1) {
            $contexto = Contexto::find($contextoExistente);
            if ($contexto && $contexto->contexto_display !== $nombreContexto) {
                $contexto->update(['contexto_display' => $nombreContexto]);
            }
            return $contexto;
        }

        // Create or get context
        return Contexto::firstOrCreate(
            ['contexto_display' => $nombreContexto],
            ['descripcion' => 'Contexto para el curso ' . $codigoCurso]
        );
    }

    /**
     * Calculate fecha_fin based on fecha_inicio.
     *
     * @param string $fechaInicio
     * @return string
     */
    private function calculateFechaFin(string $fechaInicio): string
    {
        $fecha = new \DateTime($fechaInicio);
        $fecha->modify('+6 months');
        return $fecha->format('Y-m-d');
    }
}
