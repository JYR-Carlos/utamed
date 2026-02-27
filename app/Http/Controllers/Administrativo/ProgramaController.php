<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Services\ProgramaService;
use App\Services\SyllabusStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProgramaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * 
     * Si se envían secciones customizadas, actualiza el syllabus con esos contenidos.
     * Si no, genera la estructura base automáticamente.
     */
    public function store(Request $request, Curso $curso)
    {
        $user = Auth::user();

        // Validar que el docente tiene acceso a este curso
        $this->authorize('viewPrograma', $curso);

        // Validar que el docente tiene permiso para crear programa en este curso
        // Esto incluye: ser docente asignado + tener permisos específicos
        if (!$user->is_admin) {
            // Para docentes, validar directamente via policy
            $programaPolicy = new \App\Policies\ProgramaPolicy();
            if (!$programaPolicy->create($user, $curso)) {
                abort(403, 'No tienes permiso para crear programas en este curso');
            }
        }

        // Validar si se envían secciones
        if ($request->has('secciones')) {
            $request->validate([
                'secciones' => 'required|array',
                'secciones.*.nombre_seccion' => 'required|string',
                'secciones.*.orden' => 'required|integer',
                'secciones.*.contenidos' => 'nullable|array',
                'secciones.*.contenidos.*.texto_contenido' => 'nullable|string',
                'secciones.*.contenidos.*.orden_item' => 'required|integer',
            ]);
            
            // Generar con secciones customizadas
            $overrides = [
                'secciones' => $request->secciones
            ];
        } else {
            $overrides = null;
        }

        try {
            $programa = ProgramaService::generateProgramaWithSyllabus(
                $curso,
                $user,
                $overrides
            );

            return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
                ->with('success', 'Programa generado correctamente.');

        } catch (\Exception $e) {
            return Redirect::back()->with('error', 'Error al generar el programa: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Curso $curso)
    {
        // Validar que el usuario tiene acceso a este curso para ver programas
        // Rechaza acceso a cursos no asignados al docente
        $this->authorize('viewPrograma', $curso);

        $curso->load(['asignacionPlan.asignatura']);

        // Obtener programa actual con JSONB
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        // Validar autorización si existe programa
        if ($programa) {
            $this->authorize('view', $programa);
        }

        // Verificar si el usuario puede aprobar programas usando la Policy
        $user = Auth::user();
        $canApprove = false;

        // Calcular canEdit sin pasar por BaseProgramaPolicy::update() que tiene un bug
        // con Permissions enum vs string. En su lugar replicamos la lógica directamente:
        // - Admin siempre puede editar
        // - Docente asignado al curso puede editar si el programa no está APROBADO
        $isAdmin = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereIn('nombre', ['Administrador', 'SuperAdmin', 'Super Admin', 'Admin'])
            ->exists();

        $isAssignedDocente = $user->docente
            ? $user->docente->secciones()->where('id_curso', $curso->id_curso)->exists()
            : false;

        $editableState = !$programa || !in_array($programa->estado, ['APROBADO']);

        $canEdit = $editableState && ($isAdmin || $isAssignedDocente);

        if ($programa) {
            $canApprove = $user->can('approve', $programa);
        }

        // Convertir JSONB a formato esperado por frontend
        $programaData = null;
        if ($programa && $programa->data_syllabus) {
            $dataSyllabus = is_array($programa->data_syllabus)
                ? $programa->data_syllabus
                : json_decode($programa->data_syllabus, true);

            $secciones = $this->parseSecciones($dataSyllabus);

            // Renombrar contenidos_programa → contenidos para la vista
            foreach ($secciones as &$seccion) {
                $seccion['contenidos'] = $seccion['contenidos_programa'];
                unset($seccion['contenidos_programa']);
            }
            unset($seccion);

            $programaData = [
                'id_programa'      => $programa->id_programa,
                'version_programa' => $programa->version_programa,
                'estado'           => $programa->estado,
                'secciones'        => $secciones,
                'fecha_creacion'   => $programa->fecha_creacion,
            ];
        }

        // Permisos del usuario en el contexto del curso
        $userPermissions = collect($user->getAllPermissions($curso->id_contexto))->map(fn($p) => [
            'id_permiso'    => $p['id_permiso'],
            'slug'          => $p['slug'],
            'esta_permitido' => (bool) $p['esta_permitido'],
            'puede_delegar' => (bool) ($p['puede_delegar'] ?? false),
        ])->values()->toArray();

        $curso->load(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera']);
        $asignatura = $curso->asignacionPlan?->asignatura;

        return \Inertia\Inertia::render('docente/Programa', [
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
            'programa'        => $programaData,
            'asignatura'      => $asignatura,
            'canApprove'      => $canApprove,
            'canEdit'         => $canEdit,
            'userPermissions' => $userPermissions,
        ]);
    }

    /**
     * Convierte data_syllabus de estructura IX-secciones a array de SeccionPrograma.
     */
    private function parseSecciones(array $data): array
    {
        $seccionesData = $data['secciones'] ?? $data;

        $romanos = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
        $nombres = [
            'I'    => 'Identificación',
            'II'   => 'Presentación',
            'III'  => 'Estándares',
            'IV'   => 'Competencias',
            'V'    => 'Evaluación Diagnóstica',
            'VI'   => 'Unidades',
            'VII'  => 'Planificación',
            'VIII' => 'Recursos',
            'IX'   => 'Aspectos Administrativos',
        ];

        $secciones = [];
        foreach ($romanos as $idx => $romano) {
            $seccionData      = $seccionesData[$romano] ?? [];
            $contenido        = $seccionData['contenido'] ?? [];
            $contenidosPrograma = $this->extraeContenidos($contenido, $romano);

            $seccion = [
                'nombre_seccion'     => $nombres[$romano] ?? "Sección $romano",
                'numeral_romano'     => $romano,
                'orden'              => $idx + 1,
                'contenidos_programa' => $contenidosPrograma,
            ];

            if ($romano === 'IX') {
                $seccion['componentes']          = $contenido['tabla_componentes'] ?? [];
                $seccion['ponderacion_optativa'] = $contenido['ponderacion_optativa'] ?? [];
            }

            $secciones[] = $seccion;
        }

        return $secciones;
    }

    private function extraeContenidos(array $contenido, string $seccionId): array
    {
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
                $esp = implode("\n", array_map(fn($c) => '• ' . ($c['titulo'] ?? ''), $contenido['competencias_especificas'] ?? []));
                $gen = implode("\n", array_map(fn($c) => '• ' . ($c['titulo'] ?? ''), $contenido['competencias_genericas'] ?? []));
                $sub = implode("\n", array_map(fn($c) => '• ' . ($c['titulo'] ?? ''), $contenido['subcompetencias'] ?? []));
                $text = "Específicas:\n$esp\n\nGenéricas:\n$gen\n\nSub:\n$sub";
                break;
            case 'V':
                $text = implode("\n", array_map(
                    fn($i) => '• ' . ($i['titulo'] ?? '') . ': ' . ($i['descripcion'] ?? ''),
                    $contenido['items'] ?? []
                ));
                break;
            case 'VI':
                $unidadesText = array_map(function ($u) {
                    $resultados = implode("\n  ", array_map(
                        fn($r) => '• ' . ($r['resultado'] ?? ''),
                        $u['resultados_aprendizaje'] ?? []
                    ));
                    return sprintf(
                        "Unidad %d: %s\nContenidos: %s\nResultados:\n  %s",
                        $u['numero'] ?? 0,
                        $u['titulo'] ?? '',
                        implode(', ', array_map(fn($c) => $c['item'] ?? '', $u['contenidos_items'] ?? [])),
                        $resultados
                    );
                }, $contenido['unidades'] ?? []);
                $text = implode("\n\n", $unidadesText);
                break;
            case 'VII':
                $resultados = implode("\n", array_map(
                    fn($r) => '• ' . ($r['resultado'] ?? ''),
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
                    fn($r) => '• ' . ($r['recurso'] ?? ''),
                    $contenido['recursos'] ?? []
                ));
                break;
            case 'IX':
                $text = sprintf(
                    "Asistencia mín.: %s%%\nReprobación: %s\nNota mínima aprobación: %s",
                    $contenido['porcentaje_asistencia_minima'] ?? '',
                    $contenido['condicion_reprobacion'] ?? '',
                    $contenido['nota_minima_aprobacion'] ?? ''
                );
                break;
            default:
                $text = json_encode($contenido, JSON_UNESCAPED_UNICODE);
        }

        return [['texto_contenido' => $text ?? '', 'orden_item' => 1]];
    }

    /**
     * @deprecated Use $user->can() with ProgramaPolicy instead
     */
    private function isJefeDeCarrera($user): bool
    {
        return $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereIn('nombre', ['jefe de carrera', 'coordinador de carrera'])
            ->exists();
    }

    /**
     * Aprueba un programa
     */
    public function approve(Curso $curso)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        // Validar que el usuario tiene permiso para aprobar usando la Policy
        $this->authorize('approve', $programa);

        $user = Auth::user();

        $programa->update([
            'estado' => 'APROBADO',
            'aprobado_por' => $user->id_usuario,
            'fecha_aprobacion' => now(),
        ]);

        return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
            ->with('success', 'Programa aprobado correctamente.');
    }

    /**
     * Rechaza un programa
     */
    public function reject(Curso $curso, Request $request)
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        // Validar que el usuario tiene permiso para rechazar usando la Policy
        $this->authorize('reject', $programa);

        $request->validate([
            'razon_rechazo' => 'required|string|max:500',
        ]);

        $user = Auth::user();

        $programa->update([
            'estado' => 'BORRADOR',
            'razon_rechazo' => $request->razon_rechazo,
            'rechazado_por' => $user->id_usuario,
            'fecha_rechazo' => now(),
        ]);

        return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
            ->with('warning', 'Programa rechazado. Se devolvió a estado de borrador para revisión.');
    }

    /**
     * Elimina el programa actual de un curso.
     */
    public function destroy(Curso $curso)
    {
        // Validar que el docente tiene acceso a este curso
        $this->authorize('viewPrograma', $curso);

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        // Validar autorización para eliminar
        if ($programa) {
            $this->authorize('delete', $programa);
        } else {
            abort(404, 'Programa no encontrado');
        }

        if (!$programa) {
            return redirect()->back()->with('error', 'No hay programa para eliminar');
        }

        // Soft delete
        $programa->delete();

        return redirect()->route('docente.cursos.index')
            ->with('success', 'Programa eliminado correctamente');
    }
}
