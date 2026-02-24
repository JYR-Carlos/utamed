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
        if ($programa) {
            $canApprove = $user->can('approve', $programa);
        }

        // Convertir JSONB a formato esperado por frontend
        $programaData = null;
        if ($programa && $programa->data_syllabus) {
            $programaData = [
                'id_programa' => $programa->id_programa,
                'version_programa' => $programa->version_programa,
                'estado' => $programa->estado,
                'secciones' => $programa->data_syllabus['secciones'] ?? [],
                'fecha_creacion' => $programa->fecha_creacion,
            ];
        }

        return \Inertia\Inertia::render('docente/Programa', [
            'curso' => $curso,
            'programa' => $programaData,
            'asignatura' => $curso->asignacionPlan?->asignatura,
            'canApprove' => $canApprove,
        ]);
    }

    /**
     * Verifica si el usuario es jefe de carrera
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
