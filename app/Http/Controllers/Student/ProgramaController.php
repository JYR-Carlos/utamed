<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProgramaController extends Controller
{
    /**
     * Ver programa aprobado de un curso
     */
    public function show(Curso $curso)
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que estudiante está inscrito en este curso
        if (!$user->estudiante) {
            return redirect()->route('estudiante.dashboard')
                ->with('error', 'No tienes un perfil de estudiante');
        }

        $inscripcion = InscripcionCurso::where('id_estudiante', $user->estudiante->id_estudiante)
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->first();

        if (!$inscripcion) {
            return redirect()->route('estudiante.cursos.index')
                ->with('error', 'No estás inscrito en este curso');
        }

        // Obtener programa aprobado con relaciones eager-loaded
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('estado', 'APROBADO')
            ->where('es_actual', true)
            ->with('autor')
            ->first();

        // Si no hay programa, mostrar página con aviso en lugar de 404
        if (!$programa) {
            // Cargar relaciones para mostrar la información del curso
            $curso->load([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera'
            ]);

            $cursoData = [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura' => $curso->asignacionPlan?->asignatura,
                'carrera' => $curso->asignacionPlan?->plan?->carrera,
            ];

            return Inertia::render('student/Courses/Programa', [
                'programa' => null,
                'curso' => $cursoData,
            ]);
        }

        // Cargar relaciones del curso
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera'
        ]);

        // Procesar data_syllabus correctamente
        $dataSyllabus = is_array($programa->data_syllabus) 
            ? $programa->data_syllabus 
            : json_decode($programa->data_syllabus, true);

        // Parsear secciones igual que el admin
        $secciones = $this->parseSecciones($dataSyllabus);

        // Renombrar contenidos_programa a contenidos para la vista
        foreach ($secciones as &$seccion) {
            $seccion['contenidos'] = $seccion['contenidos_programa'];
            unset($seccion['contenidos_programa']);
        }
        unset($seccion);

        // Formatear datos
        $programaData = [
            'id_programa' => $programa->id_programa,
            'id_curso' => $programa->id_curso,
            'version' => $programa->version_programa,
            'estado' => $programa->estado,
            'secciones' => $secciones,
            'creado_por' => $programa->autor?->nombre,
            'fecha_creacion' => $programa->fecha_creacion,
        ];

        $cursoData = [
            'id_curso' => $curso->id_curso,
            'nombre' => $curso->nombre,
            'cod_curso' => $curso->cod_curso,
            'asignatura' => $curso->asignacionPlan?->asignatura,
            'carrera' => $curso->asignacionPlan?->plan?->carrera,
        ];

        return Inertia::render('student/Courses/Programa', [
            'programa' => $programaData,
            'curso' => $cursoData,
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

        return $contenidos;
    }
}
