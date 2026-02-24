<?php

namespace App\Http\Controllers\Ayudante;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Support\Permissions;

class ProgramaController extends Controller
{
    /**
     * Ver programa de un curso como ayudante
     * 
     * Requiere permiso: curso/programa: ver
     */
    public function show(Curso $curso)
    {
        return $this->renderProgramaView($curso, 'view');
    }

    /**
     * Editar programa de un curso como ayudante
     * 
     * Requiere permiso: curso/programa: editar
     * Solo disponible si estado != 'APROBADO'
     */
    public function edit(Curso $curso)
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que ayudante está asignado en este curso
        if (!$curso->id_contexto) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'El curso no tiene contexto configurado');
        }

        $rolAyudante = Rol::where('nombre', 'ayudante')->first();
        
        if (!$rolAyudante) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'Rol de ayudante no configurado en el sistema');
        }

        $asignacion = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->first();

        if (!$asignacion) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No estás asignado a este curso como ayudante');
        }

        // Verificar permiso: curso/programa: editar
        if (!$user->hasPermission(Permissions::CURSO_PROGRAMA_EDITAR, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para editar el programa de este curso');
        }

        // Verificar que el programa no está aprobado
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if ($programa && $programa->estado === 'APROBADO') {
            return redirect()->route('ayudante.cursos.programa.show', $curso->id_curso)
                ->with('error', 'No puedes editar un programa aprobado');
        }

        return $this->renderProgramaView($curso, 'edit');
    }

    /**
     * Actualizar programa de un curso como ayudante
     * 
     * Requiere permiso: curso/programa: editar
     * Solo disponible si estado != 'APROBADO'
     */
    public function update(Curso $curso, \Illuminate\Http\Request $request)
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que ayudante está asignado en este curso
        if (!$curso->id_contexto) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'El curso no tiene contexto configurado');
        }

        $rolAyudante = Rol::where('nombre', 'ayudante')->first();
        
        if (!$rolAyudante) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'Rol de ayudante no configurado en el sistema');
        }

        $asignacion = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->first();

        if (!$asignacion) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No estás asignado a este curso como ayudante');
        }

        // Verificar permiso: curso/programa: editar
        if (!$user->hasPermission(Permissions::CURSO_PROGRAMA_EDITAR, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para editar el programa de este curso');
        }

        // Verificar que el programa no está aprobado
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        if ($programa && $programa->estado === 'APROBADO') {
            return redirect()->route('ayudante.cursos.programa.show', $curso->id_curso)
                ->with('error', 'No puedes editar un programa aprobado');
        }

        // Validar datos
        $validated = $request->validate([
            'secciones' => 'required|array',
            'secciones.*.nombre_seccion' => 'required|string',
            'secciones.*.orden' => 'required|integer',
            'secciones.*.contenidos' => 'nullable|array',
            'secciones.*.contenidos.*.texto_contenido' => 'nullable|string',
            'secciones.*.contenidos.*.orden_item' => 'required|integer',
        ]);

        try {
            $overrides = [
                'secciones' => $validated['secciones']
            ];

            $programa = \App\Services\ProgramaService::generateProgramaWithSyllabus(
                $curso,
                $user,
                $overrides
            );

            return redirect()->route('ayudante.cursos.programa.show', $curso->id_curso)
                ->with('success', 'Programa actualizado correctamente.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al actualizar el programa: ' . $e->getMessage());
        }
    }

    /**
     * Renderizar la vista del programa
     * 
     * @param Curso $curso
     * @param string $mode 'view' o 'edit'
     * @return \Inertia\Response|RedirectResponse
     */
    private function renderProgramaView(Curso $curso, string $mode = 'view')
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que ayudante está asignado en este curso
        if (!$curso->id_contexto) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'El curso no tiene contexto configurado');
        }

        // Buscar en UsuarioRolAsignacion: este usuario con rol "ayudante" en este contexto
        $rolAyudante = Rol::where('nombre', 'ayudante')->first();
        
        if (!$rolAyudante) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'Rol de ayudante no configurado en el sistema');
        }

        $asignacion = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_rol', $rolAyudante->id_rol)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->first();

        if (!$asignacion) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No estás asignado a este curso como ayudante');
        }

        // Verificar permiso: curso/programa: ver
        if (!$user->hasPermission(Permissions::CURSO_PROGRAMA_VER, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para ver el programa de este curso');
        }

        // Verificar permiso para editar si es modo edit
        if ($mode === 'edit' && !$user->hasPermission(Permissions::CURSO_PROGRAMA_EDITAR, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para editar el programa de este curso');
        }

        // Obtener programa actual
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_actual', true)
            ->first();

        // Verificar estado para modo edit
        if ($mode === 'edit' && $programa && $programa->estado === 'APROBADO') {
            return redirect()->route('ayudante.cursos.programa.show', $curso->id_curso)
                ->with('error', 'No puedes editar un programa aprobado');
        }

        // Si no hay programa, mostrar página con aviso
        if (!$programa) {
            $curso->load([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ]);

            return Inertia::render('ayudante/Courses/Programa', [
                'programa' => null,
                'curso' => [
                    'id_curso' => $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                    'asignatura' => $curso->asignacionPlan?->asignatura?->nombre,
                    'carrera' => $curso->asignacionPlan?->plan?->carrera?->nombre,
                ],
                'mode' => $mode,
            ]);
        }

        // Cargar secciones y contenidos del programa
        $programa->load('secciones.contenidos');

        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera'
        ]);

        return Inertia::render('ayudante/Courses/Programa', [
            'programa' => [
                'id_programa' => $programa->id_programa,
                'id_curso' => $programa->id_curso,
                'version' => $programa->version,
                'estado' => $programa->estado,
                'secciones' => $programa->secciones->map(function ($seccion) {
                    return [
                        'id_estructura_programa' => $seccion->id_estructura_programa,
                        'nombre_seccion' => $seccion->nombre_seccion,
                        'numeral_romano' => $seccion->numeral_romano,
                        'orden' => $seccion->orden,
                        'contenidos_programa' => $seccion->contenidos->map(function ($contenido) {
                            return [
                                'id_contenido_programa' => $contenido->id_contenido_programa,
                                'texto_contenido' => $contenido->texto_contenido,
                                'orden_item' => $contenido->orden_item,
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
                'creado_por' => $programa->creadoPor?->nombre_completo,
                'fecha_creacion' => $programa->fecha_creacion,
            ],
            'curso' => [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura' => $curso->asignacionPlan?->asignatura?->nombre,
                'carrera' => $curso->asignacionPlan?->plan?->carrera?->nombre,
            ],
            'mode' => $mode,
        ]);
    }

}
