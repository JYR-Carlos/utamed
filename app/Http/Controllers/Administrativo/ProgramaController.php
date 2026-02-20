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
        $curso->load(['asignacionPlan.asignatura']);

        // Obtener programa actual con JSONB
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

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
        ]);
    }

    /**
     * Elimina el programa actual de un curso.
     */
    public function destroy(Curso $curso)
    {
        $this->authorize('delete', $curso);

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return redirect()->back()->with('error', 'No hay programa para eliminar');
        }

        // Soft delete
        $programa->delete();

        return redirect()->route('docente.cursos.index')
            ->with('success', 'Programa eliminado correctamente');
    }
}
