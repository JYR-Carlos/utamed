<?php

namespace App\Http\Controllers\Ayudante;

use App\Http\Controllers\Controller;
use App\Models\Curso\Programa;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Auditoria\ProgramaHistorial;
use App\Services\ProgramaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\Permissions;

/**
 * Gestión del programa/syllabus de un curso desde el rol de ayudante.
 *
 * El acceso exige rol "ayudante" activo sobre el contexto del curso y el permiso
 * `CURSOS_PROGRAMAS_VER_TODOS`. La edición queda bloqueada cuando el programa está
 * `APROBADO`. El armado del syllabus se delega en ProgramaService.
 */
class ProgramaController extends Controller
{
    /**
     * Ver programa de un curso como ayudante
     *
     * Requiere permiso: curso/programa: ver
     */
    public function show(Curso $curso): Response|RedirectResponse
    {
        return $this->renderProgramaView($curso, 'view');
    }

    /**
     * Crear nuevo programa para un curso (formulario)
     *
     * Requiere permiso: cursos/programas:crear
     */
    public function create(Curso $curso): Response|RedirectResponse
    {
        return $this->renderProgramaView($curso, 'create');
    }

    /**
     * Editar programa de un curso como ayudante
     * 
     * Requiere permiso: curso/programa: editar
     * Solo disponible si estado != 'APROBADO'
     */
    public function edit(Curso $curso): Response|RedirectResponse
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que ayudante está asignado en este curso
        if (!$curso->id_contexto) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'El curso no tiene contexto configurado');
        }

        $rolAyudante = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();
        
        if (!$rolAyudante) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'Rol de ayudante no configurado en el sistema');
        }

        $asignacion = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->first();

        if (!$asignacion) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No estás asignado a este curso como ayudante');
        }

        // Verificar permiso: cursos/programas
        if (!$user->hasPermission(Permissions::CURSOS_PROGRAMAS_VER_TODOS, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para editar el programa de este curso');
        }

        // Verificar que el programa no está aprobado
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if ($programa && $programa->estado === 'APROBADO') {
            return redirect()->route('ayudante.cursos.programa.show', $curso->id_curso)
                ->with('error', 'No puedes editar un programa aprobado');
        }

        return $this->renderProgramaView($curso, 'edit');
    }

    /**
     * Actualizar programa de un curso como ayudante
     * 
     * Requiere permiso: curso/programa: editar
     * Solo disponible si estado != 'APROBADO'
     */
    public function update(Request $request, Curso $curso): JsonResponse|RedirectResponse
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que ayudante está asignado en este curso
        if (!$curso->id_contexto) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'El curso no tiene contexto configurado');
        }

        $rolAyudante = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();
        
        if (!$rolAyudante) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'Rol de ayudante no configurado en el sistema');
        }

        $asignacion = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->first();

        if (!$asignacion) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No estás asignado a este curso como ayudante');
        }

        // Verificar permiso: cursos/programas
        if (!$user->hasPermission(Permissions::CURSOS_PROGRAMAS_VER_TODOS, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para editar el programa de este curso');
        }

        // Verificar que el programa no está aprobado
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if ($programa && $programa->estado === 'APROBADO') {
            return response()->json(['error' => 'No puedes editar un programa aprobado'], 422);
        }

        // Validar y mapear syllabus_type del frontend
        $syllabusType = $request->input('syllabus_type', 'complete');

        $typeMapping = [
            'simplified' => 'BASICO',
            'combined'   => 'BASICO',
            'complete'   => 'COMPLETO',
        ];

        if (!array_key_exists($syllabusType, $typeMapping)) {
            return response()->json(['error' => 'syllabus_type debe ser "simplified", "combined" o "complete"'], 422);
        }

        $tipoSyllabus = $typeMapping[$syllabusType];

        // Validar payload con el mismo esquema que AdminProgramaController
        $baseRules = ['secciones' => 'required|array', 'syllabus_type' => 'nullable|string'];

        if ($tipoSyllabus === 'BASICO') {
            $typeRules = [
                'secciones.I.contenido.nombre_asignatura' => 'required|string|max:255',
                'secciones.I.contenido.codigo'           => 'required|string|max:50',
                'secciones.I.contenido.creditos_sct'     => 'required|integer',
                'secciones.I.contenido.horas.catedra'    => 'required|integer|min:0',
                'secciones.I.contenido.horas.taller'     => 'required|integer|min:0',
                'secciones.I.contenido.horas.laboratorio' => 'required|integer|min:0',
                'secciones.I.contenido.categoria'        => 'required|string',
                'secciones.II.contenido.texto'           => 'nullable|string',
                'secciones.VI.contenido.unidades'        => 'nullable|array',
                'secciones.VII.contenido.actividades'    => 'nullable|array',
                'secciones.VIII.contenido.recursos'      => 'nullable|array',
            ];
        } else {
            $typeRules = [
                'secciones.I.contenido.nombre_asignatura' => 'required|string|max:255',
                'secciones.I.contenido.codigo'           => 'required|string|max:50',
                'secciones.I.contenido.creditos_sct'     => 'required|integer',
                'secciones.II.contenido.texto'           => 'nullable|string',
                'secciones.VI.contenido.unidades'        => 'nullable|array',
                'secciones.VIII.contenido.recursos'      => 'nullable|array',
            ];
        }

        $validated = $request->validate(array_merge($baseRules, $typeRules));

        try {
            $estadoInicial = $tipoSyllabus === 'BASICO' ? 'BASICO_COMPLETO' : 'COMPLETO';

            $programa = ProgramaService::generateProgramaWithSyllabus(
                $curso,
                $user,
                [
                    'secciones'             => $validated['secciones'],
                    'tipo_syllabus'         => $tipoSyllabus,
                    'estado'                => $estadoInicial,
                    'syllabus_creation_type' => $syllabusType,
                ]
            );

            // Crear/sincronizar unidades desde sección VI
            $seccionVI = $validated['secciones']['VI']['contenido']['unidades'] ?? [];
            $this->createUnidadesFromSyllabus($curso, $seccionVI);

            // Crear actividades nuevas si se proporcionan
            $actividadesByCreate = $request->input('actividades_to_create', []);
            if (!empty($actividadesByCreate)) {
                $this->createActividadesFromSyllabus($curso, $actividadesByCreate);
            }

            Log::info('Programa creado por ayudante', [
                'id_programa' => $programa->id_programa,
                'id_curso'    => $curso->id_curso,
                'creado_por'  => $user->id_usuario,
            ]);

            return response()->json([
                'message' => 'Programa generado correctamente.',
                'programa' => [
                    'id_programa'           => $programa->id_programa,
                    'version_programa'      => $programa->version_programa,
                    'estado'                => $programa->estado,
                    'tipo_syllabus'         => $tipoSyllabus,
                    'syllabus_creation_type' => $syllabusType,
                    'fecha_creacion'        => $programa->fecha_creacion,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar programa (ayudante)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al generar el programa: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function createUnidadesFromSyllabus(Curso $curso, array $unidadesData): void
    {
        if (empty($unidadesData)) {
            return;
        }

        Unidad::where('id_curso', $curso->id_curso)->delete();

        foreach ($unidadesData as $uData) {
            $titulo = trim($uData['titulo'] ?? '');
            if ($titulo === '') {
                continue;
            }
            Unidad::create([
                'num_unidad'  => $uData['numero'] ?? null,
                'nombre'      => $titulo,
                'descripcion' => $uData['contenidos_items'][0]['item'] ?? null,
                'id_curso'    => $curso->id_curso,
            ]);
        }
    }

    private function createActividadesFromSyllabus(Curso $curso, array $actividadesData): void
    {
        if (empty($actividadesData)) {
            return;
        }

        foreach ($actividadesData as $actData) {
            if (empty($actData['nombre']) || empty($actData['nombre_unidad'])) {
                continue;
            }

            $unidad = Unidad::where('id_curso', $curso->id_curso)
                ->where('nombre', $actData['nombre_unidad'])
                ->first();

            if (!$unidad) {
                Log::warning('Unidad no encontrada para actividad', [
                    'nombre_unidad' => $actData['nombre_unidad'],
                    'id_curso'      => $curso->id_curso,
                ]);
                continue;
            }

            $componente = Componente::where('id_curso', $curso->id_curso)->first();

            if (!$componente) {
                Log::warning('No hay componentes para crear actividad', ['id_curso' => $curso->id_curso]);
                continue;
            }

            Actividad::create([
                'nombre'         => $actData['nombre'],
                'tipo_actividad' => $actData['tipo_actividad'] ?? 1,
                'tipo_entrega'   => $actData['tipo_entrega'] ?? 'online',
                'es_grupal'      => $actData['es_grupal'] ?? false,
                'max_integrantes' => $actData['max_integrantes'] ?? 1,
                'es_plantilla'   => false,
                'id_componente'  => $componente->id_componente,
                'id_unidad'      => $unidad->id_unidad,
                'id_contexto'    => $curso->id_contexto,
                'fecha_limite'   => $actData['fecha_limite'] ?? now()->addDays(7),
                'visible'        => true,
            ]);
        }
    }

    /**
     * Renderizar la vista del programa
     * 
     * @param Curso $curso
     * @param string $mode 'view' o 'edit'
     * @return Response|RedirectResponse
     */
    private function renderProgramaView(Curso $curso, string $mode = 'view'): Response|RedirectResponse
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que ayudante está asignado en este curso
        if (!$curso->id_contexto) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'El curso no tiene contexto configurado');
        }

        // Buscar en UsuarioRolAsignacion: este usuario con rol "ayudante" en este contexto
        $rolAyudante = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();
        
        if (!$rolAyudante) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'Rol de ayudante no configurado en el sistema');
        }

        $asignacion = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->first();

        if (!$asignacion) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No estás asignado a este curso como ayudante');
        }

        // Verificar permiso: curso/programa
        if (!$user->hasPermission(Permissions::CURSOS_PROGRAMAS_VER_TODOS, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para acceder al programa de este curso');
        }

        // Obtener programa actual
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        // Verificar estado para modo edit
        if ($mode === 'edit' && $programa && $programa->estado === 'APROBADO') {
            return redirect()->route('ayudante.cursos.programa.show', $curso->id_curso)
                ->with('error', 'No puedes editar un programa aprobado');
        }

        // Obtener permisos especiales del usuario en el contexto del curso
        $userPermissions = $user->getAllPermissions($curso->id_contexto);
        $userPermissions = collect($userPermissions)->map(function ($perm) {
            return [
                'id_permiso' => $perm['id_permiso'],
                'slug' => $perm['slug'],
                'esta_permitido' => (bool)$perm['esta_permitido'],
                'puede_delegar' => (bool)($perm['puede_delegar'] ?? false),
            ];
        })->values()->toArray();

        // Si no hay programa, mostrar página con aviso
        if (!$programa) {
            $curso->load([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ]);

            $asignatura = $curso->asignacionPlan?->asignatura;

            return Inertia::render('docente/Programa', [
                'programa' => null,
                'curso' => [
                    'id_curso'           => $curso->id_curso,
                    'nombre'             => $curso->nombre,
                    'cod_curso'          => $curso->cod_curso,
                    'id_asignacion_plan' => $curso->id_asignacion_plan,
                    'id_contexto'        => $curso->id_contexto,
                    'asignatura_nombre'  => $asignatura?->nombre,
                    'carrera_nombre'     => $curso->asignacionPlan?->plan?->carrera?->nombre,
                    'asignatura'         => $asignatura,
                    'carrera'            => $curso->asignacionPlan?->plan?->carrera,
                    'creditos_sct'       => $asignatura?->creditos_sct,
                    'horas_catedra'      => $asignatura?->horas_catedra,
                    'horas_taller'       => $asignatura?->horas_taller,
                    'horas_laboratorio'  => $asignatura?->horas_laboratorio,
                ],
                'mode'           => $mode,
                'userPermissions' => $userPermissions,
                'layoutType'     => 'ayudante',
                'backUrl'        => "/ayudante/cursos/{$curso->id_curso}",
            ]);
        }

        // Los datos del programa (secciones y contenidos) están en data_syllabus (JSONB)
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera'
        ]);

        // Procesar data_syllabus correctamente
        $dataSyllabus = is_array($programa->data_syllabus) 
            ? $programa->data_syllabus 
            : json_decode($programa->data_syllabus, true);

        // Parsear secciones
        $secciones = $this->parseSecciones($dataSyllabus);

        // Renombrar contenidos_programa a contenidos para la vista
        foreach ($secciones as &$seccion) {
            $seccion['contenidos'] = $seccion['contenidos_programa'];
            unset($seccion['contenidos_programa']);
        }
        unset($seccion);

        $asignatura = $curso->asignacionPlan?->asignatura;

        // Recuperar última razón de rechazo cuando el programa está en BORRADOR
        $ultimoRechazo = null;
        if ($programa->estado === 'BORRADOR') {
            $ultimoRechazo = ProgramaHistorial::where('id_programa', $programa->id_programa)
                ->where('accion', 'RECHAZO')
                ->orderByDesc('fecha_accion')
                ->first(['observaciones', 'fecha_accion']);
        }

        return Inertia::render('docente/Programa', [
            'programa' => [
                'id_programa'     => $programa->id_programa,
                'id_curso'        => $programa->id_curso,
                'version_programa' => $programa->version_programa,
                'estado'          => $programa->estado,
                'secciones'       => $secciones,
                'creado_por'      => $programa->autor?->nombre,
                'fecha_creacion'  => $programa->fecha_creacion,
                'razon_rechazo'   => $ultimoRechazo?->observaciones,
                'fecha_rechazo'   => $ultimoRechazo?->fecha_accion,
            ],
            'curso' => [
                'id_curso'           => $curso->id_curso,
                'nombre'             => $curso->nombre,
                'cod_curso'          => $curso->cod_curso,
                'id_asignacion_plan' => $curso->id_asignacion_plan,
                'id_contexto'        => $curso->id_contexto,
                'asignatura_nombre'  => $asignatura?->nombre,
                'carrera_nombre'     => $curso->asignacionPlan?->plan?->carrera?->nombre,
                'asignatura'         => $asignatura,
                'carrera'            => $curso->asignacionPlan?->plan?->carrera,
                'creditos_sct'       => $asignatura?->creditos_sct,
                'horas_catedra'      => $asignatura?->horas_catedra,
                'horas_taller'       => $asignatura?->horas_taller,
                'horas_laboratorio'  => $asignatura?->horas_laboratorio,
            ],
            'mode'           => $mode,
            'userPermissions' => $userPermissions,
            'layoutType'     => 'ayudante',
            'backUrl'        => "/ayudante/cursos/{$curso->id_curso}",
        ]);
    }

    /**
     * Convierte data_syllabus de estructura IX-secciones a estructura de SeccionPrograma
     */
    private function parseSecciones(array $data): array
    {
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
            $seccionData = $seccionesData[$romano] ?? [];
            $contenido = $seccionData['contenido'] ?? [];
            
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
     * Extrae contenidos de cada sección para mostrar legible
     */
    private function extraeContenidos(array $contenido, string $seccionId): array
    {
        $contenidos = [];

        if (empty($contenido)) {
            return [['texto_contenido' => '', 'orden_item' => 1]];
        }

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

        // Return single item array with text content
        $contenidos[] = [
            'texto_contenido' => $text,
            'orden_item' => 1,
        ];

        return $contenidos;
    }
}