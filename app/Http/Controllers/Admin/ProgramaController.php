<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Curso\Unidad;
use App\Models\Agenda\Actividad;
use App\Services\ProgramaService;
use App\Support\Permissions;
use App\Traits\ParsesSyllabus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Admin Programa Controller
 * 
 * Gestiona programas con 9 secciones específicas (mapeadas a módulos):
 * I. Identificación (MODULO_1) | II. Presentación (MODULO_2) | III. Estándares (MODULO_3)
 * IV. Competencias (MODULO_4) | V. Evaluación Diagnóstica (MODULO_5) | VI. Unidades (MODULO_6)
 * VII. Planificación (MODULO_7) | VIII. Recursos (MODULO_8) | IX. Aspectos Administrativos (MODULO_9)
 * 
 * Permisos por sección definidos en Permissions enum
 * Retorna JSON responses para AJAX/Axios
 * Guarda estructura JSONB en data_syllabus
 */
class ProgramaController extends Controller
{
    use ParsesSyllabus;
    /**
     * Lista los programas DE UN CURSO ESPECÍFICO para el modal/sidebar view
     * Retorna JSON con lista formateada para el frontend
     * GET /admin/cursos/{curso}/programas
     */
    public function indexByCurso(Curso $curso)
    {
        $this->authorize('view', $curso);

        $programas = Programa::where('id_curso', $curso->id_curso)
            ->with(['creator', 'reviewer'])
            ->orderBy('fecha_creacion', 'desc')
            ->get()
            ->map(function ($p) {
                $creatorName = $p->creator
                    ? trim(collect([$p->creator->nombre1, $p->creator->nombre2, $p->creator->apellido1, $p->creator->apellido2])->filter()->implode(' '))
                    : null;
                $reviewerName = $p->reviewer
                    ? trim(collect([$p->reviewer->nombre1, $p->reviewer->nombre2, $p->reviewer->apellido1, $p->reviewer->apellido2])->filter()->implode(' '))
                    : null;
                return [
                    'id_programa' => $p->id_programa,
                    'version_programa' => $p->version_programa,
                    'estado' => $p->estado,
                    'creado_por' => $p->creado_por,
                    'revisado_por' => $p->revisado_por,
                    'fecha_creacion' => $p->fecha_creacion,
                    'data_syllabus' => $p->data_syllabus,
                    'completenessPercentage' => $p->getCompletenessPercentage(),
                    'creator' => [
                        'id_usuario' => $p->creator?->id_usuario,
                        'nombre_completo' => $creatorName,
                    ],
                    'reviewer' => [
                        'id_usuario' => $p->reviewer?->id_usuario,
                        'nombre_completo' => $reviewerName,
                    ],
                ];
            });

        return Inertia::render('admin/Programas_New', [
            'cursoId' => $curso->id_curso,
            'cursoNombre' => $curso->nombre,
            'programas' => $programas,
            'userRole' => Auth::user()->rol ?? 'usuario',
            'userId' => Auth::id(),
        ]);
    }

