<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Curso\Programa;
use App\Models\Curso\Curso;
use App\Services\ProgramaService;
use App\Services\SyllabusViewerPresenter;
use App\Services\SyllabusWizardPresenter;
use App\Traits\ParsesSyllabus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión del programa/syllabus de un curso y su flujo de aprobación
 * (perspectiva administrativa). Renderiza la misma vista que el docente
 * (`docente/Programa`) y gobierna las transiciones de estado del programa:
 * generación, completar básico, enviar a revisión, aprobar, rechazar y eliminar.
 *
 * El armado del syllabus se delega en ProgramaService; los rechazos usan
 * `SET LOCAL` dentro de una transacción para alimentar el trigger de auditoría.
 */
class ProgramaController extends Controller
{
    use ParsesSyllabus;

    /**
     * Genera el programa de un curso.
     *
     * Si se envían secciones customizadas, actualiza el syllabus con esos contenidos.
     * Si no, genera la estructura base automáticamente.
     */
    public function store(Request $request, Curso $curso): RedirectResponse
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Validar que el docente tiene acceso a este curso
        $this->authorize('viewPrograma', $curso);

        // Validar que el docente tiene permiso para crear programa en este curso
        // Esto incluye: ser docente asignado + tener permisos específicos
        if (!$user->is_admin) {
            // Para docentes, validar directamente via policy
            $this->authorize('create', Programa::class);
        }

        // Validar si se envían secciones
        //
        // Nota: esta ruta no está registrada en routes/web.php y su forma
        // (`secciones.*.nombre_seccion`) es la legacy, incompatible con los DTO de
        // App\Syllabus, que esperan claves romanas. Se mantiene acotada por
        // higiene: lo que llega al JSONB son las claves validadas, no el input
        // crudo del request.
        if ($request->has('secciones')) {
            $validated = $request->validate([
                'secciones.*.nombre_seccion' => 'required|string|max:255',
                'secciones.*.orden' => 'required|integer|min:0|max:1000',
                'secciones.*.contenidos' => 'nullable|array|max:200',
                'secciones.*.contenidos.*.texto_contenido' => 'nullable|string|max:20000',
                'secciones.*.contenidos.*.orden_item' => 'required|integer|min:0|max:1000',
            ]);

            // Generar con secciones customizadas
            $overrides = [
                'secciones' => $validated['secciones']
            ];
        } else {
            $overrides = null;
        }

