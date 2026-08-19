<?php

namespace App\Traits;

use App\Syllabus\Secciones\SeccionIContenido;
use App\Syllabus\Secciones\SeccionIVContenido;
use App\Syllabus\Secciones\SeccionIXContenido;
use App\Syllabus\Secciones\SeccionTextoContenido;
use App\Syllabus\Secciones\SeccionVContenido;
use App\Syllabus\Secciones\SeccionVIContenido;
use App\Syllabus\Secciones\SeccionVIIBasico;
use App\Syllabus\Secciones\SeccionVIICompleto;
use App\Syllabus\Secciones\SeccionVIIIContenido;
use App\Syllabus\SyllabusSecciones;
use App\Syllabus\SyllabusTipo;

trait ParsesSyllabus
{
    /**
     * Convierte data_syllabus a array de SeccionPrograma compatible con ProgramaDocument.
     *
     * Soporta dos formatos:
     * - Formato indexado (SyllabusStructure): array de objetos con 'numeral_romano' y 'contenidos'
     *   ya listos para el frontend — se pasan directamente sin transformación.
     * - Formato de wizard (asociativo): keyed por numeral romano con 'contenido' (objeto estructurado),
     *   tipado vía App\Syllabus\SyllabusSecciones y aplanado/formateado a texto legible.
     */
    protected function parseSecciones(array $data): array
    {
        $seccionesData = $data['secciones'] ?? $data;

        // Formato indexado: array secuencial donde cada item ya tiene 'numeral_romano' y 'contenidos'
        if (!empty($seccionesData) && array_is_list($seccionesData) && isset($seccionesData[0]['numeral_romano'])) {
            return array_values($seccionesData);
        }

        $tipo = isset($data['metadata']['tipo_syllabus'])
            ? SyllabusTipo::tryFrom($data['metadata']['tipo_syllabus'])
            : null;
        $secciones = SyllabusSecciones::fromArray($seccionesData, $tipo);

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

        $out = [];
        foreach ($romanos as $idx => $romano) {
            $contenido = $secciones->get($romano);

            $seccion = [
                'nombre_seccion' => $nombres[$romano] ?? "Sección $romano",
                'numeral_romano' => $romano,
                'orden'          => $idx + 1,
                'contenidos'     => $this->extraeContenidos($contenido),
            ];

            if ($romano === 'IX' && $contenido instanceof SeccionIXContenido) {
                $seccion['componentes'] = array_map(fn ($c) => $c->toArray(), $contenido->tablaComponentes);
                $seccion['ponderacion_optativa'] = ['porcentaje' => $contenido->ponderacionOptativaPorcentaje];
            } elseif ($romano === 'IX') {
                $seccion['componentes'] = [];
                $seccion['ponderacion_optativa'] = [];
            }

            $out[] = $seccion;
        }

        return $out;
    }

    /**
     * Extrae contenidos de cada sección para mostrar legible.
     */
    protected function extraeContenidos(?object $contenido): array
    {
        if (!$contenido) {
            return [['texto_contenido' => '', 'orden_item' => 1]];
        }

        $text = match (true) {
            $contenido instanceof SeccionIContenido => $this->formatSeccionI($contenido),
            $contenido instanceof SeccionTextoContenido => $contenido->texto,
            $contenido instanceof SeccionIVContenido => $this->formatCompetencias($contenido),
            $contenido instanceof SeccionVContenido => $this->formatEvaluacionDiagnostica($contenido),
            $contenido instanceof SeccionVIContenido => $this->formatUnidades($contenido),
            $contenido instanceof SeccionVIIBasico => $this->formatActividades($contenido),
            $contenido instanceof SeccionVIICompleto => $this->formatPlanificacion($contenido),
            $contenido instanceof SeccionVIIIContenido => $this->formatRecursos($contenido),
            $contenido instanceof SeccionIXContenido => $this->formatAspectosAdministrativos($contenido),
            default => '',
        };

        return [['texto_contenido' => $text ?? '', 'orden_item' => 1]];
    }

    private function formatSeccionI(SeccionIContenido $c): string
    {
        return sprintf(
            "Asignatura: %s\nCódigo: %s\nCréditos SCT: %s\nHoras Cátedra: %s, Taller: %s, Lab: %s\nCategoría: %s",
            $c->nombreAsignatura,
            $c->codigo,
            $c->creditosSct,
            $c->horas->catedra,
            $c->horas->taller,
            $c->horas->laboratorio,
            $c->categoria
        );
    }

