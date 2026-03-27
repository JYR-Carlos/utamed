<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComponenteRequest;
use App\Http\Requests\UpdateComponenteRequest;
use App\Http\Resources\ComponenteResource;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Docente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Controlador para la gestión de componentes de un curso.
 * 
 * Tablas implicadas:
 * - curso.componente: Componentes (Cátedra, Problemas, Laboratorio) de cada curso
 * - curso.curso: Cursos ofertados en periodos académicos
 * - usuario.docente: Docentes asignados como responsables de componentes
 * - curso.tipo_componente: Tipos válidos de componentes (Cátedra, Problemas, Laboratorio, etc.)
 * 
 * Gestiona la creación, actualización y eliminación de componentes dentro de un curso.
 * Aplica reglas de negocio: máximo 2 componentes por curso, tipo único por componente.
 * Permite asignar docentes responsables y realizar seguimiento.
 */
class ComponenteController extends Controller
{
    /**
     * Obtiene todos los componentes de un curso.
     * 
     * Retorna JSON con los datos de porcentaje de aprobación, asistencia obligatoria, etc.
     * para llenar el wizard de Componente IX (Aspectos Administrativos).
     * 
     * @param  Curso  $curso  Curso cuyos componentes se solicitan
     * @return \Illuminate\Http\JsonResponse  JSON con array de componentes
     */
    public function indexByCurso(Curso $curso)
    {
        $componentes = Componente::where('id_curso', $curso->id_curso)
            ->with(['tipoComponente', 'docentesAsignados.usuario'])
            ->get();

        $componentes = $componentes->map(function ($componente) {
            return [
                'componente' => $componente->tipoComponente?->tipo ?? 'Componente',
                'porcentaje' => $componente->porcentaje_aprobacion ?? 0,
                'genera_acta' => (bool) $componente->genera_acta,
                'aprobacion_obligatoria' => (bool) $componente->aprobacion_obligatoria,
                'asistencia_obligatoria' => $componente->porcentaje_asistencia_obligatoria ?? 0,
            ];
        })->toArray();

        return response()->json([
            'componentes' => $componentes,
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
     * @param  StoreComponenteRequest  $request  Datos: id_tipo_componente, id_docente (opcional)
     * @param  Curso    $curso    Curso al cual agregar el componente
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function store(StoreComponenteRequest $request, Curso $curso)
    {
        $validated = $request->validated();

        try {
            $existingComponentes = Componente::where('id_curso', $curso->id_curso)->get();

            // 1. Un curso no puede tener más de tres componentes
            if ($existingComponentes->count() >= 3) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'El curso no puede tener más de 3 componentes.'], 422);
                }
                return back()->with('error', 'El curso no puede tener más de 3 componentes.');
            }

            // 2. Tipo único por curso (también garantizado por constraint en BD)
            if ($existingComponentes->contains('id_tipo_componente', $validated['id_tipo_componente'])) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Ya existe un componente de este tipo en el curso.'], 422);
                }
                return back()->with('error', 'Ya existe un componente de este tipo en el curso.');
            }

            $componente = Componente::create([
                'id_curso'                          => $curso->id_curso,
                'id_tipo_componente'                => $validated['id_tipo_componente'],
                'genera_acta'                       => false,
                'porcentaje_aprobacion'             => 60,
                'aprobacion_obligatoria'            => false,
                'porcentaje_asistencia_obligatoria' => 0,
                // id_contexto es asignado automáticamente por trigger tr_componente_pre_insert
            ]);

            // Asignar docente a través de la tabla pivot docente_componente
            if (!empty($validated['id_docente'])) {
                DocenteComponente::create([
                    'id_componente' => $componente->id_componente,
                    'id_docente'    => $validated['id_docente'],
                ]);

                if ($curso->id_contexto) {
                    $this->assignDocenteRolCurso($validated['id_docente'], $curso->id_contexto);
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message'    => 'Componente creado exitosamente.',
                    'componente' => new ComponenteResource($componente->load(['tipoComponente', 'docentesAsignados.usuario'])),
                ]);
            }
            return back()->with('success', 'Componente creado exitosamente.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Error al crear el componente: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Error al crear el componente: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza una sección existente (tipo y docente responsable).
     * 
     * Modifica el tipo de sección y/o el docente asignado.
     * Devuelve JSON si es solicitud AJAX, redirección de vuelta si es formulario tradicional.
     * 
     * @param  UpdateComponenteRequest  $request  Datos actualizados: id_tipo_componente, id_docente (opcional)
     * @param  Componente  $componente  Componente a actualizar
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function update(UpdateComponenteRequest $request, Componente $componente)
    {
        $validated = $request->validated();

        try {
            $componente->load('curso');
            $cursoContextoId = $componente->curso?->id_contexto;

            $componente->update([
                'id_tipo_componente' => $validated['id_tipo_componente'],
            ]);

            // Actualizar asignación de docente si viene en el request
            if (array_key_exists('id_docente', $validated)) {
                $componente->docenteComponentes()->delete();

                if (!empty($validated['id_docente'])) {
                    DocenteComponente::create([
                        'id_componente' => $componente->id_componente,
                        'id_docente'    => $validated['id_docente'],
                    ]);

                    if ($cursoContextoId) {
                        $this->assignDocenteRolCurso($validated['id_docente'], $cursoContextoId);
                    }
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message'    => 'Componente actualizado exitosamente.',
                    'componente' => new ComponenteResource($componente->fresh(['tipoComponente', 'docentesAsignados.usuario'])),
                ]);
            }
            return back()->with('success', 'Componente actualizado exitosamente.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Error al actualizar el componente: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Error al actualizar el componente: ' . $e->getMessage());
        }
    }

    /**
     * Elimina una sección del curso.
     * 
     * Borra la sección y sus registros asociados. Devuelve JSON si es AJAX, redirección si es formulario.
     * 
     * @param  Request  $request  Solicitud HTTP
     * @param  Componente  $componente  Componente a eliminar
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  JSON o redirección
     */
    public function destroy(Request $request, Componente $componente)
    {
        try {
            $componente->delete();
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Componente eliminado exitosamente.']);
            }
            return back()->with('success', 'Componente eliminado exitosamente.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Error al eliminar el componente: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Error al eliminar el componente: ' . $e->getMessage());
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