    /**
     * Retorna el programa en formato JSON para el modal AJAX
     * GET /admin/cursos/{id}/programa/json
     */
    public function getJson(Curso $curso)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return response()->json(['error' => 'No hay programa para este curso'], 404);
        }

        // Autorizar - docente puede ver su programa, admin/jefe puede revisar
        $this->authorize('view', $programa);  // Changed from 'approve' to 'view' to allow docentes

        // Parsear data_syllabus y convertir a estructura de secciones
        try {
            $dataSyllabus = is_array($programa->data_syllabus) 
                ? $programa->data_syllabus 
                : json_decode($programa->data_syllabus, true);

            // Mapear estructura JSONB a secciones esperadas por el frontend
            // parseSecciones ya procesa todo, incluyendo contenidos
            $secciones = $this->parseSecciones($dataSyllabus);

            return response()->json([
                'programa' => [
                    'id_programa' => $programa->id_programa,
                    'version_programa' => $programa->version_programa,
                    'estado' => $programa->estado,
                    'data_syllabus' => $dataSyllabus,
                    'fecha_creacion' => $programa->fecha_creacion,
                    'creado_por' => $programa->autor?->nombre ?? 'N/A',
                    'secciones' => $secciones,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al procesar el programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna el programa activo en vista Inertia para revisión
     */
    public function show(Curso $curso)

    {
        // Cargar relaciones necesarias
        $curso->load('asignacionPlan.asignatura', 'asignacionPlan.plan.carrera');

        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        // Validar autorización - docente puede ver su programa, admin/jefe puede revisar
        if ($programa) {
            $this->authorize('view', $programa);  // Changed from 'approve' to 'view' to allow docentes
        }

        if (!$programa) {
            return redirect()->route('admin.cursos.index')
                ->with('error', 'No hay programa para este curso');
        }

        // Obtener permisos especiales del usuario para el contexto del curso (si está configurado)
        $userPermissions = [];
        if ($curso->id_contexto) {
            $userPermissionsData = $user->getAllPermissions($curso->id_contexto);
            $userPermissions = collect($userPermissionsData)->map(function ($perm) {
                return [
                    'id_permiso' => $perm['id_permiso'],
                    'slug' => $perm['slug'],
                    'esta_permitido' => (bool)$perm['esta_permitido'],
                    'puede_delegar' => (bool)($perm['puede_delegar'] ?? false),
                ];
            })->values()->toArray();
        }

        // Cargar relaciones del programa para nombre de creador/revisor
        $programa->loadMissing(['creator', 'reviewer']);

        // Normalizar data_syllabus al contrato que espera ProgramaDocument
        $rawSyllabus = is_array($programa->data_syllabus)
            ? $programa->data_syllabus
            : json_decode($programa->data_syllabus, true);

        $secciones = $this->parseSecciones($rawSyllabus ?? []);

        $asignatura = $curso->asignacionPlan?->asignatura;

        return Inertia::render('docente/Programa', [
            'curso' => [
                'id_curso'                      => $curso->id_curso,
                'nombre'                        => $curso->nombre,
                'cod_curso'                     => $curso->cod_curso,
                'id_asignacion_plan'            => $curso->id_asignacion_plan,
                'id_contexto'                   => $curso->id_contexto,
                'asignatura_nombre'             => $asignatura?->nombre,
                'carrera_nombre'                => $curso->asignacionPlan?->plan?->carrera?->nombre,
                'asignatura'                    => $asignatura,
                'fecha_limite_entrega_basico'   => $curso->fecha_limite_entrega_basico,
                'fecha_limite_entrega_syllabus' => $curso->fecha_limite_entrega_syllabus,
            ],
            'programa' => [
                'id_programa'      => $programa->id_programa,
                'version_programa' => $programa->version_programa,
                'estado'           => $programa->estado,
                'creado_por'       => $programa->creado_por,
                'revisado_por'     => $programa->revisado_por,
                'fecha_creacion'   => $programa->fecha_creacion,
                'secciones'        => $secciones,
                'creator'  => $programa->creator  ? [
                    'id_usuario'      => $programa->creator->id_usuario,
                    'nombre_completo' => trim(collect([$programa->creator->nombre1, $programa->creator->nombre2, $programa->creator->apellido1, $programa->creator->apellido2])->filter()->implode(' ')),
                ] : null,
                'reviewer' => $programa->reviewer ? [
                    'id_usuario'      => $programa->reviewer->id_usuario,
                    'nombre_completo' => trim(collect([$programa->reviewer->nombre1, $programa->reviewer->nombre2, $programa->reviewer->apellido1, $programa->reviewer->apellido2])->filter()->implode(' ')),
                ] : null,
            ],
            'asignatura'      => $asignatura,
            'canEdit'         => $user->can('update', $programa),
            'canApprove'      => $user->can('approve', $programa),
            'userPermissions' => $userPermissions,
            'layoutType'      => 'admin',
            'backUrl'         => '/admin/cursos',
            'userId'          => $user->id_usuario,
        ]);
    }

    /**
     * Genera o regenera el programa activo
     * 
     * Acepta las 9 secciones completas en structure definida por PROGRAMA_9_SECCIONES_ESTRUCTURA.md
     * Valida permisos para cada sección antes de guardar
     */
    public function store(Request $request, Curso $curso)
    {
        $user = Auth::user();

        // Validar que el usuario tiene permiso general para crear/editar programa
        $this->authorize('create', [Programa::class, $curso]);

        // Validar y mapear syllabus_type del frontend
        $syllabusType = $request->input('syllabus_type', 'complete');
        
        // Mapear valores del frontend a valores del backend
        $typeMapping = [
            'simplified' => 'BASICO',
            'combined' => 'BASICO',  // combined continúa desde simplificado
            'complete' => 'COMPLETO',
        ];
        
        if (!in_array($syllabusType, ['simplified', 'combined', 'complete'])) {
            return response()->json([
                'error' => 'syllabus_type debe ser "simplified", "combined" o "complete"'
            ], 422);
        }

        $tipoSyllabus = $typeMapping[$syllabusType];

        // Obtener reglas de validación según tipo
        $validationRules = $this->getValidationRulesForType($tipoSyllabus);
        
        $validated = $request->validate([
            'secciones' => 'required|array',
            ...$validationRules,
        ]);

        try {
            // Validar permisos para cada sección antes de guardar
            $this->validatePermissionsForAllSecciones($user, $curso);

            // Determinar estado inicial según tipo
            $estadoInicial = $tipoSyllabus === 'BASICO' ? 'BASICO_COMPLETO' : 'COMPLETO';

            $programa = ProgramaService::generateProgramaWithSyllabus(
                $curso,
                $user instanceof \App\Models\Usuario\Usuario ? $user : null,
                [
                    'secciones' => $validated['secciones'],
                    'tipo_syllabus' => $tipoSyllabus,
                    'estado' => $estadoInicial,
                    'syllabus_creation_type' => $syllabusType,  // Guardar el tipo original del frontend
                ]
            );

            // ── Crear/sincronizar unidades desde sección VI del syllabus ─────────────
            $seccionVI = $validated['secciones']['VI']['contenido']['unidades'] ?? [];
            $this->createUnidadesFromSyllabus($curso, $seccionVI);

            // ── Crear actividades nuevas si se proporcionan ───────────────────────
            $actividadesByCreate = $request->input('actividades_to_create', []);
            if (!empty($actividadesByCreate)) {
                $this->createActividadesFromSyllabus($curso, $actividadesByCreate);
            }

            Log::info('Programa creado', [
                'id_programa' => $programa->id_programa,
                'id_curso' => $curso->id_curso,
                'tipo_syllabus' => $tipoSyllabus,
                'syllabus_creation_type' => $syllabusType,
                'estado' => $estadoInicial,
                'creado_por' => $user->id_usuario,
                'version' => $programa->version_programa,
            ]);

            return response()->json([
                'message' => 'Programa generado correctamente.',
                'programa' => [
                    'id_programa' => $programa->id_programa,
                    'version_programa' => $programa->version_programa,
                    'estado' => $programa->estado,
                    'tipo_syllabus' => $tipoSyllabus,
                    'syllabus_creation_type' => $syllabusType,
                    'creado_por' => $programa->autor->nombre,
                    'fecha_creacion' => $programa->fecha_creacion,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar programa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Error al generar el programa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza una sección específica del programa
     * 
     * @param Request $request
     * @param Programa $programa
     * @param string $seccionId (I, II, III, IV, V, VI, VII, VIII, IX)
     */
    public function updateSeccion(Request $request, Programa $programa, string $seccionId)
    {
        $user = Auth::user();

        $this->authorize('update', $programa);

        // Validar permiso para esta sección específica
        $this->validatePermissionForSeccion($user, $seccionId, $programa->id_curso);

        // Validar que el programa esté en estado editable (BORRADOR, BASICO_COMPLETO o COMPLETO)
        if (!in_array($programa->estado, ['BORRADOR', 'BASICO_COMPLETO', 'COMPLETO'])) {
            return response()->json([
                'error' => "No se puede editar un programa en estado {$programa->estado}"
            ], 422);
        }

        // Mapear validaciones por sección
        $validationRules = $this->getValidationRulesForSeccion($seccionId);

        $validated = $request->validate([
            'contenido' => 'required|array',
            ...$validationRules,
        ]);

        try {
            // Verificar si la sección a actualizar es de versión COMPLETO
            // y el programa actual es BASICO_COMPLETO
            $isConversionTrigger = $programa->isBasicoCompleto() && 
                                  $programa->isBasico() && 
                                  in_array($seccionId, ['III', 'IV', 'V', 'IX']);

            $programa = ProgramaService::updateSeccion(
                $programa,
                $seccionId,
                $validated['contenido'],
                $isConversionTrigger  // Flag para conversión automática
            );

            Log::info('Sección actualizada', [
                'id_programa' => $programa->id_programa,
                'seccion' => $seccionId,
                'usuario' => Auth::id(),
                'permiso_usado' => $this->getPermissionForSeccion($seccionId)->value,
                'nuevo_estado' => $programa->estado,
            ]);

            return response()->json([
                'message' => "Sección {$seccionId} actualizada correctamente.",
                'programa' => [
                    'id_programa' => $programa->id_programa,
                    'estado' => $programa->estado,
                    'tipo_syllabus' => $programa->getTipoSyllabus(),
                    'conversio_realizada' => $isConversionTrigger && $programa->isCompletoState(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar sección', [
                'error' => $e->getMessage(),
                'seccion' => $seccionId,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar la sección: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprueba el programa (cambia estado a APROBADO)
     * SOLO ADMIN puede hacer esto
     */
    public function approve(Request $request, Curso $curso)
    {
        $user = Auth::user();

        // Obtener programa actual
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return response()->json(['error' => 'No hay programa activo.'], 404);
        }

        // Validar autorización (usar la política)
        $this->authorize('approve', $programa);

        try {
            // Solo se puede aprobar la versión COMPLETO (tipo_syllabus = COMPLETO, estado = COMPLETO)
            // La versión BASICO no requiere aprobación; se marca directamente como BASICO_COMPLETO.
            if ($programa->estado !== 'COMPLETO') {
                return response()->json([
                    'error' => "Solo se puede aprobar un programa en estado COMPLETO. Estado actual: {$programa->estado}"
                ], 422);
            }

            if ($programa->getTipoSyllabus() !== 'COMPLETO') {
                return response()->json([
                    'error' => 'La versión básica (BASICO) no requiere aprobación.'
                ], 422);
            }

            // Validar completitud según tipo de syllabus
            if (!$programa->isCompleteWithAllSections()) {
                $required = $programa->getRequiredSecciones();
                $secciones = $programa->getSecciones();
                $missing = array_filter($required, function($s) use ($secciones) {
                    return !isset($secciones[$s]) || empty($secciones[$s]['contenido']);
                });
                
                return response()->json([
                    'error' => 'El programa debe tener todas las secciones requeridas completas. Faltan: ' . implode(', ', $missing)
                ], 422);
            }

            // Cambiar estado a APROBADO
            $programa->update([
                'estado' => 'APROBADO',
                'fecha_aprobacion' => now(),
                'revisado_por' => $user->id_usuario,
            ]);

            Log::info('Programa aprobado', [
                'id_programa' => $programa->id_programa,
                'tipo_syllabus' => $programa->getTipoSyllabus(),
                'aprobado_por' => $user->id_usuario,
                'fecha_aprobacion' => now(),
            ]);

            return redirect()->route('admin.cursos.index')
                ->with('success', 'Programa aprobado correctamente');

        } catch (\Exception $e) {
            Log::error('Error al aprobar programa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar programas con filtro por estado y tipo_syllabus
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Programa::class);

        $estado = $request->query('estado');
        $tipo = $request->query('tipo');
        $page = $request->query('page', 1);

        $query = Programa::query()
            ->with([
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                'autor'
            ])
            ->whereNull('fecha_eliminacion');

        // Filtro por estado - nuevos estados
        $estadosValidos = ['BORRADOR', 'BASICO_COMPLETO', 'COMPLETO', 'APROBADO', 'PUBLICADO'];
        if ($estado && in_array($estado, $estadosValidos)) {
            $query->where('estado', $estado);
        }

        // Filtro por tipo_syllabus (en JSONB)
        if ($tipo && in_array($tipo, ['BASICO', 'COMPLETO'])) {
            $query->whereJsonContains('data_syllabus->metadata->tipo_syllabus', $tipo);
        }

        $programas = $query->orderBy('fecha_creacion', 'desc')->paginate(15, ['*'], 'page', $page);

        // Mapear datos
        $programasData = $programas->map(function ($p) {
            return [
                'id_programa' => $p->id_programa,
                'id_curso' => $p->id_curso,
                'version' => $p->version_programa,
                'estado' => $p->estado,
                'tipo_syllabus' => $p->getTipoSyllabus(),
                'asignatura' => $p->curso?->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera' => $p->curso?->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'creado_por' => $p->autor?->nombre ?? 'N/A',
                'fecha_creacion' => $p->fecha_creacion,
                'fecha_aprobacion' => $p->fecha_aprobacion,
                'fecha_publicacion' => $p->fecha_publicacion ?? null,
                'completud' => $p->getCompletenessPercentage(),
            ];
        });

        // Estadísticas por estado y tipo
        $stats = [
            'basico_completo' => Programa::where('estado', 'BASICO_COMPLETO')
                ->whereNull('fecha_eliminacion')->count(),
            'completo' => Programa::where('estado', 'COMPLETO')
                ->whereNull('fecha_eliminacion')->count(),
            'aprobados' => Programa::where('estado', 'APROBADO')
                ->whereNull('fecha_eliminacion')->count(),
            'publicados' => Programa::where('estado', 'PUBLICADO')
                ->whereNull('fecha_eliminacion')->count(),
            'borradores' => Programa::where('estado', 'BORRADOR')
                ->whereNull('fecha_eliminacion')->count(),
            // Aliases para compatibilidad con vista Programas.svelte
            'pendientes' => Programa::whereIn('estado', ['BASICO_COMPLETO', 'COMPLETO'])
                ->whereNull('fecha_eliminacion')->count(),
            'rechazados' => 0,  // estado eliminado del nuevo flujo
            'total' => Programa::whereNull('fecha_eliminacion')->count(),
        ];

        return Inertia::render('admin/Programas', [
            'programas' => $programasData,
            'pagination' => [
                'current_page' => $programas->currentPage(),
                'last_page' => $programas->lastPage(),
                'total' => $programas->total(),
                'per_page' => $programas->perPage(),
            ],
            'stats' => $stats,
            'estado_filtro' => $estado,
            'tipo_filtro' => $tipo,
        ]);
    }

    /**
     * Vista de Syllabus: lista de cursos con su programa/syllabus, estado y fechas límite.
     * Exclusivo para administradores.
     * GET /admin/syllabus
     */
    public function syllabusIndex(Request $request)
    {
        $this->authorize('viewAny', Programa::class);

        $busqueda  = $request->query('q', '');
        $semestre  = $request->query('semestre', '');
        $agno      = $request->query('agno', '');

        $query = Curso::query()
            ->with([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera',
                'programas' => fn ($q) => $q->where('es_actual', true)->whereNull('fecha_eliminacion'),
            ])
            ->whereNull('fecha_eliminacion');

        if ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'ilike', "%{$busqueda}%")
                  ->orWhere('cod_curso', 'ilike', "%{$busqueda}%");
            });
        }

        if ($semestre) {
            $query->where('semestre_real', $semestre);
        }

        if ($agno) {
            $query->where('agno_real', $agno);
        }

        $cursos = $query->orderBy('nombre')->paginate(20, ['*'], 'page', $request->query('page', 1));

        $cursosData = $cursos->map(function (Curso $c) {
            $programa = $c->programas->first();

            // Tipo de syllabus: BASICO, COMPLETO, o null (sin programa)
            $tipoSyllabus = $programa ? $programa->getTipoSyllabus() : null;
            $estadoPrograma = $programa ? $programa->estado : null;

            return [
                'id_curso'                      => $c->id_curso,
                'cod_curso'                     => $c->cod_curso,
                'nombre'                        => $c->nombre,
                'agno_real'                     => $c->agno_real,
                'semestre_real'                 => $c->semestre_real,
                'asignatura'                    => $c->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera'                       => $c->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'fecha_limite_entrega_basico'   => $c->fecha_limite_entrega_basico?->toIso8601String(),
                'fecha_limite_entrega_syllabus' => $c->fecha_limite_entrega_syllabus?->toIso8601String(),
                'programa' => $programa ? [
                    'id_programa'      => $programa->id_programa,
                    'estado'           => $estadoPrograma,
                    'tipo_syllabus'    => $tipoSyllabus,
                    'version_programa' => $programa->version_programa,
                    'completud'        => $programa->getCompletenessPercentage(),
                ] : null,
            ];
        });

        // Opciones de filtro
        $semestres = Curso::whereNull('fecha_eliminacion')
            ->whereNotNull('semestre_real')
            ->distinct()->pluck('semestre_real')->sort()->values();

        $agnos = Curso::whereNull('fecha_eliminacion')
            ->whereNotNull('agno_real')
            ->distinct()->pluck('agno_real')->sortDesc()->values();

        return Inertia::render('admin/Syllabus', [
            'cursos'     => $cursosData,
            'pagination' => [
                'current_page' => $cursos->currentPage(),
                'last_page'    => $cursos->lastPage(),
                'total'        => $cursos->total(),
                'per_page'     => $cursos->perPage(),
            ],
            'filters'    => [
                'q'        => $busqueda,
                'semestre' => $semestre,
                'agno'     => $agno,
            ],
            'semestres'  => $semestres,
            'agnos'      => $agnos,
        ]);
    }

    /**
     * Rechazar programa - Devuelve de APROBADO a su estado anterior (BASICO_COMPLETO o COMPLETO)
     * SOLO ADMIN puede hacer esto
     */
    public function reject(Request $request, Curso $curso)
    {
        $user = Auth::user();

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        // Validar autorización (usar la política)
        $this->authorize('reject', $programa);

        try {
            // Validar estado actual - solo se puede rechazar si está APROBADO
            if ($programa->estado !== 'APROBADO') {
                return redirect()->back()->with('error', "No se puede rechazar un programa en estado {$programa->estado}. Solo se pueden rechazar programas aprobados.");
            }

            // Obtener el tipo de syllabus para devolver al estado correcto
            $tipoSyllabus = $programa->getTipoSyllabus();
            $estadoAnterior = $tipoSyllabus === 'BASICO' ? 'BASICO_COMPLETO' : 'COMPLETO';

            // Obtener razón del rechazo si fue proporcionada
            $razonRechazo = $request->input('razon_rechazo', 'No especificada');

            // Devolver a estado anterior
            $programa->update([
                'estado' => $estadoAnterior,
                'fecha_aprobacion' => null,
                'revisado_por' => null,
            ]);

            Log::info('Programa rechazado/devuelto a revisión', [
                'id_programa' => $programa->id_programa,
                'tipo_syllabus' => $tipoSyllabus,
                'estado_anterior' => 'APROBADO',
                'estado_nuevo' => $estadoAnterior,
                'rechazado_por' => $user->id_usuario,
                'razon_rechazo' => $razonRechazo,
            ]);

            return redirect()->route('admin.programas.index', ['estado' => $estadoAnterior])
                ->with('warning', "Programa de {$curso->nombre} devuelto a estado {$estadoAnterior}. El usuario puede editarlo nuevamente. Razón: {$razonRechazo}");

        } catch (\Exception $e) {
            Log::error('Error al rechazar programa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Error al rechazar el programa: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza las fechas límite de entrega del básico y/o el syllabus completo.
     * SOLO ADMIN puede hacer esto.
     */
    public function updateDeadlines(Request $request, Curso $curso)
    {
        $this->authorize('viewAny', Programa::class);

        $validated = $request->validate([
            'fecha_limite_entrega_basico'   => 'sometimes|nullable|date',
            'fecha_limite_entrega_syllabus' => 'sometimes|nullable|date',
        ]);

        // Cross-field: syllabus date must be >= basic date, using DB fallback
        $basico   = $validated['fecha_limite_entrega_basico']   ?? $curso->fecha_limite_entrega_basico;
        $syllabus = $validated['fecha_limite_entrega_syllabus'] ?? $curso->fecha_limite_entrega_syllabus;

        if ($basico && $syllabus && $syllabus < $basico) {
            return back()->withErrors([
                'fecha_limite_entrega_syllabus' => 'La fecha límite del syllabus debe ser igual o posterior a la del básico.',
            ]);
        }

        $curso->update($validated);

        return redirect()->back()->with('success', 'Fechas límite actualizadas correctamente.');
    }

    /**
     * Valida que el usuario tenga permiso para editar una sección específica
     */
    private function validatePermissionForSeccion($user, string $seccionId, int $idContexto): void
    {
        $permission = $this->getPermissionForSeccion($seccionId);

        if (!$user->hasPermission($permission, $idContexto)) {
            abort(403, "No tienes permiso para editar la sección {$seccionId}");
        }
    }

    /**
     * Valida que el usuario tenga permisos para todas las secciones
     */
    private function validatePermissionsForAllSecciones($user, Curso $curso): void
    {
        $secciones = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];

        foreach ($secciones as $seccion) {
            $this->validatePermissionForSeccion($user, $seccion, $curso->id_curso);
        }
    }

    /**
     * Mapea ID de sección (I-IX) a Enum de Permiso
     * 
     * @param string $seccionId (I, II, III, IV, V, VI, VII, VIII, IX)
     * @return Permissions
     */
    private function getPermissionForSeccion(string $seccionId): Permissions
    {
        $map = [
            'I' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1,
            'II' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2,
            'III' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3,
            'IV' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4,
            'V' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5,
            'VI' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6,
            'VII' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7,
            'VIII' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8,
            'IX' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9,
        ];

        return $map[$seccionId] ?? throw new \InvalidArgumentException("Sección inválida: {$seccionId}");
    }

    /**
     * Retorna las reglas de validación específicas para cada sección actualizado
     */
    private function getValidationRulesForSeccion(string $seccionId): array
    {
        $rules = [
            'I' => [
                'contenido.nombre_asignatura' => 'required|string|max:255',
                'contenido.codigo' => 'required|string|max:50',
                'contenido.creditos_sct' => 'required|integer',
                'contenido.horas.catedra' => 'required|integer|min:0',
                'contenido.horas.taller' => 'required|integer|min:0',
                'contenido.horas.laboratorio' => 'required|integer|min:0',
                'contenido.categoria' => 'required|string',
            ],
            'II' => ['contenido.texto' => 'nullable|string'],
            'III' => ['contenido.texto' => 'nullable|string'],
            'IV' => [
                'contenido.competencias_especificas' => 'required|array|min:1',
                'contenido.competencias_genericas' => 'required|array|min:1',
            ],
            'V' => ['contenido.items' => 'required|array|min:1'],
            'VI' => ['contenido.unidades' => 'required|array|min:1'],
            'VII' => [
                'contenido.resultados_aprendizaje' => 'required|array',
                'contenido.metodologia' => 'required|array',
                'contenido.evaluacion' => 'required|array',
            ],
            'VIII' => ['contenido.recursos' => 'required|array|min:2'],
            'IX' => [
                'contenido.tabla_componentes' => 'required|array|min:1',
                'contenido.ponderacion_optativa' => 'required|array',
            ],
        ];

        return $rules[$seccionId] ?? [];
    }

    /**
     * Retorna las reglas de validación según el tipo de syllabus
     * BASICO: valida solo 5 secciones
     * COMPLETO: valida 9 secciones
     */
    private function getValidationRulesForType(string $tipoSyllabus): array
    {
        if ($tipoSyllabus === 'BASICO') {
            return $this->getValidationRulesForBasico();
        }
        
        return $this->getValidationRulesForCompleto();
    }

    /**
     * Reglas de validación para BASICO (5 secciones)
     */
    private function getValidationRulesForBasico(): array
    {
        return [
            // Sección I: Identificación
            'secciones.I.contenido.nombre_asignatura' => 'required|string|max:255',
            'secciones.I.contenido.codigo' => 'required|string|max:50',
            'secciones.I.contenido.creditos_sct' => 'required|integer|min:1|max:50',
            'secciones.I.contenido.horas.catedra' => 'required|integer|min:0',
            'secciones.I.contenido.horas.taller' => 'required|integer|min:0',
            'secciones.I.contenido.horas.laboratorio' => 'required|integer|min:0',
            'secciones.I.contenido.categoria' => 'required|string|in:Obligatorio,Electivo,Nivelación,Complementaria',
            
            // Sección II: Presentación - Permitir enviar vacío para rellenar después
            'secciones.II.contenido.texto' => 'nullable|string',
            'secciones.VI.contenido.unidades' => 'nullable|array',
            'secciones.VI.contenido.unidades.*.numero' => 'nullable|integer',
            'secciones.VI.contenido.unidades.*.titulo' => 'nullable|string',
            'secciones.VI.contenido.unidades.*.contenidos_items' => 'nullable|array',
            'secciones.VI.contenido.unidades.*.contenidos_items.*.item' => 'nullable|string',
            'secciones.VI.contenido.unidades.*.resultados_aprendizaje' => 'nullable|array',
            'secciones.VI.contenido.unidades.*.resultados_aprendizaje.*.resultado' => 'nullable|string',
            
            // Sección VII: Actividades de Aprendizaje - Permitir enviar vacío para rellenar después
            'secciones.VII.contenido.actividades' => 'nullable|array',
            'secciones.VII.contenido.actividades.*.id_actividad' => 'nullable|integer',
            'secciones.VII.contenido.actividades.*.nombre' => 'nullable|string',
            'secciones.VII.contenido.actividades.*.tipo' => 'nullable|string',
            
            // Sección VIII: Bibliografía/Recursos - Permitir enviar vacío para rellenar después
            'secciones.VIII.contenido.recursos' => 'nullable|array',
            'secciones.VIII.contenido.recursos.*.descripcion' => 'nullable|string',
            'secciones.VIII.contenido.recursos.*.tipo' => 'nullable|string|in:Libro,Documentación Online,Video,Herramienta Software,Base de Datos',
            'secciones.VIII.contenido.recursos.*.ubicacion' => 'nullable|string',
        ];
    }

    /**
     * Reglas de validación para COMPLETO (9 secciones)
     */
    private function getValidationRulesForCompleto(): array
    {
        return [
            // Sección I: Identificación
            'secciones.I.contenido.nombre_asignatura' => 'required|string|max:255',
            'secciones.I.contenido.codigo' => 'required|string|max:50',
            'secciones.I.contenido.creditos_sct' => 'required|integer|min:1|max:50',
            'secciones.I.contenido.horas.catedra' => 'required|integer|min:0',
            'secciones.I.contenido.horas.taller' => 'required|integer|min:0',
            'secciones.I.contenido.horas.laboratorio' => 'required|integer|min:0',
            'secciones.I.contenido.categoria' => 'required|string|in:Obligatorio,Electivo,Nivelación,Complementaria',
            
            // Sección II: Presentación - Permitir enviar vacío para rellenar después
            'secciones.II.contenido.texto' => 'nullable|string',
            
            // Sección III: Estándares
            'secciones.III.contenido.texto' => 'nullable|string',
            
            // Sección IV: Competencias
            'secciones.IV.contenido.competencias_especificas' => 'required|array|min:1',
            'secciones.IV.contenido.competencias_especificas.*.titulo' => 'required|string',
            'secciones.IV.contenido.competencias_genericas' => 'required|array|min:1',
            'secciones.IV.contenido.competencias_genericas.*.titulo' => 'required|string',
            'secciones.IV.contenido.subcompetencias' => 'nullable|array',
            'secciones.IV.contenido.subcompetencias.*.titulo' => 'required_if:secciones.IV.contenido.subcompetencias,!null|string',
            
            // Sección V: Evaluación Diagnóstica
            'secciones.V.contenido.items' => 'required|array|min:1',
            'secciones.V.contenido.items.*.titulo' => 'required|string',
            'secciones.V.contenido.items.*.descripcion' => 'nullable|string',
            
            // Sección VI: Unidades - Permitir enviar vacío para rellenar después
            'secciones.VI.contenido.unidades' => 'nullable|array',
            'secciones.VI.contenido.unidades.*.numero' => 'nullable|integer',
            'secciones.VI.contenido.unidades.*.titulo' => 'nullable|string',
            'secciones.VI.contenido.unidades.*.contenidos_items' => 'nullable|array',
            'secciones.VI.contenido.unidades.*.contenidos_items.*.item' => 'nullable|string',
            'secciones.VI.contenido.unidades.*.resultados_aprendizaje' => 'nullable|array',
            'secciones.VI.contenido.unidades.*.resultados_aprendizaje.*.resultado' => 'nullable|string',
            
            // Sección VII: Planificación
            'secciones.VII.contenido.resultados_aprendizaje.titulo' => 'required|string',
            'secciones.VII.contenido.resultados_aprendizaje.items' => 'required|array|min:1',
            'secciones.VII.contenido.resultados_aprendizaje.items.*.resultado' => 'required|string',
            'secciones.VII.contenido.metodologia.titulo' => 'required|string',
            'secciones.VII.contenido.metodologia.tipo_estrategia' => 'required|string',
            'secciones.VII.contenido.evaluacion.titulo' => 'required|string',
            'secciones.VII.contenido.evaluacion.tipo_evaluacion' => 'required|string',
            
            // Sección VIII: Recursos - Permitir enviar vacío para rellenar después
            'secciones.VIII.contenido.recursos' => 'nullable|array',
            'secciones.VIII.contenido.recursos.*.descripcion' => 'nullable|string',
            'secciones.VIII.contenido.recursos.*.tipo' => 'nullable|string|in:Libro,Documentación Online,Video,Herramienta Software,Base de Datos',
            'secciones.VIII.contenido.recursos.*.ubicacion' => 'nullable|string',
            
            // Sección IX: Aspectos Administrativos
            'secciones.IX.contenido.descripcion' => 'required|string',
            'secciones.IX.contenido.ponderacion_optativa.porcentaje' => 'required|numeric|min:0|max:100',
            'secciones.IX.contenido.tabla_componentes' => 'required|array|min:1',
            'secciones.IX.contenido.tabla_componentes.*.componente' => 'required|string',
            'secciones.IX.contenido.tabla_componentes.*.porcentaje' => 'required|numeric|min:0|max:100',
            'secciones.IX.contenido.tabla_componentes.*.genera_acta' => 'required|boolean',
            'secciones.IX.contenido.tabla_componentes.*.aprobacion_obligatoria' => 'nullable|boolean',
            'secciones.IX.contenido.tabla_componentes.*.asistencia_obligatoria' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Crea actividades nuevas asociadas a unidades del curso.
     * 
     * Hace match del nombre_unidad con las unidades existentes en el curso
     * y crea las actividades asociadas.
     * 
     * @param Curso $curso
     * @param array $actividadesData Array de actividades con nombre_unidad
     */
    /**
     * Sincroniza las unidades del curso a partir de la sección VI del syllabus.
     *
     * Elimina las unidades existentes del curso y las vuelve a crear en base
     * al arreglo $unidadesData (cada elemento tiene 'numero' y 'titulo').
     * Al finalizar las unidades quedan disponibles para vincular actividades.
     *
     * @param Curso  $curso
     * @param array  $unidadesData  [ ['numero' => int, 'titulo' => string, ...], ... ]
     */
    private function createUnidadesFromSyllabus(Curso $curso, array $unidadesData): void
    {
        if (empty($unidadesData)) {
            return;
        }

        // IDs de unidades que quedan tras el upsert (para eliminar las que ya no están en el syllabus)
        $keptIds = [];

        foreach ($unidadesData as $uData) {
            $titulo = trim($uData['titulo'] ?? '');
            if ($titulo === '') {
                continue;
            }

            $numUnidad = $uData['numero'] ?? null;

            // Buscar por num_unidad primero, luego por nombre — así se reutilizan las existentes
            $unidad = $numUnidad
                ? Unidad::where('id_curso', $curso->id_curso)->where('num_unidad', $numUnidad)->first()
                : null;

            if (!$unidad) {
                $unidad = Unidad::where('id_curso', $curso->id_curso)->where('nombre', $titulo)->first();
            }

            if ($unidad) {
                // Actualizar datos sin cambiar el id_unidad (preserva FK de actividades)
                $unidad->update([
                    'num_unidad'  => $numUnidad ?? $unidad->num_unidad,
                    'nombre'      => $titulo,
                    'descripcion' => $uData['contenidos_items'][0]['item'] ?? $unidad->descripcion,
                ]);
                Log::info('Unidad actualizada desde syllabus', ['id_unidad' => $unidad->id_unidad, 'nombre' => $titulo]);
            } else {
                $unidad = Unidad::create([
                    'num_unidad'  => $numUnidad,
                    'nombre'      => $titulo,
                    'descripcion' => $uData['contenidos_items'][0]['item'] ?? null,
                    'id_curso'    => $curso->id_curso,
                ]);
                Log::info('Unidad creada desde syllabus', ['id_unidad' => $unidad->id_unidad, 'nombre' => $titulo]);
            }

            $keptIds[] = $unidad->id_unidad;
        }

        // Eliminar unidades que ya no aparecen en el syllabus, SOLO si no tienen actividades asociadas
        Unidad::where('id_curso', $curso->id_curso)
            ->whereNotIn('id_unidad', $keptIds)
            ->whereDoesntHave('actividades')
            ->delete();
    }

    private function createActividadesFromSyllabus(Curso $curso, array $actividadesData)
    {
        if (empty($actividadesData)) {
            return;
        }

        try {
            foreach ($actividadesData as $actData) {
                // Validar que tenga los campos necesarios
                if (empty($actData['nombre']) || empty($actData['nombre_unidad'])) {
                    continue;
                }

                // Buscar la unidad que coincida con el nombre
                $unidad = Unidad::where('id_curso', $curso->id_curso)
                    ->where('nombre', $actData['nombre_unidad'])
                    ->first();

                if (!$unidad) {
                    Log::warning("Unidad no encontrada para actividad: {$actData['nombre']}", [
                        'nombre_unidad' => $actData['nombre_unidad'],
                        'id_curso' => $curso->id_curso,
                    ]);
                    continue;
                }

                // Obtener la primera sección del curso para asociar la actividad
                $seccion = Seccion::where('id_curso', $curso->id_curso)
                    ->first();

                if (!$seccion) {
                    Log::warning("No hay secciones para crear actividad en curso: {$curso->id_curso}");
                    continue;
                }

                // Crear la actividad
                Actividad::create([
                    'nombre' => $actData['nombre'],
                    'tipo_actividad' => $actData['tipo_actividad'] ?? 1,
                    'tipo_entrega' => $actData['tipo_entrega'] ?? 'online',
                    'es_grupal' => $actData['es_grupal'] ?? false,
                    'max_integrantes' => $actData['max_integrantes'] ?? 1,
                    'es_plantilla' => false,
                    'id_seccion' => $seccion->id_seccion,
                    'id_unidad' => $unidad->id_unidad,
                    'id_contexto' => $curso->id_contexto,
                    'fecha_limite' => $actData['fecha_limite'] ?? now()->addDays(7),
                    'visible' => true,
                ]);

                Log::info("Actividad creada", [
                    'nombre' => $actData['nombre'],
                    'id_unidad' => $unidad->id_unidad,
                    'id_curso' => $curso->id_curso,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error creando actividades: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSTANCIACIÓN (Admin crea el espacio vacío; el docente lo rellena después)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Instancia un programa vacío para el curso.
     *
     * El administrador crea el "contenedor" en estado BORRADOR.
     * El docente luego completa las secciones y lo marca como listo:
     *   - básico  → llama a completar-basico   → BASICO_COMPLETO (visible sin aprobación)
     *   - completo → llama a enviar            → COMPLETO (requiere aprobación)
     *
     * POST /admin/cursos/{curso}/programa/instanciar
     * Body: { tipo_syllabus: 'BASICO'|'COMPLETO' }
     */
    public function instantiar(Request $request, Curso $curso)
    {
        $user = Auth::user();

        $this->authorize('create', [Programa::class, $curso]);

        $validated = $request->validate([
            'tipo_syllabus' => 'required|in:BASICO,COMPLETO',
        ]);

        try {
            $programa = ProgramaService::instantiate(
                $curso,
                $user instanceof \App\Models\Usuario\Usuario ? $user : null,
                $validated['tipo_syllabus']
            );

            Log::info('Programa instanciado', [
                'id_programa'     => $programa->id_programa,
                'id_curso'        => $curso->id_curso,
                'tipo_syllabus'   => $validated['tipo_syllabus'],
                'instanciado_por' => $user->id_usuario,
                'version'         => $programa->version_programa,
            ]);

            return redirect()->back()->with('success', 'Programa instanciado correctamente. El docente puede comenzar a completarlo.');

        } catch (\Exception $e) {
            Log::error('Error al instanciar programa', [
                'error'    => $e->getMessage(),
                'id_curso' => $curso->id_curso,
            ]);

            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}