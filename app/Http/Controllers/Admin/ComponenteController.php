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
            // El primero asignado es siempre es_titular = true
            if (!empty($validated['id_docente'])) {
                DocenteComponente::create([
                    'id_componente' => $componente->id_componente,
                    'id_docente'    => $validated['id_docente'],
                    'es_titular'    => true,
                ]);

                if ($curso->id_contexto) {
                    $this->assignDocenteRolCurso($validated['id_docente'], $curso->id_contexto, true, $curso->id_docente_titular);
                }
            }

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
                $componente->docenteComponentes()->delete();

                if (!empty($validated['id_docente'])) {
                    DocenteComponente::create([
                        'id_componente' => $componente->id_componente,
                        'id_docente'    => $validated['id_docente'],
                        'es_titular'    => true,
                    ]);

                    if ($cursoContextoId) {
                        $this->assignDocenteRolCurso(
                            $validated['id_docente'],
                            $cursoContextoId,
                            true,
                            $componente->curso?->id_docente_titular,
                        );
                    }
                }
            }

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
            );
        }

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

                // Promover rol del nuevo titular
                $componente->load('curso');
                if ($componente->curso?->id_contexto) {
                    $this->assignDocenteRolCurso($next->id_docente, $componente->curso->id_contexto, true);
                }
            }
        }

        $docenteComponente->delete();

        // Revocar rol del docente eliminado si ya no tiene ningún componente en el curso
        $componente->load('curso');
        if ($componente->curso?->id_contexto) {
            $tieneOtrosComponentes = DocenteComponente::where('id_docente', $idDocenteRemovido)
                ->whereHas('componente', fn ($q) => $q->where('id_curso', $componente->id_curso))
                ->exists();

            if (!$tieneOtrosComponentes) {
                $this->revokeDocenteRolCurso($idDocenteRemovido, $componente->curso->id_contexto);
            }
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

        // Sincronizar roles en el contexto del curso
        $componente->load(['curso', 'docenteComponentes']);
        $idContextoCurso = $componente->curso?->id_contexto;
        $idCurso         = $componente->id_curso;
        $idDtCurso       = $componente->curso?->id_docente_titular;

        if ($idContextoCurso) {
            // Promover al nuevo titular del componente
            $this->assignDocenteRolCurso($dc->id_docente, $idContextoCurso, true, $idDtCurso);

            // Degradar a los demás docentes del componente que ya no son titulares
            foreach ($componente->docenteComponentes as $otherDc) {
                if ($otherDc->id_docente_componente === $dc->id_docente_componente) {
                    continue;
                }
                $this->downgradeDocenteRolToComponente($otherDc->id_docente, $idContextoCurso, $idCurso, $idDtCurso);
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
    ): void {
        try {
            $docente = Docente::find($idDocente);
            if (!$docente || !$docente->id_usuario) {
                Log::warning('No se pudo asignar rol Docente: docente no encontrado.', [
                    'id_docente' => $idDocente,
                ]);
                return;
            }

            $actorId = Auth::id() ?? $docente->id_usuario;

            // ── Determinar el rol objetivo ─────────────────────────────────
            $esDtCurso = $idDocenteTitularCurso !== null && $idDocente === $idDocenteTitularCurso;

            $rolNombre = match (true) {
                $esDtCurso             => 'Docente Titular',
                $esTitularComponente   => 'Docente Componente',
                default                => 'Docente Componente Colegiado',
            };

            // ── Jerarquía: no degradar si ya tiene un rol superior ─────────
            // Orden descendente: Docente Titular > Docente Componente > Docente Componente Colegiado
            $jerarquia = ['Docente Titular', 'Docente Componente', 'Docente Componente Colegiado'];
            $nivelObjetivo = array_search($rolNombre, $jerarquia);

            $rolesActuales = Rol::whereIn('nombre', $jerarquia)->pluck('id_rol', 'nombre');

            foreach ($jerarquia as $idx => $nombreSuperior) {
                if ($idx >= $nivelObjetivo) {
                    break;
                }
                $idRolSuperior = $rolesActuales[$nombreSuperior] ?? null;
                if ($idRolSuperior) {
                    $tieneSuperior = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $idRolSuperior)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->exists();
                    if ($tieneSuperior) {
                        return; // Ya tiene un rol superior; no degradar
                    }
                }
            }

            // ── Revocar roles inferiores antes de asignar el objetivo ──────
            foreach ($jerarquia as $idx => $nombreInferior) {
                if ($idx <= $nivelObjetivo) {
                    continue;
                }
                $idRolInferior = $rolesActuales[$nombreInferior] ?? null;
                if ($idRolInferior) {
                    UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $idRolInferior)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->update([
                            'esta_activo'    => false,
                            'fue_eliminado'  => true,
                            'fecha_fin_real' => Carbon::now(),
                            'eliminado_por'  => $actorId,
                        ]);
                }
            }

            // ── Asignar rol objetivo si no lo tiene aún ────────────────────
            $rol = Rol::where('nombre', $rolNombre)->first();
            if (!$rol) {
                Log::error("Rol '{$rolNombre}' no existe en la base de datos.");
                return;
            }

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
                Log::info("Rol '{$rolNombre}' asignado en contexto de curso", [
                    'id_usuario'        => $docente->id_usuario,
                    'id_contexto_curso' => $idContextoCurso,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error asignando rol Docente en curso: ' . $e->getMessage(), [
                'id_docente'        => $idDocente,
                'id_contexto_curso' => $idContextoCurso,
            ]);
        }
    }

    /**
     * Degrada el rol de un docente al nivel correcto tras perder la titularidad del componente.
     *
     * Determina el nuevo rol buscando si el docente aún es titular de algún componente en el curso:
     *   - Sí, es titular de otro componente → 'Docente Componente'
     *   - No es titular en ninguno           → 'Docente Componente Colegiado'
     * En cualquier caso se preserva 'Docente Titular' si corresponde al DT del curso.
     *
     * @param  int       $idDocente           ID del docente
     * @param  int       $idContextoCurso     ID del contexto del curso
     * @param  int       $idCurso             ID del curso
     * @param  int|null  $idDocenteTitularCurso  id_docente del DT del curso
     * @return void
     */
    private function downgradeDocenteRolToComponente(
        int $idDocente,
        int $idContextoCurso,
        int $idCurso,
        ?int $idDocenteTitularCurso = null,
    ): void {
        // El DT del curso nunca se degrada desde aquí
        if ($idDocenteTitularCurso !== null && $idDocente === $idDocenteTitularCurso) {
            return;
        }

        // Determinar si sigue siendo titular en algún otro componente del mismo curso
        $isTitularElsewhere = DocenteComponente::where('id_docente', $idDocente)
            ->where('es_titular', true)
            ->whereHas('componente', fn ($q) => $q->where('id_curso', $idCurso))
            ->exists();

        // Si es titular en otro componente, conserva 'Docente Componente'; si no, 'Docente Componente Colegiado'
        $nuevoEsTitular = $isTitularElsewhere;

        try {
            $docente = Docente::find($idDocente);
            if (!$docente || !$docente->id_usuario) {
                return;
            }

            // Revocar todos los roles de docente en el contexto antes de reasignar
            $actorId   = Auth::id() ?? $docente->id_usuario;
            $rolesNames = ['Docente Titular', 'Docente Componente', 'Docente Componente Colegiado'];
            $rolesIds   = Rol::whereIn('nombre', $rolesNames)->pluck('id_rol');

            UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                ->where('id_contexto', $idContextoCurso)
                ->whereIn('id_rol', $rolesIds)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->update([
                    'esta_activo'    => false,
                    'fue_eliminado'  => true,
                    'fecha_fin_real' => Carbon::now(),
                    'eliminado_por'  => $actorId,
                ]);

            $this->assignDocenteRolCurso($idDocente, $idContextoCurso, $nuevoEsTitular, $idDocenteTitularCurso);
        } catch (\Exception $e) {
            Log::error('Error degradando rol Docente: ' . $e->getMessage(), [
                'id_docente' => $idDocente,
            ]);
        }
    }

    /**
     * Revoca todos los roles de docente de un usuario en el contexto del curso.
     * Se llama cuando el docente ya no tiene ningún componente asignado en el curso.
     *
     * @param  int  $idDocente        ID del docente
     * @param  int  $idContextoCurso  ID del contexto del curso
     * @return void
     */
    private function revokeDocenteRolCurso(int $idDocente, int $idContextoCurso): void
    {
        try {
            $docente = Docente::find($idDocente);
            if (!$docente || !$docente->id_usuario) {
                return;
            }

            $actorId = Auth::id() ?? $docente->id_usuario;

            $rolesDocente = Rol::whereIn('nombre', ['Docente', 'Docente Titular', 'Docente Componente', 'Docente Componente Colegiado'])
                ->pluck('id_rol');

            UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                ->where('id_contexto', $idContextoCurso)
                ->whereIn('id_rol', $rolesDocente)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->update([
                    'esta_activo'    => false,
                    'fue_eliminado'  => true,
                    'fecha_fin_real' => Carbon::now(),
                    'eliminado_por'  => $actorId,
                ]);

            Log::info('Roles de docente revocados en curso', [
                'id_usuario'        => $docente->id_usuario,
                'id_contexto_curso' => $idContextoCurso,
            ]);
        } catch (\Exception $e) {
            Log::error('Error revocando rol Docente en curso: ' . $e->getMessage(), [
                'id_docente' => $idDocente,
            ]);
        }
    }
}
