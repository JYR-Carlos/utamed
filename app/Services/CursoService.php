<?php

namespace App\Services;

use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\TipoComponente;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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

            $agnoReal = $data['agno_real'] ?? null;
            $semestreReal = $data['semestre_real'] ?? 2;

            // Docente asignado a cada componente (clave: id_tipo_componente, valor: id_docente).
            $docentesPorComponente = $data['docentes_por_componente'] ?? [];

            // El curso es colegiado si hay más de un docente distinto entre los
            // componentes asignados. No es una opción manual: se deriva de la
            // asignación real de docentes por componente.
            $docentesUnicos = collect($docentesPorComponente)->filter()->unique()->count();
            $esColegiado = $docentesUnicos > 1;

            // Letra de grupo (indice_grupo): si viene explícita desde el wizard
            // (selección manual), se respeta y valida (ver StoreCursoRequest);
            // si no, se calcula la próxima disponible para ese periodo.
            $indiceGrupo = !empty($data['indice_grupo'])
                ? (int) $data['indice_grupo']
                : $this->nextIndiceGrupo($asignacionPlan->id_asignacion_plan, $agnoReal, $semestreReal);

            // Prepare curso data
            $cursoData = [
                'cod_curso'            => $data['cod_curso'],
                'nombre'               => $data['nombre'] ?? '',
                'fecha_inicio'         => $data['fecha_inicio'] ?? now()->format('Y-m-d'),
                'agno_real'            => $agnoReal,
                'semestre_real'        => $semestreReal,
                'id_asignacion_plan'   => $asignacionPlan->id_asignacion_plan,
                'id_contexto'          => $contexto->id_contexto,
                'indice_grupo'         => $indiceGrupo,
                'id_docente_titular'   => $data['id_docente_sugerido'],
                'es_colegiado'         => $esColegiado,
            ];

            // Set fecha_fin to 6 months after fecha_inicio
            $cursoData['fecha_fin'] = $this->calculateFechaFin($cursoData['fecha_inicio']);

            $curso = Curso::create($cursoData);

            // Asignar rol 'Docente Titular' al docente en el contexto del curso SIEMPRE,
            // independientemente de si imparte clases directamente en el componente.
            // Usamos $contexto->id_contexto (ya creado) en lugar de $curso->id_contexto
            // para garantizar que el valor esté disponible.
            if (!empty($data['id_docente_sugerido'])) {
                $this->assignDocenteRolCurso($data['id_docente_sugerido'], $contexto->id_contexto, true);
            }

            // Find the chosen component type
            $tipoComponente = TipoComponente::find($data['id_tipo_componente_principal']);

            $generaActaPorComponente = $data['genera_acta_por_componente'] ?? [];

            if ($tipoComponente) {
                $contextoComponente = $this->createOrUpdateContext($data['cod_curso'] . '-C');

                $componente = Componente::create([
                    'id_curso'                          => $curso->id_curso,
                    'id_tipo_componente'                => $tipoComponente->id_tipo_componente,
                    'id_contexto'                       => $contextoComponente->id_contexto,
                    'genera_acta'                       => $generaActaPorComponente[$tipoComponente->id_tipo_componente] ?? true,
                    'aprobacion_obligatoria'            => $data['aprobacion_obligatoria'] ?? false,
                    'porcentaje_aprobacion'             => $data['porcentaje_aprobacion'] ?? 60.00,
                    'porcentaje_asistencia_obligatoria' => $data['porcentaje_asistencia_obligatoria'] ?? 75.00,
                ]);

                // El jefe SIEMPRE tiene acceso administrativo (via id_docente_titular),
                // independientemente de si dicta clases en algún componente.
                $idDocenteComponente = $docentesPorComponente[$tipoComponente->id_tipo_componente] ?? null;
                if ($idDocenteComponente) {
                    DocenteComponente::create([
                        'id_componente' => $componente->id_componente,
                        'id_docente'    => $idDocenteComponente,
                        'es_titular'    => true,
                    ]);
                }

                // Crear componentes adicionales (distintos al principal)
                $tiposAdicionales = array_filter(
                    $data['tipos_componente_ids'] ?? [],
                    fn($id) => (int) $id !== (int) $tipoComponente->id_tipo_componente
                );

                foreach ($tiposAdicionales as $tipoId) {
                    $tipoAdicional = TipoComponente::find((int) $tipoId);
                    if (!$tipoAdicional) {
                        continue;
                    }

                    $ctxAdicional = $this->createOrUpdateContext(
                        $data['cod_curso'] . '-' . $tipoAdicional->id_tipo_componente
                    );

                    $componenteAdicional = Componente::create([
                        'id_curso'                          => $curso->id_curso,
                        'id_tipo_componente'                => $tipoAdicional->id_tipo_componente,
                        'id_contexto'                       => $ctxAdicional->id_contexto,
                        'genera_acta'                       => $generaActaPorComponente[$tipoAdicional->id_tipo_componente] ?? true,
                        'aprobacion_obligatoria'            => $data['aprobacion_obligatoria'] ?? false,
                        'porcentaje_aprobacion'             => $data['porcentaje_aprobacion'] ?? 60.00,
                        'porcentaje_asistencia_obligatoria' => $data['porcentaje_asistencia_obligatoria'] ?? 75.00,
                    ]);

                    $idDocenteAdicional = $docentesPorComponente[$tipoAdicional->id_tipo_componente] ?? null;
                    if ($idDocenteAdicional) {
                        DocenteComponente::create([
                            'id_componente' => $componenteAdicional->id_componente,
                            'id_docente'    => $idDocenteAdicional,
                            'es_titular'    => true,
                        ]);
                    }
                }
            }

            return $curso;
        });
    }

    /**
     * Copy an existing course preserving structure, components, teachers and activities.
     * Activity dates are reset to null. Students and responses are NOT copied.
     *
     * @param Curso $cursoPadre The source course
     * @param array $data Must contain 'cod_curso' (int) and 'fecha_inicio' (Y-m-d string)
     * @return Curso
     */
    public function copiar(Curso $cursoPadre, array $data): Curso
    {
        return DB::transaction(function () use ($cursoPadre, $data) {
            $cursoPadre->load([
                'componentes.tipoComponente',
                'componentes.docenteComponentes',
                'componentes.actividades',
            ]);

            $contexto = $this->createOrUpdateContext((string) $data['cod_curso']);
            $fechaInicio = $data['fecha_inicio'];

            // No reutilizamos el indice_grupo del padre: el trigger de BD ya no
            // lo sobrescribe automáticamente, así que copiar el mismo valor
            // colisionaría con la letra del curso origen. Como esta copia no
            // fija agno_real/semestre_real (quedan null, igual que el padre en
            // este flujo), calculamos la próxima letra disponible en ese mismo
            // scope (id_asignacion_plan, null, null).
            $indiceGrupo = $this->nextIndiceGrupo($cursoPadre->id_asignacion_plan, null, null);

            $nuevoCurso = Curso::create([
                'cod_curso'           => $data['cod_curso'],
                'nombre'              => $cursoPadre->nombre,
                'fecha_inicio'        => $fechaInicio,
                'fecha_fin'           => $this->calculateFechaFin($fechaInicio),
                'id_asignacion_plan'  => $cursoPadre->id_asignacion_plan,
                'id_contexto'         => $contexto->id_contexto,
                'id_docente_titular'  => $cursoPadre->id_docente_titular,
                'es_colegiado'        => $cursoPadre->es_colegiado,
                'indice_grupo'        => $indiceGrupo,
                'id_curso_padre'      => $cursoPadre->id_curso,
            ]);

            // Assign titular role in the new course context
            if ($cursoPadre->id_docente_titular) {
                $this->assignDocenteRolCurso(
                    $cursoPadre->id_docente_titular,
                    $contexto->id_contexto,
                    true
                );
            }

            foreach ($cursoPadre->componentes as $comp) {
                $ctxComp = $this->createOrUpdateContext(
                    $data['cod_curso'] . '-' . $comp->id_tipo_componente
                );

                $nuevoComp = Componente::create([
                    'id_curso'                          => $nuevoCurso->id_curso,
                    'id_tipo_componente'                => $comp->id_tipo_componente,
                    'id_contexto'                       => $ctxComp->id_contexto,
                    'genera_acta'                       => $comp->genera_acta,
                    'aprobacion_obligatoria'            => $comp->aprobacion_obligatoria,
                    'porcentaje_aprobacion'             => $comp->porcentaje_aprobacion,
                    'porcentaje_asistencia_obligatoria' => $comp->porcentaje_asistencia_obligatoria,
                ]);

                foreach ($comp->docenteComponentes as $dc) {
                    DocenteComponente::create([
                        'id_componente' => $nuevoComp->id_componente,
                        'id_docente'    => $dc->id_docente,
                        'es_titular'    => $dc->es_titular,
                    ]);
                }

                foreach ($comp->actividades as $act) {
                    \App\Models\Agenda\Actividad::create([
                        'nombre'          => $act->nombre,
                        'fecha_limite'    => null,
                        'visible'         => $act->visible,
                        'ponderacion'     => $act->ponderacion,
                        'exigencia'       => $act->exigencia,
                        'tipo_actividad'  => $act->tipo_actividad instanceof \App\Enums\DB\TipoActividad
                            ? $act->tipo_actividad->value
                            : $act->tipo_actividad,
                        'tipo_entrega'    => $act->tipo_entrega,
                        'es_grupal'       => $act->es_grupal,
                        'max_integrantes' => $act->max_integrantes,
                        'es_plantilla'    => false,
                        'id_componente'   => $nuevoComp->id_componente,
                    ]);
                }
            }

            Log::info('[CursoService@copiar] Curso copiado', [
                'id_curso_padre' => $cursoPadre->id_curso,
                'id_curso_nuevo' => $nuevoCurso->id_curso,
                'cod_curso'      => $data['cod_curso'],
            ]);

            return $nuevoCurso;
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

                // syncDocenteTitularInComponents solo sincroniza curso.docente_componente
                // (es_titular por componente); sin esto, el nuevo titular nunca recibe el
                // rol RBAC "Docente Titular" en el contexto del curso (sólo se asigna al
                // crear el curso), y por lo tanto no tiene el permiso
                // cursos/programas:agregar para crear el syllabus.
                $this->assignDocenteRolCurso($newDocenteTitularId, $curso->id_contexto, true);
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
     * Calcula el próximo indice_grupo (letra) disponible para un periodo dado.
     * Null-safe: agno_real/semestre_real nulos se tratan como su propio scope
     * (no como "cualquier periodo"), igual que la comparación SQL original.
     *
     * @param int $idAsignacionPlan
     * @param int|null $agnoReal
     * @param int|null $semestreReal
     * @return int
     */
    public function nextIndiceGrupo(int $idAsignacionPlan, ?int $agnoReal, ?int $semestreReal): int
    {
        $query = Curso::where('id_asignacion_plan', $idAsignacionPlan)
            ->whereNull('fecha_eliminacion');

        $agnoReal === null ? $query->whereNull('agno_real') : $query->where('agno_real', $agnoReal);
        $semestreReal === null ? $query->whereNull('semestre_real') : $query->where('semestre_real', $semestreReal);

        $ultimoIndice = (int) ($query->max('indice_grupo') ?? 0);

        return $ultimoIndice + 1;
    }

    /**
     * Create or update a course context.
     *
     * @param string $codigoCurso
     * @param int|null $contextoExistente
     * @return Contexto
     */
    /**
     * Público a propósito: IntranetService lo reutiliza para crear el contexto
     * de las componentes que sincroniza sobre un curso ya existente, en vez de
     * duplicar esta lógica.
     */
    public function createOrUpdateContext(string $codigoCurso, ?int $contextoExistente = null): Contexto
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

    /**
     * Asigna el rol correcto al usuario en el contexto del curso según si es titular o componente.
     *
     * @param  int   $idDocente        ID del docente
     * @param  int   $idContextoCurso  ID del contexto del curso
     * @param  bool  $esTitular        Si se asigna como titular (true) o componente (false)
     * @return void
     */
    private function assignDocenteRolCurso(int $idDocente, int $idContextoCurso, bool $esTitular): void
    {
        try {
            $docente = Docente::with('usuario')->find($idDocente);
            if (!$docente || !$docente->id_usuario) {
                Log::warning('CursoService: No se pudo asignar rol Docente: docente no encontrado.', [
                    'id_docente' => $idDocente,
                ]);
                return;
            }

            $usuarioActivo = $docente->usuario
                && $docente->usuario instanceof Usuario
                && $docente->usuario->esta_activo;

            if (!$usuarioActivo) {
                Log::warning('CursoService: No se pudo asignar rol Docente: usuario asociado inexistente o inactivo.', [
                    'id_docente' => $idDocente,
                    'id_usuario' => $docente->id_usuario,
                ]);
                return;
            }

            $actorId = Auth::id() ?? $docente->id_usuario;
            $rolNombre = $esTitular ? 'Docente Titular' : 'Docente Componente';

            if ($esTitular) {
                // Revocar rol Docente Componente previo si existe
                $rolComponente = Rol::where('nombre', 'Docente Componente')->first();
                if ($rolComponente) {
                    UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $rolComponente->id_rol)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->update([
                            'esta_activo'    => false,
                            'fue_eliminado'  => true,
                            'fecha_fin_real' => Carbon::now(),
                            'eliminado_por'  => $actorId,
                        ]);
                }
            } else {
                // No degradar si ya tiene Docente Titular
                $rolTitular = Rol::where('nombre', 'Docente Titular')->first();
                if ($rolTitular) {
                    $hasTitular = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $rolTitular->id_rol)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->exists();
                    if ($hasTitular) {
                        return;
                    }
                }
            }

            $rol = Rol::firstOrCreate(
                ['nombre' => $rolNombre],
                ['creado_por' => $actorId]
            );

            $already = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                ->where('id_contexto', $idContextoCurso)
                ->where('id_rol', $rol->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->exists();

            if (!$already) {
                $now = Carbon::now();
                UsuarioRolAsignacion::create([
                    'id_usuario'               => $docente->id_usuario,
                    'id_rol'                   => $rol->id_rol,
                    'id_contexto'              => $idContextoCurso,
                    'asignado_por'             => $actorId,
                    'fecha_inicio_planificada' => $now,
                    'fecha_fin_planificada'    => $now->copy()->addYears(100),
                    'fecha_fin_real'           => null,
                    'fue_eliminado'            => false,
                    'esta_activo'              => true,
                    'creado_por'               => $actorId,
                ]);
                Log::info("CursoService: Rol '{$rolNombre}' asignado en curso", [
                    'id_usuario'        => $docente->id_usuario,
                    'id_contexto_curso' => $idContextoCurso,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CursoService: Error asignando rol Docente en curso: ' . $e->getMessage(), [
                'id_docente'        => $idDocente,
                'id_contexto_curso' => $idContextoCurso,
            ]);
        }
    }
}
