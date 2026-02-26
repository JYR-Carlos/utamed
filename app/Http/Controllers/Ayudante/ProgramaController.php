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

        // Verificar permiso: cursos/programas
        if (!$user->hasPermission(Permissions::CURSOS_PROGRAMAS_VER, $curso->id_contexto)) {
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

        // Verificar permiso: cursos/programas
        if (!$user->hasPermission(Permissions::CURSOS_PROGRAMAS_VER, $curso->id_contexto)) {
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

        // Verificar permiso: curso/programa
        if (!$user->hasPermission(Permissions::CURSOS_PROGRAMAS_VER, $curso->id_contexto)) {
            return redirect()->route('ayudante.cursos.index')
                ->with('error', 'No tienes permiso para acceder al programa de este curso');
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

        // Los datos del programa (secciones y contenidos) están en data_syllabus (JSONB)
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera'
        ]);

        // Procesar data_syllabus correctamente
        $dataSyllabus = is_array($programa->data_syllabus) 
            ? $programa->data_syllabus 
            : json_decode($programa->data_syllabus, true);

        // Parsear secciones
        $secciones = $this->parseSecciones($dataSyllabus);

        // Renombrar contenidos_programa a contenidos para la vista
        foreach ($secciones as &$seccion) {
            $seccion['contenidos'] = $seccion['contenidos_programa'];
            unset($seccion['contenidos_programa']);
        }
        unset($seccion);

        return Inertia::render('ayudante/Courses/Programa', [
            'programa' => [
                'id_programa' => $programa->id_programa,
                'id_curso' => $programa->id_curso,
                'version' => $programa->version_programa,
                'estado' => $programa->estado,
                'secciones' => $secciones,
                'creado_por' => $programa->autor?->nombre,
                'fecha_creacion' => $programa->fecha_creacion,
            ],
            'curso' => [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura' => $curso->asignacionPlan?->asignatura,
                'carrera' => $curso->asignacionPlan?->plan?->carrera,
            ],
            'mode' => $mode,
        ]);
    }

    /**
     * Convierte data_syllabus de estructura IX-secciones a estructura de SeccionPrograma
     */
    private function parseSecciones(array $data): array
    {
        $seccionesData = $data['secciones'] ?? $data;
        
        $romanos = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
        $nombres = [
            'I' => 'Identificación',
            'II' => 'Presentación',
            'III' => 'Estándares',
            'IV' => 'Competencias',
            'V' => 'Evaluación Diagnóstica',
            'VI' => 'Unidades',
            'VII' => 'Planificación',
            'VIII' => 'Recursos',
            'IX' => 'Aspectos Administrativos',
        ];

        $secciones = [];
        foreach ($romanos as $idx => $romano) {
            $seccionData = $seccionesData[$romano] ?? [];
            $contenido = $seccionData['contenido'] ?? [];
            
            $contenidosPrograma = $this->extraeContenidos($contenido, $romano);

            $seccion = [
                'nombre_seccion' => $nombres[$romano] ?? "Sección $romano",
                'numeral_romano' => $romano,
                'orden' => $idx + 1,
                'contenidos_programa' => $contenidosPrograma,
            ];

            // For section IX, add structured component data
            if ($romano === 'IX') {
                $seccion['componentes'] = $contenido['tabla_componentes'] ?? [];
                $seccion['ponderacion_optativa'] = $contenido['ponderacion_optativa'] ?? [];
            }

            $secciones[] = $seccion;
        }

        return $secciones;
    }

    /**
     * Extrae contenidos de cada sección para mostrar legible
     */
    private function extraeContenidos(array $contenido, string $seccionId): array
    {
        $contenidos = [];

        if (empty($contenido)) {
            return [['texto_contenido' => '', 'orden_item' => 1]];
        }

        switch ($seccionId) {
            case 'I':
                $text = sprintf(
                    "Asignatura: %s\nCódigo: %s\nCréditos SCT: %s\nHoras Cátedra: %s, Taller: %s, Lab: %s\nCategoría: %s",
                    $contenido['nombre_asignatura'] ?? '',
                    $contenido['codigo'] ?? '',
                    $contenido['creditos_sct'] ?? '',
                    $contenido['horas']['catedra'] ?? 0,
                    $contenido['horas']['taller'] ?? 0,
                    $contenido['horas']['laboratorio'] ?? 0,
                    $contenido['categoria'] ?? ''
                );
                break;

            case 'II':
            case 'III':
                $text = $contenido['texto'] ?? '';
                break;

            case 'IV':
                $esp = implode("\n", array_map(fn($c) => "• " . ($c['titulo'] ?? ''), $contenido['competencias_especificas'] ?? []));
                $gen = implode("\n", array_map(fn($c) => "• " . ($c['titulo'] ?? ''), $contenido['competencias_genericas'] ?? []));
                $sub = implode("\n", array_map(fn($c) => "• " . ($c['titulo'] ?? ''), $contenido['subcompetencias'] ?? []));
                $text = "Específicas:\n$esp\n\nGenéricas:\n$gen\n\nSub:\n$sub";
                break;

            case 'V':
                $text = implode("\n", array_map(
                    fn($i) => "• " . ($i['titulo'] ?? '') . ": " . ($i['descripcion'] ?? ''),
                    $contenido['items'] ?? []
                ));
                break;

            case 'VI':
                $unidadesText = array_map(function ($u) {
                    $resultados = implode("\n  ", array_map(
                        fn($r) => "• " . ($r['resultado'] ?? ''),
                        $u['resultados_aprendizaje'] ?? []
                    ));
                    return sprintf(
                        "Unidad %d: %s\nContenidos: %s\nResultados:\n  %s",
                        $u['numero'] ?? 0,
                        $u['titulo'] ?? '',
                        implode(", ", array_map(fn($c) => $c['item'] ?? '', $u['contenidos_items'] ?? [])),
                        $resultados
                    );
                }, $contenido['unidades'] ?? []);
                $text = implode("\n\n", $unidadesText);
                break;

            case 'VII':
                $resultados = implode("\n", array_map(
                    fn($r) => "• " . ($r['resultado'] ?? ''),
                    $contenido['resultados_aprendizaje']['items'] ?? []
                ));
                $text = sprintf(
                    "Resultados de Aprendizaje:\n%s\n\nMetodología:\n%s\n\nEvaluación:\n%s",
                    $resultados,
                    $contenido['metodologia']['tipo_estrategia'] ?? '',
                    $contenido['evaluacion']['tipo_evaluacion'] ?? ''
                );
                break;

            case 'VIII':
                $text = implode("\n", array_map(
                    fn($r) => "• " . ($r['descripcion'] ?? '') . " (" . ($r['tipo'] ?? '') . ")",
                    $contenido['recursos'] ?? []
                ));
                break;

            case 'IX':
                $componentes = implode("\n", array_map(
                    fn($c) => sprintf("• %s: %s%%, Acta: %s, Oblig: %s, Asist: %s%%",
                        $c['componente'] ?? '',
                        $c['porcentaje'] ?? 0,
                        $c['genera_acta'] ? 'Sí' : 'No',
                        $c['aprobacion_obligatoria'] ? 'Sí' : 'No',
                        $c['asistencia_obligatoria'] ?? 0
                    ),
                    $contenido['tabla_componentes'] ?? []
                ));
                $text = sprintf(
                    "Normativa:\n%s\n\nPonderación Optativa: %s%%\n\nComponentes:\n%s",
                    $contenido['descripcion'] ?? '',
                    $contenido['ponderacion_optativa']['porcentaje'] ?? 0,
                    $componentes
                );
                break;

            default:
                $text = '';
        }

        // Return single item array with text content
        $contenidos[] = [
            'texto_contenido' => $text,
            'orden_item' => 1,
        ];

        return $contenidos;    }
}