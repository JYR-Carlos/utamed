<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Services\ProgramaService;
use App\Services\SyllabusStructure;
use App\Traits\ParsesSyllabus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProgramaController extends Controller
{
    use ParsesSyllabus;
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
                'id_curso'                    => $curso->id_curso,
                'nombre'                      => $curso->nombre,
                'cod_curso'                   => $curso->cod_curso,
                'id_asignacion_plan'          => $curso->id_asignacion_plan,
                'id_contexto'                 => $curso->id_contexto,
                'asignatura_nombre'           => $asignatura?->nombre,
                'carrera_nombre'              => $curso->asignacionPlan?->plan?->carrera?->nombre,
                'asignatura'                  => $asignatura,
                'carrera'                     => $curso->asignacionPlan?->plan?->carrera,
                'creditos_sct'                => $asignatura?->creditos_sct,
                'horas_catedra'               => $asignatura?->horas_catedra,
                'horas_taller'                => $asignatura?->horas_taller,
                'horas_laboratorio'           => $asignatura?->horas_laboratorio,
                'fecha_limite_entrega_basico'    => $curso->fecha_limite_entrega_basico,
                'fecha_limite_entrega_syllabus'  => $curso->fecha_limite_entrega_syllabus,
            ],
            'programa'        => $programaData,
            'asignatura'      => $asignatura,
            'canApprove'      => $canApprove,
            'canEdit'         => $canEdit,
            'userPermissions' => $userPermissions,
            'layoutType'      => 'docente',
            'backUrl'         => '/docente/cursos',
        ]);
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

    // ─────────────────────────────────────────────────────────────────────────
    // TRANSICIONES DE ESTADO (llamadas por el docente)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Marca la versión básica del programa como completada (BORRADOR → BASICO_COMPLETO).
     *
     * Una vez completada, la versión básica es visible para alumnos, docentes y administradores
     * sin requerir aprobación.
     *
     * PUT /docente/cursos/{curso}/programa/completar-basico
     */
    public function completarBasico(Curso $curso)
    {
        $this->authorize('viewPrograma', $curso);

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        $this->authorize('update', $programa);

        try {
            $programa = \App\Services\ProgramaService::marcarBasicoCompleto($programa);

            return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
                ->with('success', 'Versión básica marcada como completada. Ahora es visible para los alumnos.');

        } catch (\Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    /**
     * Envía el programa completo para revisión y aprobación (BORRADOR|BASICO_COMPLETO → COMPLETO).
     *
     * Solo la versión COMPLETO requiere aprobación por parte del administrador.
     * Si el programa era BASICO, se convierte automáticamente a COMPLETO.
     *
     * PUT /docente/cursos/{curso}/programa/enviar
     */
    public function enviarParaRevision(Curso $curso)
    {
        $this->authorize('viewPrograma', $curso);

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        $this->authorize('update', $programa);

        try {
            $programa = \App\Services\ProgramaService::enviarParaRevision($programa);

            return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
                ->with('success', 'Programa enviado para revisión. El administrador lo revisará y aprobará.');

        } catch (\Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
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
