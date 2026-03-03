<?php

namespace App\Traits;

trait ParsesSyllabus
{
    /**
     * Convierte data_syllabus de estructura IX-secciones a array de SeccionPrograma
     * compatible con el componente ProgramaDocument del frontend.
     */
    protected function parseSecciones(array $data): array
    {
        $seccionesData = $data['secciones'] ?? $data;

        $romanos = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
        $nombres = [
            'I'    => 'Identificación',
            'II'   => 'Presentación',
            'III'  => 'Estándares',
            'IV'   => 'Competencias',
            'V'    => 'Evaluación Diagnóstica',
            'VI'   => 'Unidades',
            'VII'  => 'Planificación',
            'VIII' => 'Recursos',
            'IX'   => 'Aspectos Administrativos',
        ];

        $secciones = [];
        foreach ($romanos as $idx => $romano) {
            $seccionData        = $seccionesData[$romano] ?? [];
            $contenido          = $seccionData['contenido'] ?? [];
            $contenidosPrograma = $this->extraeContenidos($contenido, $romano);

            $seccion = [
                'nombre_seccion' => $nombres[$romano] ?? "Sección $romano",
                'numeral_romano' => $romano,
                'orden'          => $idx + 1,
                'contenidos'     => $contenidosPrograma,   // frontend expects "contenidos"
            ];

            if ($romano === 'IX') {
                $seccion['componentes']          = $contenido['tabla_componentes'] ?? [];
                $seccion['ponderacion_optativa'] = $contenido['ponderacion_optativa'] ?? [];
            }

            $secciones[] = $seccion;
        }

        return $secciones;
    }

    protected function extraeContenidos(array $contenido, string $seccionId): array
    {
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
                $esp = array_filter(array_map(fn($c) => trim($c['titulo'] ?? ''), $contenido['competencias_especificas'] ?? []));
                $gen = array_filter(array_map(fn($c) => trim($c['titulo'] ?? ''), $contenido['competencias_genericas'] ?? []));
                $sub = array_filter(array_map(fn($c) => trim($c['titulo'] ?? ''), $contenido['subcompetencias'] ?? []));
                if (empty($esp) && empty($gen) && empty($sub)) { $text = ''; break; }
                $espStr = implode("\n", array_map(fn($t) => '• ' . $t, $esp));
                $genStr = implode("\n", array_map(fn($t) => '• ' . $t, $gen));
                $subStr = implode("\n", array_map(fn($t) => '• ' . $t, $sub));
                $text = "Específicas:\n$espStr\n\nGenéricas:\n$genStr\n\nSub:\n$subStr";
                break;
            case 'V':
                $items = array_filter($contenido['items'] ?? [], fn($i) => !empty(trim($i['titulo'] ?? '')) || !empty(trim($i['descripcion'] ?? '')));
                if (empty($items)) { $text = ''; break; }
                $text = implode("\n", array_map(
                    fn($i) => '• ' . ($i['titulo'] ?? '') . ': ' . ($i['descripcion'] ?? ''),
                    $items
                ));
                break;
            case 'VI':
                $unidades = array_filter($contenido['unidades'] ?? [], fn($u) => !empty(trim($u['titulo'] ?? '')));
                if (empty($unidades)) { $text = ''; break; }
                $unidadesText = array_map(function ($u) {
                    $resultados = implode("\n  ", array_map(
                        fn($r) => '• ' . ($r['resultado'] ?? ''),
                        $u['resultados_aprendizaje'] ?? []
                    ));
                    return sprintf(
                        "Unidad %d: %s\nContenidos: %s\nResultados:\n  %s",
                        $u['numero'] ?? 0,
                        $u['titulo'] ?? '',
                        implode(', ', array_map(fn($c) => $c['item'] ?? '', $u['contenidos_items'] ?? [])),
                        $resultados
                    );
                }, $unidades);
                $text = implode("\n\n", $unidadesText);
                break;
            case 'VII':
                $resultadosItems = $contenido['resultados_aprendizaje']['items'] ?? [];
                $metodologia     = trim($contenido['metodologia']['tipo_estrategia'] ?? '');
                $evaluacion      = trim($contenido['evaluacion']['tipo_evaluacion'] ?? '');
                $resultadosItems = array_filter($resultadosItems, fn($r) => !empty(trim($r['resultado'] ?? '')));
                if (empty($resultadosItems) && $metodologia === '' && $evaluacion === '') { $text = ''; break; }
                $resultados = implode("\n", array_map(fn($r) => '• ' . ($r['resultado'] ?? ''), $resultadosItems));
                $text = sprintf(
                    "Resultados de Aprendizaje:\n%s\n\nMetodología:\n%s\n\nEvaluación:\n%s",
                    $resultados,
                    $metodologia,
                    $evaluacion
                );
                break;
            case 'VIII':
                $recursos = array_filter($contenido['recursos'] ?? [], fn($r) => !empty(trim($r['recurso'] ?? '')));
                if (empty($recursos)) { $text = ''; break; }
                $text = implode("\n", array_map(fn($r) => '• ' . ($r['recurso'] ?? ''), $recursos));
                break;
            case 'IX':
                $asistencia  = trim($contenido['porcentaje_asistencia_minima'] ?? '');
                $reprobacion = trim($contenido['condicion_reprobacion'] ?? '');
                $nota        = trim($contenido['nota_minima_aprobacion'] ?? '');
                if ($asistencia === '' && $reprobacion === '' && $nota === '') { $text = ''; break; }
                $text = sprintf(
                    "Asistencia mín.: %s%%\nReprobación: %s\nNota mínima aprobación: %s",
                    $asistencia,
                    $reprobacion,
                    $nota
                );
                break;
            default:
                $text = json_encode($contenido, JSON_UNESCAPED_UNICODE);
        }

        return [['texto_contenido' => $text ?? '', 'orden_item' => 1]];
    }
}
