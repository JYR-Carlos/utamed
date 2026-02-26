<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Services\ProgramaService;
use App\Support\Permissions;
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
     * Convierte data_syllabus de estructura IX-secciones a estructura de SeccionPrograma
     */
    private function parseSecciones(array $data): array
    {
        // El data_syllabus tiene estructura: { metadata: {...}, secciones: { I: {...}, II: {...}, ..., IX: {...} } }
        // Extraer secciones del contenedor
        $seccionesData = $data['secciones'] ?? $data;
        
        $romanos = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
        $nombres = [
            'I' => 'Identificación',
            'II' => 'Presentación',
            'III' => 'Estándares',
            'IV' => 'Competencias',
            'V' => 'Evaluación Diagnóstica',
            'VI' => 'Unidades',
            'VII' => 'Planificación',
            'VIII' => 'Recursos',
            'IX' => 'Aspectos Administrativos',
        ];

        $secciones = [];
        foreach ($romanos as $idx => $romano) {
            // Extract seccion data and then get contenido
            $seccionData = $seccionesData[$romano] ?? [];
            $contenido = $seccionData['contenido'] ?? [];
            
            // Convertir contenido a formato de contenidos_programa
            $contenidosPrograma = $this->extraeContenidos($contenido, $romano);

            $seccion = [
                'nombre_seccion' => $nombres[$romano] ?? "Sección $romano",
                'numeral_romano' => $romano,
                'orden' => $idx + 1,
                'contenidos_programa' => $contenidosPrograma,
            ];

            // For section IX, add structured component data
            if ($romano === 'IX') {
                $seccion['componentes'] = $contenido['tabla_componentes'] ?? [];
                $seccion['ponderacion_optativa'] = $contenido['ponderacion_optativa'] ?? [];
            }

            $secciones[] = $seccion;
        }

        return $secciones;
    }

    /**
     * Extrae contenidos de cada sección para mostrar en el modal
     */
    private function extraeContenidos(array $contenido, string $seccionId): array
    {
        $contenidos = [];

        if (empty($contenido)) {
            return [['texto_contenido' => '', 'orden_item' => 1]];
        }

        // Serializar cada sección de forma legible
        switch ($seccionId) {
            case 'I':
                $text = sprintf(
                    "Asignatura: %s\nCódigo: %s\nCréditos SCT: %s\nHoras Cátedra: %s, Taller: %s, Lab: %s\nCategoría: %s",
                    $contenido['nombre_asignatura'] ?? '',
                    $contenido['codigo'] ?? '',
                    $contenido['creditos_sct'] ?? '',
                    $contenido['horas']['catedra'] ?? 0,
                    $contenido['horas']['taller'] ?? 0,
                    $contenido['horas']['laboratorio'] ?? 0,
                    $contenido['categoria'] ?? ''
                );
                break;

            case 'II':
            case 'III':
                $text = $contenido['texto'] ?? '';
                break;

            case 'IV':
                $esp = implode("\n", array_map(fn($c) => "• " . ($c['titulo'] ?? ''), $contenido['competencias_especificas'] ?? []));
                $gen = implode("\n", array_map(fn($c) => "• " . ($c['titulo'] ?? ''), $contenido['competencias_genericas'] ?? []));
                $sub = implode("\n", array_map(fn($c) => "• " . ($c['titulo'] ?? ''), $contenido['subcompetencias'] ?? []));
                $text = "Específicas:\n$esp\n\nGenéricas:\n$gen\n\nSub:\n$sub";
                break;

            case 'V':
                $text = implode("\n", array_map(
                    fn($i) => "• " . ($i['titulo'] ?? '') . ": " . ($i['descripcion'] ?? ''),
                    $contenido['items'] ?? []
                ));
                break;

            case 'VI':
                $unidadesText = array_map(function ($u) {
                    $resultados = implode("\n  ", array_map(
                        fn($r) => "• " . ($r['resultado'] ?? ''),
                        $u['resultados_aprendizaje'] ?? []
                    ));
                    return sprintf(
                        "Unidad %d: %s\nContenidos: %s\nResultados:\n  %s",
                        $u['numero'] ?? 0,
                        $u['titulo'] ?? '',
                        implode(", ", array_map(fn($c) => $c['item'] ?? '', $u['contenidos_items'] ?? [])),
                        $resultados
                    );
                }, $contenido['unidades'] ?? []);
                $text = implode("\n\n", $unidadesText);
                break;

            case 'VII':
                $resultados = implode("\n", array_map(
                    fn($r) => "• " . ($r['resultado'] ?? ''),
                    $contenido['resultados_aprendizaje']['items'] ?? []
                ));
                $text = sprintf(
                    "Resultados de Aprendizaje:\n%s\n\nMetodología:\n%s\n\nEvaluación:\n%s",
                    $resultados,
                    $contenido['metodologia']['tipo_estrategia'] ?? '',
                    $contenido['evaluacion']['tipo_evaluacion'] ?? ''
                );
                break;

            case 'VIII':
                $text = implode("\n", array_map(
                    fn($r) => "• " . ($r['descripcion'] ?? '') . " (" . ($r['tipo'] ?? '') . ")",
                    $contenido['recursos'] ?? []
                ));
                break;

            case 'IX':
                $componentes = implode("\n", array_map(
                    fn($c) => sprintf("• %s: %s%%, Acta: %s, Oblig: %s, Asist: %s%%",
                        $c['componente'] ?? '',
                        $c['porcentaje'] ?? 0,
                        $c['genera_acta'] ? 'Sí' : 'No',
                        $c['aprobacion_obligatoria'] ? 'Sí' : 'No',
                        $c['asistencia_obligatoria'] ?? 0
                    ),
                    $contenido['tabla_componentes'] ?? []
                ));
                $text = sprintf(
                    "Normativa:\n%s\n\nPonderación Optativa: %s%%\n\nComponentes:\n%s",
                    $contenido['descripcion'] ?? '',
                    $contenido['ponderacion_optativa']['porcentaje'] ?? 0,
                    $componentes
                );
                break;

            default:
                $text = '';
        }

        return [['texto_contenido' => $text, 'orden_item' => 1]];
    }

    /**
     * Retorna el programa activo en vista Inertia para revisión
     */
    public function show(Curso $curso)

    {
        // Cargar relaciones necesarias
        $curso->load('asignacionPlan.asignatura', 'asignacionPlan.plan.carrera');

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

        // Parse sections for display - parseSecciones already processes contenidos
        $secciones = $this->parseSecciones($programa->data_syllabus);
        
        // Convert contenidos_programa to contenidos for Svelte component compatibility
        foreach ($secciones as &$seccion) {
            $seccion['contenidos'] = $seccion['contenidos_programa'];
            unset($seccion['contenidos_programa']);
        }
        unset($seccion); // Break reference

        return Inertia::render('admin/Programas/ReviewPrograma', [
            'programa' => [
                'id_programa' => $programa->id_programa,
                'version_programa' => $programa->version_programa,
                'estado' => $programa->estado,
                'data_syllabus' => [
                    'secciones' => $secciones,
                ],
                'fecha_creacion' => $programa->fecha_creacion,
                'creado_por' => $programa->autor?->nombre ?? 'N/A',
            ],
            'curso' => [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre,
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre,
            ],
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

        // Validar tipo_syllabus
        $tipoSyllabus = $request->input('tipo_syllabus', 'BASICO');
        if (!in_array($tipoSyllabus, ['BASICO', 'COMPLETO'])) {
            return response()->json([
                'error' => 'tipo_syllabus debe ser "BASICO" o "COMPLETO"'
            ], 422);
        }

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
                ]
            );

            Log::info('Programa creado', [
                'id_programa' => $programa->id_programa,
                'id_curso' => $curso->id_curso,
                'tipo_syllabus' => $tipoSyllabus,
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

        // Validar que el programa esté en estado editable (BASICO_COMPLETO o COMPLETO)
        if (!in_array($programa->estado, ['BASICO_COMPLETO', 'COMPLETO'])) {
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
            // Validar que el programa esté en estado editable (antes de aprobación)
            if (!in_array($programa->estado, ['BASICO_COMPLETO', 'COMPLETO'])) {
                return response()->json([
                    'error' => "El programa debe estar en estado BASICO_COMPLETO o COMPLETO para ser aprobado. Estado actual: {$programa->estado}"
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
        $estadosValidos = ['BASICO_COMPLETO', 'COMPLETO', 'APROBADO', 'PUBLICADO'];
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
            'II' => ['contenido.texto' => 'required|string|min:100'],
            'III' => ['contenido.texto' => 'required|string|min:100'],
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
            
            // Sección II: Presentación
            'secciones.II.contenido.texto' => 'required|string|min:100',
            
            // Sección VI: Unidades
            'secciones.VI.contenido.unidades' => 'required|array|min:1',
            'secciones.VI.contenido.unidades.*.numero' => 'required|integer',
            'secciones.VI.contenido.unidades.*.titulo' => 'required|string',
            'secciones.VI.contenido.unidades.*.contenidos_items' => 'required|array|min:1',
            'secciones.VI.contenido.unidades.*.contenidos_items.*.item' => 'required|string',
            
            // Sección VII: Actividades de Aprendizaje
            'secciones.VII.contenido.actividades' => 'required|array|min:1',
            'secciones.VII.contenido.actividades.*.id_actividad' => 'required|integer',
            'secciones.VII.contenido.actividades.*.nombre' => 'required|string',
            'secciones.VII.contenido.actividades.*.tipo' => 'required|string',
            
            // Sección VIII: Bibliografía/Recursos
            'secciones.VIII.contenido.recursos' => 'required|array|min:2',
            'secciones.VIII.contenido.recursos.*.descripcion' => 'required|string',
            'secciones.VIII.contenido.recursos.*.tipo' => 'required|string|in:Libro,Documentación Online,Video,Herramienta Software,Base de Datos',
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
            
            // Sección II: Presentación
            'secciones.II.contenido.texto' => 'required|string|min:100',
            
            // Sección III: Estándares
            'secciones.III.contenido.texto' => 'required|string|min:100',
            
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
            
            // Sección VI: Unidades
            'secciones.VI.contenido.unidades' => 'required|array|min:1',
            'secciones.VI.contenido.unidades.*.numero' => 'required|integer',
            'secciones.VI.contenido.unidades.*.titulo' => 'required|string',
            'secciones.VI.contenido.unidades.*.contenidos_items' => 'required|array|min:1',
            'secciones.VI.contenido.unidades.*.contenidos_items.*.item' => 'required|string',
            
            // Sección VII: Planificación
            'secciones.VII.contenido.resultados_aprendizaje.titulo' => 'required|string',
            'secciones.VII.contenido.resultados_aprendizaje.items' => 'required|array|min:1',
            'secciones.VII.contenido.resultados_aprendizaje.items.*.resultado' => 'required|string',
            'secciones.VII.contenido.metodologia.titulo' => 'required|string',
            'secciones.VII.contenido.metodologia.tipo_estrategia' => 'required|string',
            'secciones.VII.contenido.evaluacion.titulo' => 'required|string',
            'secciones.VII.contenido.evaluacion.tipo_evaluacion' => 'required|string',
            
            // Sección VIII: Recursos
            'secciones.VIII.contenido.recursos' => 'required|array|min:2',
            'secciones.VIII.contenido.recursos.*.descripcion' => 'required|string',
            'secciones.VIII.contenido.recursos.*.tipo' => 'required|string|in:Libro,Documentación Online,Video,Herramienta Software,Base de Datos',
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
}