        try {
            /** @var \App\Models\Usuario\Usuario $user */
            $user = Auth::user();
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
     * Muestra el programa actual del curso y los permisos del usuario sobre él.
     */
    public function show(Curso $curso): Response
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

        // Calcular canEdit: - Admin siempre puede editar
        // - Docente asignado al curso puede editar si el programa no está APROBADO
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();
        
        $isAdmin = $user->is_admin;
        $isAssignedDocente = $user->docente
            ? Curso::where('id_curso', $curso->id_curso)->where('id_docente_titular', $user->docente->id_docente)->exists()
            : false;

        $editableState = !$programa || !in_array($programa->estado, ['APROBADO']);

        $canEdit = $editableState && ($isAdmin || $isAssignedDocente);

        if ($programa) {
            /** @var \App\Models\Usuario\Usuario $user */
            $user = Auth::user();
            $canApprove = $user->can('approve', $programa);
        }

        // Convertir JSONB a formato esperado por frontend
        $programaData = null;
        if ($programa && $programa->data_syllabus) {
            $dataSyllabus = is_array($programa->data_syllabus)
                ? $programa->data_syllabus
                : json_decode($programa->data_syllabus, true);

            $secciones = $this->parseSecciones($dataSyllabus);

            // Fechas, autoría e historial salen de auditoria.programa_historial:
            // curso.programa no tiene ninguna columna de fecha (ver presenter).
            $programaData = array_merge(
                [
                    'id_programa'      => $programa->id_programa,
                    'version_programa' => $programa->version_programa,
                    'estado'           => $programa->estado,
                    'secciones'        => $secciones,
                ],
                SyllabusViewerPresenter::documento($programa),
                SyllabusViewerPresenter::ultimoRechazo($programa),
            );
        }

        // Permisos del usuario en el contexto del curso
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();
        $userPermissions = collect($user->getAllPermissions($curso->id_contexto))->map(fn($p) => [
            'id_permiso'    => $p['id_permiso'],
            'slug'          => $p['slug'],
            'esta_permitido' => (bool) $p['esta_permitido'],
            'puede_delegar' => (bool) ($p['puede_delegar'] ?? false),
        ])->values()->toArray();

        $curso->load(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera']);
        $asignatura = $curso->asignacionPlan?->asignatura;

        return Inertia::render('docente/Programa', [
            'curso' => array_merge(
                [
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
                SyllabusViewerPresenter::cabeceraCurso($curso),
            ),
            'programa'        => $programaData,
            'asignatura'      => $asignatura,
            'canApprove'      => $canApprove,
            'canEdit'         => $canEdit,
            'canCreate'       => $programa === null && $user->can('create', [Programa::class, $curso]),
            'canDelete'       => $programa !== null && $user->can('delete', $programa),
            'userPermissions' => $userPermissions,
            'layoutType'      => 'docente',
            'backUrl'         => '/docente/cursos',
        ]);
    }

    /**
     * Página del asistente de syllabus — `/docente/cursos/{curso}/programa/editar`.
     *
     * El asistente dejó de ser un modal: es una pantalla propia con su URL, de
     * modo que se puede enlazar, recargar y volver atrás. Sirve tanto para crear
     * (sin programa aún, con `?tipo=BASICO|COMPLETO`) como para editar el
     * programa vigente, y para promover un BÁSICO a COMPLETO (`?tipo=COMPLETO`).
     *
     * Guardar sigue yendo a `POST {base}/cursos/{curso}/programa`, que crea una
     * versión nueva; por eso aquí no hay autoguardado.
     */
    public function edit(Request $request, Curso $curso): Response|RedirectResponse
    {
        $this->authorize('viewPrograma', $curso);

        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if ($programa) {
            $this->authorize('view', $programa);

            if ($programa->estado === 'APROBADO') {
                return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
                    ->with('error', 'El syllabus está aprobado: solicita la reapertura a tu jefatura antes de editarlo.');
            }
        } else {
            $this->authorize('create', [Programa::class, $curso]);
        }

        // Sin ninguna sección escribible el asistente no tiene nada que ofrecer,
        // y el guardado respondería 403 igualmente.
        if (SyllabusWizardPresenter::seccionesEditables($user, $curso) === []) {
            return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
                ->with('error', 'Ninguna sección del syllabus está delegada a ti en este curso. Pide a la jefatura o al docente titular que te asigne los módulos que debes redactar.');
        }

        $tipo = strtoupper((string) $request->query('tipo', ''));
        $tipoSolicitado = in_array($tipo, ['BASICO', 'COMPLETO'], true) ? $tipo : null;

        return Inertia::render('docente/SyllabusWizard', SyllabusWizardPresenter::build(
            $curso,
            $user,
            $programa,
            $tipoSolicitado,
            'docente',
        ));
    }

    /**
     * Aprueba un programa
     */
    public function approve(Curso $curso): RedirectResponse
    {
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        // Validar que el usuario tiene permiso para aprobar usando la Policy
        $this->authorize('approve', $programa);

        $user = Auth::user();

        // `curso.programa` no tiene `aprobado_por` ni `fecha_aprobacion`: la firma
        // real es `revisado_por` y la fecha la deja el trigger de auditoría.
        $programa->update([
            'estado'       => 'APROBADO',
            'revisado_por' => $user->id_usuario,
        ]);

        return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
            ->with('success', 'Programa aprobado correctamente.');
    }

    /**
     * Rechaza un programa
     */
    public function reject(Curso $curso, Request $request): RedirectResponse
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
        $razonRechazo = trim($request->razon_rechazo);

        // Envolver en transacción para que SET LOCAL aplique al trigger de auditoría
        DB::transaction(function () use ($programa, $razonRechazo, $user) {
            DB::statement("SELECT set_config('app.accion_tipo',   'RECHAZO', true)");
            DB::statement("SELECT set_config('app.razon_rechazo', ?, true)", [$razonRechazo]);
            DB::statement("SELECT set_config('app.actor_id',      ?, true)", [(string) $user->id_usuario]);

            $programa->update(['estado' => 'BORRADOR']);
        });

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
    public function completarBasico(Curso $curso): RedirectResponse
    {
        $this->authorize('viewPrograma', $curso);

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        $this->authorize('update', $programa);

        try {
            $programa = ProgramaService::marcarBasicoCompleto($programa);

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
    public function enviarParaRevision(Curso $curso): RedirectResponse
    {
        $this->authorize('viewPrograma', $curso);

        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->firstOrFail();

        $this->authorize('update', $programa);

        try {
            $programa = ProgramaService::enviarParaRevision($programa);

            return Redirect::route('docente.cursos.programa.show', $curso->id_curso)
                ->with('success', 'Programa enviado para revisión. El administrador lo revisará y aprobará.');

        } catch (\Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    /**
     * Elimina el programa actual de un curso.
     */
    public function destroy(Curso $curso): RedirectResponse
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
