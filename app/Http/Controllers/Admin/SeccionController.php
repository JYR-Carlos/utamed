<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso\Seccion;
use App\Models\Curso\Curso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de secciones de un curso.
 * 
 * Tablas implicadas:
 * - curso.seccion: Secciones (Cátedra, Problemas, Laboratorio) de cada curso
 * - curso.curso: Cursos ofertados en periodos académicos
 * - usuario.docente: Docentes asignados como responsables de secciones
 * - curso.tipo_seccion: Tipos válidos de secciones (Cátedra, Problemas, Laboratorio, etc.)
 * 
 * Gestiona la creación, actualización y eliminación de secciones dentro de un curso.
 * Aplica reglas de negocio: máximo 2 secciones por curso, tipo único por sección.
 * Permite asignar docentes responsables y realizar seguimiento.
 */
class SeccionController extends Controller
{
    /**
     * Crea una nueva sección para un curso específico.
     * 
     * Valida reglas de negocio: máximo 2 secciones por curso, sin duplicar tipos.
     * Permite asignación opcional de docente responsable.
     * Devuelve JSON si es solicitud AJAX, redirección de vuelta si es formulario tradicional.
     * 
     * @param  Request  $request  Datos: id_tipo_seccion, id_docente (opcional)
     * @param  Curso    $curso    Curso al cual agregar la sección
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function store(Request $request, Curso $curso)
    {
        $validated = $request->validate([
            'id_tipo_seccion' => 'required|integer|exists:App\Models\Curso\TipoSeccion,id_tipo_seccion',
            'id_docente' => 'nullable|integer|exists:App\Models\Usuario\Docente,id_docente',
        ]);

        try {
            // Validar Reglas de Negocio
            $existingSections = Seccion::where('id_curso', $curso->id_curso)->get();

            // 1. Un curso no puede tener más de dos secciones
            if ($existingSections->count() >= 2) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'El curso no puede tener más de 2 secciones.'], 422);
                }
                return back()->with('error', 'El curso no puede tener más de 2 secciones.');
            }

            // 2. No pueden ser 2 cátedras ni 2 repetidas (Tipo único)
            if ($existingSections->contains('id_tipo_seccion', $validated['id_tipo_seccion'])) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Ya existe una sección de este tipo en el curso.'], 422);
                }
                return back()->with('error', 'Ya existe una sección de este tipo en el curso.');
            }

            $seccion = Seccion::create([
                'id_curso' => $curso->id_curso,
                'id_tipo_seccion' => $validated['id_tipo_seccion'],
                'id_docente' => $validated['id_docente'],
                'es_plantilla' => false
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Sección creada exitosamente.', 'seccion' => $seccion->load('tipoSeccion', 'docente')]);
            }
            return back()->with('success', 'Sección creada exitosamente.'); // Return back for Inertia partial reload
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Error al crear la sección: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Error al crear la sección: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza una sección existente (tipo y docente responsable).
     * 
     * Modifica el tipo de sección y/o el docente asignado.
     * Devuelve JSON si es solicitud AJAX, redirección de vuelta si es formulario tradicional.
     * 
     * @param  Request  $request  Datos actualizados: id_tipo_seccion, id_docente (opcional)
     * @param  Seccion  $seccion  Sección a actualizar
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function update(Request $request, Seccion $seccion)
    {
        $validated = $request->validate([
            'id_tipo_seccion' => 'required|integer|exists:App\Models\Curso\TipoSeccion,id_tipo_seccion',
            'id_docente' => 'nullable|integer|exists:App\Models\Usuario\Docente,id_docente',
        ]);

        try {
            $seccion->update([
                'id_tipo_seccion' => $validated['id_tipo_seccion'],
                'id_docente' => $validated['id_docente']
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Sección actualizada exitosamente.', 'seccion' => $seccion->fresh('tipoSeccion', 'docente')]);
            }
            return back()->with('success', 'Sección actualizada exitosamente.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Error al actualizar la sección: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Error al actualizar la sección: ' . $e->getMessage());
        }
    }

    /**
     * Elimina una sección del curso.
     * 
     * Borra la sección y sus registros asociados. Devuelve JSON si es AJAX, redirección si es formulario.
     * 
     * @param  Request  $request  Solicitud HTTP
     * @param  Seccion  $seccion  Sección a eliminar
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function destroy(Request $request, Seccion $seccion)
    {
        try {
            $seccion->delete();
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Sección eliminada exitosamente.']);
            }
            return back()->with('success', 'Sección eliminada exitosamente.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Error al eliminar la sección: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Error al eliminar la sección: ' . $e->getMessage());
        }
    }
}
