<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComponenteRequest;
use App\Http\Requests\UpdateComponenteRequest;
use App\Http\Resources\ComponenteResource;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Services\CursoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Verifica que el componente pertenezca al curso de la URL; si no, aborta 404.
     *
     * Las seis rutas de escritura colgaban directamente del `{componente}`, sin
     * `{curso}` y sin comprobación de pertenencia: bastaba conocer el ID del
     * componente para manipularlo (F-2). El patrón correcto ya estaba escrito en
     * `setTitularByDt`, en este mismo controlador.
     */
    private function assertComponenteDeCurso(Curso $curso, Componente $componente): void
    {
        if ($componente->id_curso === $curso->id_curso) {
            return;
        }

        abort(404, 'Componente no encontrado en este curso.');
    }

    /**
     * Devuelve por qué un componente no se puede borrar sin perder historia, o null
     * si se puede: actividades, mensajes o asistencia ya registrada de sus alumnos.
     */
    private function motivoParaNoEliminar(Componente $componente): ?string
    {
        $actividades = $componente->actividades()->count();
        if ($actividades > 0) {
            return "tiene {$actividades} " . ($actividades === 1 ? 'actividad' : 'actividades');
        }

        // withTrashed: un mensaje borrado (soft delete) sigue bloqueando la FK.
        if ($componente->mensajes()->withTrashed()->exists()) {
            return 'tiene mensajes del curso';
        }

        if ($componente->clases()->exists()) {
            return 'tiene clases registradas';
        }

        $conAsistencia = $componente->inscripcionComponentes()->whereHas('asistencias')->exists();
        if ($conAsistencia) {
            return 'tiene asistencia registrada';
        }

        return null;
    }

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
        $this->authorize('viewAny', Componente::class);
        $componentes = Componente::where('id_curso', $curso->id_curso)
            ->with(['tipoComponente', 'docenteComponentes.docente.usuario'])
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
        $this->authorize('create', Componente::class);
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

            // Componente + docente_componente van juntos: sin transacción, un fallo
            // en el segundo insert deja el componente huérfano (sin docente responsable).
            $componente = DB::transaction(function () use ($curso, $validated) {
                $componente = Componente::create([
                    'id_curso'                          => $curso->id_curso,
                    'id_tipo_componente'                => $validated['id_tipo_componente'],
                    'genera_acta'                       => false,
                    'porcentaje_aprobacion'             => 60,
                    'aprobacion_obligatoria'            => false,
                    'porcentaje_asistencia_obligatoria' => Componente::ASISTENCIA_MINIMA_POR_DEFECTO,
                    // id_contexto es asignado automáticamente por trigger tr_componente_pre_insert
                ])->refresh();

                // Asignar docente a través de la tabla pivot docente_componente
                // El primero asignado es siempre es_titular = true
                if (!empty($validated['id_docente'])) {
                    DocenteComponente::create([
                        'id_componente' => $componente->id_componente,
                        'id_docente'    => $validated['id_docente'],
                        'es_titular'    => true,
                    ]);

                    if ($curso->id_contexto) {
                        $this->assignDocenteRolCurso(
                            $validated['id_docente'],
                            $curso->id_contexto,
                            true,
                            $curso->id_docente_titular,
                            $componente->id_contexto,
                        );
                    }
                }

                return $componente;
            });

            $curso->recalcularColegiado();

            if ($request->wantsJson()) {
                return response()->json([
                    'message'    => 'Componente creado exitosamente.',
                    'componente' => new ComponenteResource($componente->load(['tipoComponente', 'docenteComponentes.docente.usuario'])),
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
    public function update(UpdateComponenteRequest $request, Curso $curso, Componente $componente)
    {
        $this->authorize('update', $componente);
        $this->assertComponenteDeCurso($curso, $componente);

        $validated = $request->validated();

        try {
            $componente->load('curso');
            $cursoContextoId = $componente->curso?->id_contexto;

            $componente->update([
                'id_tipo_componente' => $validated['id_tipo_componente'],
            ]);

            // Actualizar asignación de docente si viene en el request
            if (array_key_exists('id_docente', $validated)) {
                $docentesPrevios = $componente->docenteComponentes()->pluck('id_docente')->all();
                $componente->docenteComponentes()->delete();

                $nuevoDocenteId = !empty($validated['id_docente']) ? (int) $validated['id_docente'] : null;

                if ($componente->id_contexto) {
                    foreach ($docentesPrevios as $idPrevio) {
                        if ($nuevoDocenteId === null || (int) $idPrevio !== $nuevoDocenteId) {
                            app(CursoService::class)->revocarRolesComponenteUsuario(
                                (int) $idPrevio,
                                (int) $componente->id_contexto,
                                $cursoContextoId ? (int) $cursoContextoId : null,
                                $componente->id_curso,
                            );
                        }
                    }
                }

                if ($nuevoDocenteId !== null) {
                    DocenteComponente::create([
                        'id_componente' => $componente->id_componente,
                        'id_docente'    => $nuevoDocenteId,
                        'es_titular'    => true,
                    ]);

                    if ($cursoContextoId) {
                        $this->assignDocenteRolCurso(
                            $nuevoDocenteId,
                            $cursoContextoId,
                            true,
                            $componente->curso?->id_docente_titular,
                            $componente->id_contexto,
                        );
                    }
                }
            }

            $curso->recalcularColegiado();

            if ($request->wantsJson()) {
                return response()->json([
                    'message'    => 'Componente actualizado exitosamente.',
                    'componente' => new ComponenteResource($componente->fresh(['tipoComponente', 'docenteComponentes.docente.usuario'])),
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
    public function destroy(Request $request, Curso $curso, Componente $componente)
    {
        $this->authorize('delete', $componente);
        $this->assertComponenteDeCurso($curso, $componente);

        // Las FK hacia componente son RESTRICT. Lo que es trabajo de alumnos o del
        // docente (actividades, mensajes, asistencia) no se borra en cascada: se avisa.
        $motivo = $this->motivoParaNoEliminar($componente);
        if ($motivo !== null) {
            $mensaje = "No se puede eliminar el componente: {$motivo}.";
            if ($request->wantsJson()) {
                return response()->json(['error' => $mensaje], 422);
            }
            return back()->with('error', $mensaje);
        }

        try {
            // Capturar los docentes y contextos ANTES de borrar el componente
            $idsDocentes = $componente->docenteComponentes()->pluck('id_docente');
            $componente->load('curso');
            $idContextoCurso = $componente->curso?->id_contexto;
            $idContextoComponente = $componente->id_contexto;

            DB::transaction(function () use ($componente, $curso, $idsDocentes, $idContextoCurso, $idContextoComponente) {
                // Sin historia, lo que queda colgando son asignaciones: los docentes del
                // componente y las inscripciones que el trigger creó al inscribir al curso.
                $componente->docenteComponentes()->delete();
                $componente->inscripcionComponentes()->delete();
                $componente->delete();
                $curso->recalcularColegiado();

                if ($idContextoComponente) {
                    foreach ($idsDocentes as $idDocente) {
                        app(CursoService::class)->revocarRolesComponenteUsuario(
                            (int) $idDocente,
                            (int) $idContextoComponente,
                            $idContextoCurso ? (int) $idContextoCurso : null,
                            $curso->id_curso,
                        );
                    }
                }
            });

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
     * Agrega un docente adicional a un componente existente.
     * El primer docente asignado es titular; los siguientes no lo son.
     */
    public function addDocente(Request $request, Curso $curso, Componente $componente)
    {
        $this->authorize('update', $componente);
        $this->assertComponenteDeCurso($curso, $componente);

        $validated = $request->validate([
            'id_docente' => 'required|integer|exists:docente,id_docente',
        ]);

        if ($componente->docenteComponentes()->where('id_docente', $validated['id_docente'])->exists()) {
            return response()->json(['error' => 'El docente ya está asignado a este componente.'], 422);
        }

        // Solo es titular si es el primero en el componente
        $esTitular = $componente->docenteComponentes()->doesntExist();

        $dc = DocenteComponente::create([
            'id_componente' => $componente->id_componente,
            'id_docente'    => $validated['id_docente'],
            'es_titular'    => $esTitular,
        ]);

        $componente->load('curso');
        if ($componente->curso?->id_contexto) {
            $this->assignDocenteRolCurso(
                $validated['id_docente'],
                $componente->curso->id_contexto,
                $esTitular,
                $componente->curso->id_docente_titular,
                $componente->id_contexto,
            );
        }

        $curso->recalcularColegiado();

        $docente = Docente::with('usuario')->find($validated['id_docente']);

        return response()->json([
            'message' => 'Docente agregado exitosamente.',
            'docente_componente' => [
                'id_docente_componente' => $dc->id_docente_componente,
                'id_docente'            => $dc->id_docente,
                'es_titular'            => (bool) $dc->es_titular,
                'nombre_completo'       => trim(($docente?->usuario?->nombre1 ?? '') . ' ' . ($docente?->usuario?->apellido1 ?? '')),
                'cargo'                 => $docente?->cargo,
            ],
        ]);
    }

    /**
     * Quita un docente de un componente.
     * No se puede eliminar el único docente. Si se elimina el titular,
     * el siguiente por orden de id_docente_componente asciende automáticamente.
     */
    public function removeDocente(Curso $curso, Componente $componente, DocenteComponente $docenteComponente)
    {
        $this->authorize('update', $componente);
        $this->assertComponenteDeCurso($curso, $componente);

        if ($docenteComponente->id_componente !== $componente->id_componente) {
            abort(404, 'La asignación docente no pertenece a este componente.');
        }

        if ($componente->docenteComponentes()->count() <= 1) {
            return response()->json(['error' => 'No se puede eliminar el único docente asignado.'], 422);
        }

        $wasTitular = $docenteComponente->es_titular;
        $idDocenteRemovido = $docenteComponente->id_docente;

        if ($wasTitular) {
            $next = $componente->docenteComponentes()
                ->where('id_docente_componente', '!=', $docenteComponente->id_docente_componente)
                ->orderBy('id_docente_componente')
                ->first();
            if ($next) {
                $next->update(['es_titular' => true]);

                // Promover rol del nuevo titular en el contexto del componente
                $componente->load('curso');
                if ($componente->curso?->id_contexto) {
                    $this->assignDocenteRolCurso(
                        $next->id_docente,
                        $componente->curso->id_contexto,
                        true,
                        $componente->curso->id_docente_titular,
                        $componente->id_contexto,
                    );
                }
            }
        }

        $docenteComponente->delete();
        $curso->recalcularColegiado();

        // Revocar rol de este componente al docente eliminado (y limpiar legado en curso si corresponde)
        $componente->load('curso');
        if ($componente->id_contexto) {
            app(CursoService::class)->revocarRolesComponenteUsuario(
                (int) $idDocenteRemovido,
                (int) $componente->id_contexto,
                $componente->curso?->id_contexto ? (int) $componente->curso->id_contexto : null,
                $componente->id_curso,
            );
        }

        return response()->json(['message' => 'Docente eliminado del componente.']);
    }

    /**
     * Cambia el titular de un componente.
     * Solo un docente puede ser titular a la vez.
     */
    public function setTitular(Request $request, Curso $curso, Componente $componente)
    {
        $this->authorize('update', $componente);
        $this->assertComponenteDeCurso($curso, $componente);

        return $this->aplicarTitular($request, $componente);
    }

    /**
     * Cuerpo compartido de setTitular() y setTitularByDt().
     *
     * Cada entrada pública trae su propia autorización —permiso administrativo en
     * un caso, ser el DT del curso en el otro—, así que la comprobación no puede
     * vivir aquí.
     */
    private function aplicarTitular(Request $request, Componente $componente)
    {
        $validated = $request->validate([
            'id_docente_componente' => 'required|integer|exists:docente_componente,id_docente_componente',
        ]);

        $dc = DocenteComponente::where('id_docente_componente', $validated['id_docente_componente'])
            ->where('id_componente', $componente->id_componente)
            ->first();

        if (!$dc) {
            return response()->json(['error' => 'El docente no pertenece a este componente.'], 422);
        }

        // Quitar titular a todos los docentes del componente
        DocenteComponente::where('id_componente', $componente->id_componente)
            ->update(['es_titular' => false]);

        // Asignar al nuevo titular
        $dc->update(['es_titular' => true]);

        // Sincronizar roles en el contexto del componente (y curso para DT)
        $componente->load(['curso', 'docenteComponentes']);
        $idContextoCurso      = $componente->curso?->id_contexto;
        $idContextoComponente = $componente->id_contexto;
        $idDtCurso            = $componente->curso?->id_docente_titular;

        if ($idContextoCurso) {
            // Promover al nuevo titular del componente
            $this->assignDocenteRolCurso($dc->id_docente, $idContextoCurso, true, $idDtCurso, $idContextoComponente);

            // Degradar a los demás docentes de este componente que ya no son titulares aquí
            if ($idContextoComponente) {
                foreach ($componente->docenteComponentes as $otherDc) {
                    if ($otherDc->id_docente_componente === $dc->id_docente_componente) {
                        continue;
                    }
                    app(CursoService::class)->degradarRolEnComponente(
                        $otherDc->id_docente,
                        $idContextoCurso,
                        (int) $idContextoComponente,
                        $idDtCurso,
                    );
                }
            }
        }

        return response()->json(['message' => 'Titular del componente actualizado.']);
    }

    /**
     * Versión del endpoint setTitular accesible para el Docente Titular del Curso.
     * Mismo comportamiento que setTitular(), pero protegido con verificación de identidad
     * del DT del curso en lugar del middleware admin.
     *
     * @param  Request    $request
     * @param  Curso      $curso
     * @param  Componente $componente
     * @return \Illuminate\Http\JsonResponse
     */
    public function setTitularByDt(Request $request, Curso $curso, Componente $componente)
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        if (
            !$user->docente
            || $curso->id_docente_titular !== $user->docente->id_docente
        ) {
            abort(403, 'Solo el Docente Titular del curso puede cambiar el titular de un componente.');
        }

        if ($componente->id_curso !== $curso->id_curso) {
            abort(403, 'El componente no pertenece al curso indicado.');
        }

        return $this->aplicarTitular($request, $componente);
    }

    /**
     * Cambia si un componente genera acta.
     * Valida la regla: solo la componente principal (mayor jerarquía) genera acta por defecto.
     * Advierte al usuario si activa genera_acta en una componente no principal.
     */
    public function toggleGeneraActa(Request $request, Curso $curso, Componente $componente)
    {
        $this->authorize('update', $componente);
        $this->assertComponenteDeCurso($curso, $componente);

        $validated = $request->validate([
            'genera_acta' => 'required|boolean',
        ]);

        $componente->load('tipoComponente');
        $principal = TipoComponente::getComponentePrincipal($componente->id_curso);
        $esComponentePrincipal = $principal && $principal->id_componente === $componente->id_componente;

        $warning = null;
        if ($validated['genera_acta'] && !$esComponentePrincipal) {
            $warning = 'Atención: estás habilitando generación de acta en un componente que NO es el principal del curso. '
                . 'Según la jerarquía (Cátedra > Taller > Laboratorio), solo el componente principal genera acta por defecto.';
        }

        $componente->update(['genera_acta' => $validated['genera_acta']]);

        return response()->json([
            'message' => 'Configuración de acta actualizada.',
            'genera_acta' => (bool) $validated['genera_acta'],
            'es_componente_principal' => $esComponentePrincipal,
            'warning' => $warning,
        ]);
    }

    /**
     * Asigna el rol correcto al usuario en el contexto del curso.
     *
     * Jerarquía de roles por colegiado:
     *   - Docente cuyo id_docente === curso.id_docente_titular → 'Docente Titular'
     *   - Titular del componente (es_titular=true) pero NO el DT del curso → 'Docente Componente'
     *   - Docente no titular en el componente (colegiado) → 'Docente Componente Colegiado'
     *
     * Al ascender en la jerarquía se revocan los roles inferiores previos.
     * No se reemplaza un rol superior por uno inferior.
     *
     * @param  int       $idDocente           ID del docente
     * @param  int       $idContextoCurso     ID del contexto del curso
     * @param  bool      $esTitularComponente true si es titular del componente
     * @param  int|null  $idDocenteTitularCurso  id_docente del DT del curso (desde curso.id_docente_titular)
     * @return void
     */
    private function assignDocenteRolCurso(
        int $idDocente,
        int $idContextoCurso,
        bool $esTitularComponente,
        ?int $idDocenteTitularCurso = null,
        ?int $idContextoComponente = null,
    ): void {
        app(CursoService::class)->asignarRolDocenteComponente(
            $idDocente,
            $idContextoCurso,
            $esTitularComponente,
            $idDocenteTitularCurso,
            $idContextoComponente,
        );
    }
}
