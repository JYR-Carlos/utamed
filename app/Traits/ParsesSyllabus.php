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
        // Títulos oficiales del formato de programa de la universidad.
        $nombres = [
            'I'    => 'Identificación de la asignatura',
            'II'   => 'Presentación, descripción y propósito formativo',
            'III'  => 'Estándares de la profesión',
            'IV'   => 'Áreas, competencias específicas y competencias genéricas',
            'V'    => 'Evaluación diagnóstica para determinar los aprendizajes previos',
            'VI'   => 'Unidades y contenidos de aprendizaje',
            'VII'  => 'Planificación de la enseñanza',
            'VIII' => 'Recursos para el aprendizaje',
            'IX'   => 'Aspectos administrativos y evaluación',
        ];
        $horasAsignatura = $data['metadata']['asignatura']['horas'] ?? [];

        $out = [];
        foreach ($romanos as $idx => $romano) {
            $contenido = $secciones->get($romano);

            $seccion = [
                'nombre_seccion' => $nombres[$romano] ?? "Sección $romano",
                'numeral_romano' => $romano,
                'orden'          => $idx + 1,
                'contenidos'     => $this->extraeContenidos($contenido, $secciones, $horasAsignatura),
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
    protected function extraeContenidos(?object $contenido, ?\App\Syllabus\SyllabusSecciones $secciones = null, array $horasAsignatura = []): array
    {
        if (!$contenido) {
            return [['texto_contenido' => '', 'orden_item' => 1]];
        }

        $seccionVI = $secciones?->get('VI');

        $text = match (true) {
            $contenido instanceof SeccionIContenido => $this->formatSeccionI($contenido, $horasAsignatura),
            $contenido instanceof SeccionTextoContenido => $contenido->texto,
            $contenido instanceof SeccionIVContenido => $this->formatCompetencias($contenido),
            $contenido instanceof SeccionVContenido => $this->formatEvaluacionDiagnostica($contenido),
            $contenido instanceof SeccionVIContenido => $this->formatUnidades($contenido),
            $contenido instanceof SeccionVIIBasico => $this->formatActividades($contenido),
            $contenido instanceof SeccionVIICompleto => $this->formatPlanificacion($contenido),
            $contenido instanceof SeccionVIIIContenido => $this->formatRecursos($contenido, $seccionVI instanceof SeccionVIContenido ? $seccionVI : null),
            $contenido instanceof SeccionIXContenido => $this->formatAspectosAdministrativos($contenido),
            default => '',
        };

        return [['texto_contenido' => $text ?? '', 'orden_item' => 1]];
    }

    /**
     * Las horas presenciales se desglosan en cátedra, taller y laboratorio; las
     * de dedicación (trabajo autónomo) vienen de metadata.asignatura.horas; si
     * el programa no las trae (o trae 0, que es el valor sin llenar) se dice.
     */
    private function formatSeccionI(SeccionIContenido $c, array $horasAsignatura = []): string
    {
        $h = $c->horas;
        $lineas = [
            "Asignatura: {$c->nombreAsignatura}",
            "Código: {$c->codigo}",
            "Créditos SCT: {$c->creditosSct}",
            sprintf(
                'Horas presenciales: %d horas semanales · cátedra %d · taller %d · laboratorio %d',
                $h->catedra + $h->taller + $h->laboratorio,
                $h->catedra,
                $h->taller,
                $h->laboratorio,
            ),
        ];

        $autonomas = (int) ($horasAsignatura['autonomas'] ?? 0);
        $lineas[] = $autonomas > 0
            ? "Horas dedicación: {$autonomas} horas semanales de trabajo autónomo"
            : 'Horas dedicación: sin registrar en la asignatura';

        if (trim($c->categoria) !== '') {
            $lineas[] = "Categoría: {$c->categoria}";
        }

        return implode("\n", $lineas);
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

        // Las genéricas van primero, como en el formato oficial.
        $grupos = array_filter([
            'Competencias genéricas' => $genStr,
            'Competencias específicas' => $espStr,
            'Subcompetencias' => $subStr,
        ], fn ($v) => $v !== '');

        return implode("\n\n", array_map(fn ($t, $l) => "$t:\n$l", array_keys($grupos), $grupos));
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

    /**
     * Formatea el contenido de la Sección VIII para su lectura en el documento del syllabus.
     * Prioriza la estructura relacional de bibliografías académicas.
     */
    private function formatRecursos(SeccionVIIIContenido $c, ?SeccionVIContenido $seccionVI = null): string
    {
        if (!empty($c->bibliografias)) {
            $unidadTitulos = [];
            if ($seccionVI) {
                foreach ($seccionVI->unidades as $u) {
                    $unidadTitulos[$u->numero] = $u->titulo;
                }
            }
            return $this->formatBibliografias($c->bibliografias, $unidadTitulos);
        }

        return $this->formatRecursosLegacy($c->recursos);
    }

    /**
     * Formatea entradas estructuradas de bibliografía académica agrupadas por unidad.
     *
     * @param \App\Syllabus\Secciones\BibliografiaSyllabus[] $bibliografias
     * @param array<int, string> $unidadTitulos
     */
    private function formatBibliografias(array $bibliografias, array $unidadTitulos = []): string
    {
        $valid = array_filter($bibliografias, fn ($b) => trim($b->titulo) !== '');
        if (empty($valid)) {
            return '';
        }

        // Agrupar por id_unidad (número ordinal de unidad)
        $grupos = [];
        foreach ($valid as $b) {
            $key = !empty($b->id_unidad) ? (int) $b->id_unidad : 0;
            $grupos[$key][] = $b;
        }

        ksort($grupos);

        $bloquesTexto = [];
        foreach ($grupos as $idUnidad => $items) {
            if ($idUnidad > 0) {
                $nombre = !empty($unidadTitulos[$idUnidad]) ? ': ' . mb_strtoupper(trim($unidadTitulos[$idUnidad])) : ':';
                $encabezado = "UNIDAD {$idUnidad}{$nombre}";
            } else {
                $encabezado = "BIBLIOGRAFÍA GENERAL:";
            }

            $lineasItems = array_map(function ($b) {
                $autor = !empty($b->autor) ? trim($b->autor) : 'Autor desconocido';
                $editorial = !empty($b->editorial) ? '. ' . trim($b->editorial) : '';
                $cita = !empty($b->cita) ? "\n  «" . trim($b->cita) . '»' : '';
                
                $tipoRecurso = [];
                if ($b->es_bibliografia_uta) {
                    $tipoRecurso[] = 'Biblioteca UTA';
                }
                if (!empty($b->url)) {
                    $tipoRecurso[] = 'Enlace web: ' . $b->url;
                }
                if (!empty($b->uuid_archivo)) {
                    $tipoRecurso[] = 'Archivo adjunto: /api/bibliografias/' . $b->uuid_archivo . '/archivo';
                }

                $etiqueta = !empty($tipoRecurso) ? ' [' . implode(' · ', $tipoRecurso) . ']' : '';

                // Hay títulos que ya traen la referencia («Sandoval Yáñez, C. (2025).
                // Documento de Ruta»): anteponerles autor y año los repetía (OBS-46).
                $titulo = rtrim(trim($b->titulo), '.');
                $referencia = $this->tituloTraeAutor($titulo, $autor) ? $titulo : "{$autor} ({$b->anio}). {$titulo}";

                return "• {$referencia}{$editorial}.{$etiqueta}{$cita}";
            }, $items);

            $bloquesTexto[] = $encabezado . "\n" . implode("\n", $lineasItems);
        }

        return implode("\n\n", $bloquesTexto);
    }

    /** El título empieza con el autor: es la referencia completa, escrita a mano. */
    private function tituloTraeAutor(string $titulo, string $autor): bool
    {
        $comparable = fn (string $t): string => (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($t));
        $prefijo = $comparable($autor);

        return $prefijo !== '' && str_starts_with($comparable($titulo), $prefijo);
    }

    /**
     * @deprecated Formateo de lista plana de recursos genéricos. Se mantiene por retrocompatibilidad con esquemas antiguos.
     * @param \App\Syllabus\Secciones\RecursoSyllabus[] $recursos
     */
    private function formatRecursosLegacy(array $recursos): string
    {
        $filtrados = array_filter($recursos, fn ($r) => trim($r->descripcion) !== '');
        if (empty($filtrados)) {
            return '';
        }

        return implode("\n", array_map(
            fn ($r) => '• ' . $r->descripcion . ($r->tipo !== '' ? " ({$r->tipo})" : ''),
            $filtrados
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