    private function formatCompetencias(SeccionIVContenido $c): string
    {
        $esp = array_filter(array_map(fn ($t) => trim($t->titulo), $c->competenciasEspecificas));
        $gen = array_filter(array_map(fn ($t) => trim($t->titulo), $c->competenciasGenericas));
        $sub = array_filter(array_map(fn ($t) => trim($t->titulo), $c->subcompetencias));

        if (empty($esp) && empty($gen) && empty($sub)) {
            return '';
        }

        $espStr = implode("\n", array_map(fn ($t) => '• ' . $t, $esp));
        $genStr = implode("\n", array_map(fn ($t) => '• ' . $t, $gen));
        $subStr = implode("\n", array_map(fn ($t) => '• ' . $t, $sub));

        return "Específicas:\n$espStr\n\nGenéricas:\n$genStr\n\nSub:\n$subStr";
    }

    private function formatEvaluacionDiagnostica(SeccionVContenido $c): string
    {
        $items = array_filter($c->items, fn ($i) => trim($i->titulo) !== '' || trim((string) $i->descripcion) !== '');
        if (empty($items)) {
            return '';
        }

        return implode("\n", array_map(fn ($i) => '• ' . $i->titulo . ': ' . $i->descripcion, $items));
    }

    private function formatUnidades(SeccionVIContenido $c): string
    {
        $unidades = array_filter($c->unidades, fn ($u) => trim($u->titulo) !== '');
        if (empty($unidades)) {
            return '';
        }

        $unidadesText = array_map(function ($u) {
            $resultados = implode("\n  ", array_map(fn ($r) => '• ' . $r->resultado, $u->resultadosAprendizaje));

            return sprintf(
                "Unidad %d: %s\nContenidos: %s\nResultados:\n  %s",
                $u->numero,
                $u->titulo,
                implode(', ', $u->contenidosItems),
                $resultados
            );
        }, $unidades);

        return implode("\n\n", $unidadesText);
    }

    /** Sección VII en syllabus BASICO: lista de actividades de aprendizaje. */
    private function formatActividades(SeccionVIIBasico $c): string
    {
        $actividades = array_filter($c->actividades, fn ($a) => trim($a->nombre) !== '');
        if (empty($actividades)) {
            return '';
        }

        return implode("\n", array_map(function ($a) {
            $detalle = $a->tipo !== '' ? " ({$a->tipo})" : '';
            $unidad = $a->nombreUnidad !== '' ? " — Unidad: {$a->nombreUnidad}" : '';

            return "• {$a->nombre}{$detalle}{$unidad}";
        }, $actividades));
    }

    /** Sección VII en syllabus COMPLETO: planificación de la enseñanza. */
    private function formatPlanificacion(SeccionVIICompleto $c): string
    {
        $resultadosItems = array_filter($c->resultadosAprendizajeItems, fn ($r) => trim($r->resultado) !== '');
        $metodologia = trim($c->metodologiaTipoEstrategia);
        $evaluacion = trim($c->evaluacionTipoEvaluacion);

        if (empty($resultadosItems) && $metodologia === '' && $evaluacion === '') {
            return '';
        }

        $resultados = implode("\n", array_map(fn ($r) => '• ' . $r->resultado, $resultadosItems));

        return sprintf(
            "Resultados de Aprendizaje:\n%s\n\nMetodología:\n%s\n\nEvaluación:\n%s",
            $resultados,
            $metodologia,
            $evaluacion
        );
    }

    private function formatRecursos(SeccionVIIIContenido $c): string
    {
        $recursos = array_filter($c->recursos, fn ($r) => trim($r->descripcion) !== '');
        if (empty($recursos)) {
            return '';
        }

        return implode("\n", array_map(
            fn ($r) => '• ' . $r->descripcion . ($r->tipo !== '' ? " ({$r->tipo})" : ''),
            $recursos
        ));
    }

    private function formatAspectosAdministrativos(SeccionIXContenido $c): string
    {
        $componentes = array_filter($c->tablaComponentes, fn ($comp) => trim($comp->componente) !== '');
        $descripcion = trim($c->descripcion);

        if ($descripcion === '' && empty($componentes)) {
            return '';
        }

        $componentesText = implode("\n", array_map(
            fn ($comp) => sprintf(
                '• %s: %s%% (Acta: %s, Aprobación obligatoria: %s%s)',
                $comp->componente,
                $comp->porcentaje,
                $comp->generaActa ? 'Sí' : 'No',
                $comp->aprobacionObligatoria ? 'Sí' : 'No',
                $comp->asistenciaObligatoria !== null ? ", Asistencia obligatoria: {$comp->asistenciaObligatoria}%" : ''
            ),
            $componentes
        ));

        return sprintf(
            "%s\n\nPonderación optativa: %s%%\n\nComponentes de evaluación:\n%s",
            $descripcion,
            $c->ponderacionOptativaPorcentaje,
            $componentesText
        );
    }
}
