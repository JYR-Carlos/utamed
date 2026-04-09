<?php

namespace App\Services;

use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\TipoComponente;
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
                'cod_curso'            => $data['cod_curso'],
                'nombre'               => $data['nombre'] ?? '',
                'fecha_inicio'         => $data['fecha_inicio'] ?? now()->format('Y-m-d'),
                'id_asignacion_plan'   => $asignacionPlan->id_asignacion_plan,
                'id_contexto'          => $contexto->id_contexto,
                'indice_grupo'         => $data['indice_grupo'] ?? 1,
                'id_docente_titular'   => $data['id_docente_sugerido'],
                'es_colegiado'         => $data['es_colegiado'] ?? false,
            ];

            // Set fecha_fin to 6 months after fecha_inicio
            $cursoData['fecha_fin'] = $this->calculateFechaFin($cursoData['fecha_inicio']);

            $curso = Curso::create($cursoData);

            // Find the chosen component type
            $tipoComponente = TipoComponente::find($data['id_tipo_componente_principal']);

            if ($tipoComponente) {
                $contextoComponente = $this->createOrUpdateContext($data['cod_curso'] . '-C');

                $componente = Componente::create([
                    'id_curso'                          => $curso->id_curso,
                    'id_tipo_componente'                => $tipoComponente->id_tipo_componente,
                    'id_contexto'                       => $contextoComponente->id_contexto,
                    'genera_acta'                       => $data['genera_acta'] ?? true,
                    'aprobacion_obligatoria'            => $data['aprobacion_obligatoria'] ?? false,
                    'porcentaje_aprobacion'             => $data['porcentaje_aprobacion'] ?? 60.00,
                    'porcentaje_asistencia_obligatoria' => $data['porcentaje_asistencia_obligatoria'] ?? 75.00,
                ]);

                DocenteComponente::create([
                    'id_componente' => $componente->id_componente,
                    'id_docente'    => $data['id_docente_sugerido'],
                    'es_titular'    => true,
                ]);
            }

            return $curso;
        });
    }

    /**
     * Update an existing course.
     * 
     * AUDIT: Tracks professor jefe changes and synchronizes access revocation
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

            // Store old docente_titular to sync with components
            $oldDocenteTitularId = $curso->id_docente_titular;

            // Prepare update data
            $updateData = [
                'cod_curso'          => $data['cod_curso'],
                'nombre'             => $data['nombre'] ?? '',
                'fecha_inicio'       => $data['fecha_inicio'] ?? $curso->fecha_inicio,
                'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                'id_contexto'        => $contexto->id_contexto,
                'indice_grupo'       => $data['indice_grupo'] ?? $curso->indice_grupo,
            ];

            $newDocenteTitularId = null;
            if (!empty($data['id_docente_sugerido'])) {
                $newDocenteTitularId = $data['id_docente_sugerido'];
                $updateData['id_docente_titular'] = $newDocenteTitularId;
            }

            // Recalculate fecha_fin if fecha_inicio changed
            if ($updateData['fecha_inicio'] !== $curso->fecha_inicio) {
                $updateData['fecha_fin'] = $this->calculateFechaFin($updateData['fecha_inicio']);
            }

            $curso->update($updateData);

            // Sync docente_titular roles in components if changed
            if ($newDocenteTitularId !== null && $newDocenteTitularId !== $oldDocenteTitularId) {
                $this->syncDocenteTitularInComponents($curso, $oldDocenteTitularId, $newDocenteTitularId);
            }

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

    /**
     * Synchronize docente_titular roles when professor jefe changes.
     * 
     * AUDIT LOG: Tracks all changes to professor jefe assignments
     * 
     * Removes old professor from titular roles in all course components
     * and assigns the new professor as titular to all components.
     * This ensures that when a course's titular professor changes, their
     * access is revoked from all course components immediately.
     *
     * @param Curso $curso
     * @param int $oldDocenteId
     * @param int $newDocenteId
     * @return void
     */
    private function syncDocenteTitularInComponents(Curso $curso, int $oldDocenteId, int $newDocenteId): void
    {
        // Get all components of this course
        $componentes = Componente::where('id_curso', $curso->id_curso)->get();
        
        $logData = [
            'evento' => 'CAMBIO_PROFESOR_JEFE',
            'id_curso' => $curso->id_curso,
            'cod_curso' => $curso->cod_curso,
            'id_docente_anterior' => $oldDocenteId,
            'id_docente_nuevo' => $newDocenteId,
            'total_componentes' => $componentes->count(),
            'timestamp' => now()->toIso8601String(),
            'detalles' => []
        ];

        foreach ($componentes as $componente) {
            $componenteLog = [
                'id_componente' => $componente->id_componente,
                'tipo_componente' => $componente->tipoComponente?->nombre ?? 'N/A',
                'acciones' => []
            ];

            // Remove old docente from titular roles in this component
            $removidoCount = DocenteComponente::where('id_componente', $componente->id_componente)
                ->where('id_docente', $oldDocenteId)
                ->where('es_titular', true)
                ->delete();
            
            if ($removidoCount > 0) {
                $componenteLog['acciones'][] = "REMOVIDO: Docente {$oldDocenteId} como titular (registros eliminados: {$removidoCount})";
            }

            // Add new docente as titular in this component (if not already present)
            $existingDocenteComponente = DocenteComponente::where('id_componente', $componente->id_componente)
                ->where('id_docente', $newDocenteId)
                ->first();

            if (!$existingDocenteComponente) {
                // New docente not yet in component, add as titular
                DocenteComponente::create([
                    'id_componente' => $componente->id_componente,
                    'id_docente'    => $newDocenteId,
                    'es_titular'    => true,
                ]);
                $componenteLog['acciones'][] = "ASIGNADO: Docente {$newDocenteId} como titular (registro creado)";
            } else {
                // New docente already in component, just mark as titular
                $existingDocenteComponente->update(['es_titular' => true]);
                $componenteLog['acciones'][] = "ACTUALIZADO: Docente {$newDocenteId} marcado como titular";
            }

            $logData['detalles'][] = $componenteLog;
        }

        // Log the synchronization event
        Log::channel('seguridad')->info('Sincronización de profesor jefe completada', $logData);
    }
}
