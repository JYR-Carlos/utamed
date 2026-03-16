<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSeccionRequest;
use App\Http\Requests\UpdateSeccionRequest;
use App\Http\Resources\SeccionResource;
use App\Models\Curso\Seccion;
use App\Models\Curso\Curso;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Docente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
     * Obtiene todas las secciones de un curso.
     * 
     * Retorna JSON con los datos de porcentaje de aprobación, asistencia obligatoria, etc.
     * para llenar el wizard de Sección IX (Aspectos Administrativos).
     * 
     * @param  Curso  $curso  Curso cuyas secciones se solicitan
     * @return \Illuminate\Http\JsonResponse  JSON con array de secciones
     */
    public function indexByCurso(Curso $curso)
    {
        $secciones = Seccion::where('id_curso', $curso->id_curso)
            ->with(['tipoSeccion', 'docente.usuario'])
            ->get();

        $componentes = $secciones->map(function ($seccion) {
            return [
                'componente' => $seccion->tipoSeccion?->tipo ?? 'Sección',
                'porcentaje' => $seccion->porcentaje_aprobacion ?? 0,
                'genera_acta' => (bool) $seccion->genera_acta,
                'aprobacion_obligatoria' => (bool) $seccion->aprobacion_obligatoria,
                'asistencia_obligatoria' => $seccion->porcentaje_asistencia_obligatoria ?? 0,
            ];
        })->toArray();

        return response()->json([
            'secciones' => $componentes,
            'total' => count($componentes),
        ]);
    }

    /**
     * Crea una nueva sección para un curso específico.
     * 
     * Valida reglas de negocio: máximo 2 secciones por curso, sin duplicar tipos.
     * Permite asignación opcional de docente responsable.
     * Devuelve JSON si es solicitud AJAX, redirección de vuelta si es formulario tradicional.
     * 
     * @param  StoreSeccionRequest  $request  Datos: id_tipo_seccion, id_docente (opcional)
     * @param  Curso    $curso    Curso al cual agregar la sección
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function store(StoreSeccionRequest $request, Curso $curso)
    {
        $validated = $request->validated();

        try {
            // Validar Reglas de Negocio
            $existingSections = Seccion::where('id_curso', $curso->id_curso)->get();

            // 1. Un curso no puede tener más de tres secciones
            if ($existingSections->count() >= 3) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'El curso no puede tener más de 3 secciones.'], 422);
                }
                return back()->with('error', 'El curso no puede tener más de 3 secciones.');
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
                'genera_acta' => false,
                'porcentaje_aprobacion' => 60,
                'aprobacion_obligatoria' => false,
                'porcentaje_asistencia_obligatoria' => 0
            ]);

            // Asignar rol de Docente al usuario en el contexto del curso
            if ($validated['id_docente'] && $curso->id_contexto) {
                $this->assignDocenteRolCurso(
                    $validated['id_docente'],
                    $curso->id_contexto
                );
            }

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Sección creada exitosamente.', 'seccion' => new SeccionResource($seccion->load(['tipoSeccion', 'docente.usuario']))]);
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
     * @param  UpdateSeccionRequest  $request  Datos actualizados: id_tipo_seccion, id_docente (opcional)
     * @param  Seccion  $seccion  Sección a actualizar
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function update(UpdateSeccionRequest $request, Seccion $seccion)
    {
        $validated = $request->validated();

        try {
            // Obtener el ID del contexto del curso
            $seccion->load('curso');
            $cursoContextoId = $seccion->curso?->id_contexto;

            $seccion->update([
                'id_tipo_seccion' => $validated['id_tipo_seccion'],
                'id_docente' => $validated['id_docente']
            ]);

            // Si se asignó un nuevo docente, asignar rol en el curso
            if ($validated['id_docente'] && $cursoContextoId) {
                $this->assignDocenteRolCurso(
                    $validated['id_docente'],
                    $cursoContextoId
                );
            }

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Sección actualizada exitosamente.', 'seccion' => new SeccionResource($seccion->fresh(['tipoSeccion', 'docente.usuario']))]);
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

    /**
     * Asigna el rol 'Docente' al usuario en el contexto del curso.
     * 
     * Detecta si el docente ya tiene este rol en el contexto y lo crea solo si no existe.
     * 
     * @param  int  $idDocente  ID del docente
     * @param  int  $idContextoCurso  ID del contexto del curso
     * @return void
     */
    private function assignDocenteRolCurso(int $idDocente, int $idContextoCurso): void
    {
        try {
            $docente = Docente::find($idDocente);
            if (!$docente || !$docente->id_usuario) {
                Log::warning('No se pudo asignar rol Docente: docente no encontrado.', [
                    'id_docente' => $idDocente,
                ]);
                return;
            }

            $actorId = Auth::id() ?? $docente->id_usuario;
            $rol = Rol::firstOrCreate(
                ['nombre' => 'Docente'],
                ['creado_por' => $actorId]
            );

            // Verificar si ya tiene el rol (evitar duplicados)
            $already = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                ->where('id_contexto', $idContextoCurso)
                ->where('id_rol', $rol->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->exists();

            if (!$already) {
                $now = Carbon::now();
                UsuarioRolAsignacion::create([
                    'id_usuario'                => $docente->id_usuario,
                    'id_rol'                    => $rol->id_rol,
                    'id_contexto'               => $idContextoCurso,
                    'asignado_por'              => $actorId,
                    'fecha_inicio_planificada'  => $now,
                    'fecha_fin_planificada'     => $now->copy()->addYears(100),
                    'fecha_fin_real'            => null,
                    'fue_eliminado'             => false,
                    'esta_activo'               => true,
                    'creado_por'                => $actorId,
                ]);
                Log::info('Rol Docente asignado en curso', [
                    'id_usuario' => $docente->id_usuario,
                    'id_contexto_curso' => $idContextoCurso,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error asignando rol Docente en curso: ' . $e->getMessage(), [
                'id_docente' => $idDocente,
                'id_contexto_curso' => $idContextoCurso,
            ]);
        }
    }
}
