<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Curso\Unidad;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Controlador para gestión de actividades/tareas en los cursos del docente.
 * 
 * Tablas implicadas:
 * - curso.curso: Cursos donde existen actividades
 * - agenda.actividad: Actividades/tareas del curso
 * - curso.seccion: Secciones del curso (para validar acceso docente)
 * - curso.unidad: Unidades temáticas donde se agrupan actividades
 * - usuario.docente: Perfil del docente autenticado
 * 
 * Permite al docente ver, crear, actualizar y eliminar actividades en sus cursos,
 * validando siempre que sea responsable de alguna sección del curso.
 */
class DocenteActivityController extends Controller
{
    /**
     * Muestra todas las actividades de un curso específico.
     * 
     * Valida que el docente autenticado sea responsable de alguna sección del curso.
     * Obtiene actividades ordenadas por fecha límite y secciones/unidades para edición.
     * 
     * @param  Curso  $curso  Curso cuyas actividades se solicitan
     * @return \Illuminate\Http\Response|\Inertia\Response  Redirección si no autorizado, o vista con actividades
     */
    public function show(Curso $curso)
    {
        // Verify the logged-in user is a docente for this course
        $user = auth()->user();
        
        // Check if user is associated with this course as a docente (through sections)
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para acceder a este curso.');
        }

        // Get activities for this course
        $actividades = Actividad::where('id_curso', $curso->id_curso)
            ->orderBy('fecha_limite', 'asc')
            ->get();

        // Get sections for dropdown
        $secciones = Seccion::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', false)
            ->get();

        // Get units for dropdown
        $unidades = Unidad::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', false)
            ->get();

        return Inertia::render('docente/Actividades', [
            'curso' => $curso,
            'actividades' => $actividades,
            'secciones' => $secciones,
            'unidades' => $unidades,
        ]);
    }

    /**
     * Store a newly created activity
     */
    public function store(Request $request, Curso $curso)
    {
        // Verify the logged-in user is a docente for this course
        $user = auth()->user();
        
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para crear actividades en este curso.');
        }

        // Validate the request
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_limite' => 'required|date',
            'tipo_actividad' => 'required|integer',
            'tipo_entrega' => 'required|string|in:online,presencial,hibrido',
            'es_grupal' => 'boolean',
            'max_integrantes' => 'integer|min:1|max:100',
            'visible' => 'boolean',
            'id_seccion' => 'required|integer',
            'id_unidad' => 'required|integer',
        ]);

        // Add the course ID
        $validated['id_curso'] = $curso->id_curso;
        $validated['es_plantilla'] = false;

        // Create the activity
        try {
            $actividad = Actividad::create($validated);
            
            return redirect()->back()->with('success', "Actividad '{$actividad->nombre}' creada correctamente.");
        } catch (\Exception $e) {
            \Log::error('Error creating activity: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Error al crear la actividad: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Update an activity
     */
    public function update(Request $request, Curso $curso, Actividad $actividad)
    {
        // Verify the logged-in user is a docente for this course
        $user = auth()->user();
        
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para editar esta actividad.');
        }

        // Verify the activity belongs to this course
        if ($actividad->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_limite' => 'required|date',
            'tipo_actividad' => 'required|integer',
            'tipo_entrega' => 'required|string|in:online,presencial,hibrido',
            'es_grupal' => 'boolean',
            'max_integrantes' => 'integer|min:1|max:100',
            'visible' => 'boolean',
            'id_seccion' => 'required|integer',
            'id_unidad' => 'required|integer',
        ]);

        try {
            $actividad->update($validated);
            
            return redirect()->back()->with('success', 'Actividad actualizada correctamente.');
        } catch (\Exception $e) {
            \Log::error('Error updating activity: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Error al actualizar la actividad.'])
                ->withInput();
        }
    }

    /**
     * Delete an activity
     */
    public function destroy(Curso $curso, Actividad $actividad)
    {
        // Verify the logged-in user is a docente for this course
        $user = auth()->user();
        
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }
        
        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();
        
        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para eliminar esta actividad.');
        }

        // Verify the activity belongs to this course
        if ($actividad->id_curso !== $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        try {
            $nombreActividad = $actividad->nombre;
            $actividad->delete();
            
            return redirect()->back()->with('success', "Actividad '{$nombreActividad}' eliminada correctamente.");
        } catch (\Exception $e) {
            \Log::error('Error deleting activity: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Error al eliminar la actividad.']);
        }
    }
}
