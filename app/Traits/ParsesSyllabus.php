<?php

namespace App\Traits;

use App\Syllabus\Secciones\BibliografiaSyllabus;
use App\Syllabus\Secciones\RecursoSyllabus;
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

        $romanos = SyllabusSecciones::ROMANOS;
        $nombres = $this->nombresSeccionesOficiales();
        $asignatura = $data['metadata']['asignatura'] ?? [];

        $out = [];
        foreach ($romanos as $idx => $romano) {
            $contenido = $secciones->get($romano);

            $seccion = [
                'nombre_seccion' => $nombres[$romano] ?? "Sección $romano",
                'numeral_romano' => $romano,
                'orden' => $idx + 1,
                'contenidos' => $this->extraeContenidos($contenido, $secciones, $asignatura),
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
     * Convierte data_syllabus a secciones estructuradas en orden canónico I → IX
     * para renderizado en Blade / DomPDF, preservando parseSecciones() intacto.
     */
    protected function parseSeccionesEstructuradas(array $data): array
    {
        $seccionesData = $data['secciones'] ?? $data;
        $romanos = SyllabusSecciones::ROMANOS;
        $ordenCanonico = array_flip($romanos);

        // Formato indexado legado: convertir texto a bloques estructurados y ordenar I → IX.
        if (!empty($seccionesData) && array_is_list($seccionesData) && isset($seccionesData[0]['numeral_romano'])) {
            $indexadas = array_values($seccionesData);
            usort($indexadas, function (array $a, array $b) use ($ordenCanonico) {
                $pa = $ordenCanonico[$a['numeral_romano'] ?? ''] ?? 999;
                $pb = $ordenCanonico[$b['numeral_romano'] ?? ''] ?? 999;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0));
            });

            $out = [];
            foreach ($indexadas as $idx => $sec) {
                $romano = (string) ($sec['numeral_romano'] ?? '');
                $items = $sec['contenidos'] ?? $sec['contenidos_programa'] ?? [];
                $texto = implode("\n\n", array_filter(
                    array_map(fn ($c) => trim((string) ($c['texto_contenido'] ?? '')), is_array($items) ? $items : []),
                    fn (string $t) => $t !== ''
                ));
                $bloques = $this->bloquesDesdeTexto($texto, $romano);
                $componentes = is_array($sec['componentes'] ?? null) ? $sec['componentes'] : [];

                $out[] = [
                    'nombre_seccion' => (string) ($sec['nombre_seccion'] ?? "Sección {$romano}"),
                    'numeral_romano' => $romano,
                    'orden' => $idx + 1,
                    'tipo_seccion' => 'bloques',
                    'bloques' => $bloques,
                    'componentes' => $componentes,
                    'ponderacion_optativa' => is_array($sec['ponderacion_optativa'] ?? null) ? $sec['ponderacion_optativa'] : [],
                    'tiene_contenido' => !empty($bloques) || !empty($componentes),
                ];
            }

            return $out;
        }

        $tipo = isset($data['metadata']['tipo_syllabus'])
            ? SyllabusTipo::tryFrom($data['metadata']['tipo_syllabus'])
            : null;
        $secciones = SyllabusSecciones::fromArray($seccionesData, $tipo);
        $nombres = $this->nombresSeccionesOficiales();
        $asignatura = $data['metadata']['asignatura'] ?? [];

        $out = [];
        foreach ($romanos as $idx => $romano) {
            $contenido = $secciones->get($romano);
            $estructurado = $this->extraeContenidoEstructurado($romano, $contenido, $secciones, $asignatura);

            $seccion = array_merge([
                'nombre_seccion' => $nombres[$romano] ?? "Sección {$romano}",
                'numeral_romano' => $romano,
                'orden' => $idx + 1,
            ], $estructurado);

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
     * Convierte texto plano de una sección legada en bloques tipográficos estructurados.
     *
     * @return list<array<string, mixed>>
     */
    protected function bloquesDesdeTexto(string $texto, ?string $numeral = null): array
    {
        $bruto = str_replace("\r\n", "\n", $texto);
        if (trim($bruto) === '') {
            return [];
        }

        if (in_array($numeral, ['II', 'III'], true)) {
            $parrafos = array_values(array_filter(
                array_map('trim', preg_split('/\n{2,}/', $bruto) ?: []),
                fn (string $p) => $p !== ''
            ));

            return array_map(fn (string $p) => ['tipo' => 'parrafo', 'texto' => $p], $parrafos);
        }

        $bloques = [];
        $lista = null;

        $cerrarLista = function () use (&$bloques, &$lista): void {
            if ($lista !== null && count($lista) > 0) {
                $bloques[] = ['tipo' => 'lista', 'items' => $lista];
            }
            $lista = null;
        };

        foreach (explode("\n", $bruto) as $linea) {
            $contenido = trim($linea);
            if ($contenido === '') {
                $cerrarLista();

                continue;
            }

            if (str_starts_with($contenido, '•')) {
                $item = trim((string) preg_replace('/^•\s*/u', '', $contenido));
                if ($item === '') {
                    continue;
                }
                $anidado = (bool) preg_match('/^\s{2,}/', $linea);
                $lista ??= [];
                $lista[] = ['texto' => $item, 'anidado' => $anidado, 'cita' => null];

                continue;
            }

            if ($lista !== null && count($lista) > 0 && (preg_match('/^\s{2,}/', $linea) || str_starts_with($contenido, '«'))) {
                $ultimoIdx = count($lista) - 1;
                if (str_starts_with($contenido, '«')) {
                    $lista[$ultimoIdx]['cita'] = $contenido;
                } else {
                    $lista[$ultimoIdx]['texto'] .= ' ' . $contenido;
                }

                continue;
            }

            $cerrarLista();

            if (preg_match('/^Unidad\s+\d+\s*:/i', $contenido)) {
                $bloques[] = ['tipo' => 'subtitulo', 'texto' => $contenido];

                continue;
            }

            if (preg_match('/^([^:]{1,40}):[ \t]*$/u', $contenido, $m)) {
                $bloques[] = ['tipo' => 'subtitulo', 'texto' => trim($m[1])];

                continue;
            }

            if (preg_match('/^([^:]{1,40}):[ \t]+(.+)$/u', $contenido, $m)) {
                $bloques[] = ['tipo' => 'campo', 'etiqueta' => trim($m[1]), 'valor' => trim($m[2])];

                continue;
            }

            $bloques[] = ['tipo' => 'parrafo', 'texto' => $contenido];
        }

        $cerrarLista();

        return $bloques;
    }

    /**
     * Títulos oficiales del formato de programa de la universidad.
     *
     * @return array<string, string>
     */
    private function nombresSeccionesOficiales(): array
    {
        return [
            'I' => 'Identificación de la asignatura',
            'II' => 'Presentación, descripción y propósito formativo',
            'III' => 'Estándares de la profesión',
            'IV' => 'Áreas, competencias específicas y competencias genéricas',
            'V' => 'Evaluación diagnóstica para determinar los aprendizajes previos',
            'VI' => 'Unidades y contenidos de aprendizaje',
            'VII' => 'Planificación de la enseñanza',
            'VIII' => 'Recursos para el aprendizaje',
            'IX' => 'Aspectos administrativos y evaluación',
        ];
    }

    /**
     * Extrae la representación estructurada de una sección tipada para Blade/PDF.
     *
     * @return array<string, mixed>
     */
    private function extraeContenidoEstructurado(
        string $romano,
        ?object $contenido,
        SyllabusSecciones $secciones,
        array $asignatura = [],
    ): array {
        if (!$contenido) {
            return ['tipo_seccion' => 'vacia', 'tiene_contenido' => false];
        }

        if ($contenido instanceof SeccionIContenido) {
            $h = $contenido->horas;
            $codigo = ($asignatura['codigo'] ?? '') ?: $contenido->codigo;
            $autonomas = (int) ($asignatura['horas']['autonomas'] ?? 0);
            $campos = [
                ['etiqueta' => 'Asignatura', 'valor' => $contenido->nombreAsignatura],
                ['etiqueta' => 'Código', 'valor' => $codigo],
                ['etiqueta' => 'Créditos SCT', 'valor' => (string) $contenido->creditosSct],
                [
                    'etiqueta' => 'Horas presenciales',
                    'valor' => sprintf(
                        '%d horas semanales (cátedra %d · taller %d · laboratorio %d)',
                        $h->catedra + $h->taller + $h->laboratorio,
                        $h->catedra,
                        $h->taller,
                        $h->laboratorio,
                    ),
                ],
                [
                    'etiqueta' => 'Horas dedicación',
                    'valor' => $autonomas > 0
                        ? "{$autonomas} horas semanales de trabajo autónomo"
                        : 'Sin registrar en la asignatura',
                ],
            ];
            if (trim($contenido->categoria) !== '') {
                $campos[] = ['etiqueta' => 'Categoría', 'valor' => $contenido->categoria];
            }

            return [
                'tipo_seccion' => 'identificacion',
                'campos' => $campos,
                'tiene_contenido' => trim($contenido->nombreAsignatura) !== '' || trim((string) $codigo) !== '',
            ];
        }

        if ($contenido instanceof SeccionTextoContenido) {
            $parrafos = array_values(array_filter(
                array_map('trim', preg_split('/\n{2,}/', str_replace("\r\n", "\n", $contenido->texto)) ?: []),
                fn (string $p) => $p !== ''
            ));

            return [
                'tipo_seccion' => 'texto',
                'parrafos' => $parrafos,
                'tiene_contenido' => !empty($parrafos),
            ];
        }

        if ($contenido instanceof SeccionIVContenido) {
            $mapTitulos = fn (array $items) => array_values(array_filter(
                array_map(fn ($t) => trim($t->titulo), $items),
                fn (string $t) => $t !== ''
            ));
            $grupos = array_filter([
                'Competencias genéricas' => $mapTitulos($contenido->competenciasGenericas),
                'Competencias específicas' => $mapTitulos($contenido->competenciasEspecificas),
                'Subcompetencias' => $mapTitulos($contenido->subcompetencias),
            ], fn (array $items) => !empty($items));

            return [
                'tipo_seccion' => 'competencias',
                'grupos' => $grupos,
                'tiene_contenido' => !empty($grupos),
            ];
        }

        if ($contenido instanceof SeccionVContenido) {
            $items = array_values(array_map(
                fn ($i) => ['titulo' => trim($i->titulo), 'descripcion' => trim((string) $i->descripcion)],
                array_filter($contenido->items, fn ($i) => trim($i->titulo) !== '' || trim((string) $i->descripcion) !== '')
            ));

            return [
                'tipo_seccion' => 'diagnostica',
                'items' => $items,
                'tiene_contenido' => !empty($items),
            ];
        }

        if ($contenido instanceof SeccionVIContenido) {
            $unidades = array_values(array_map(
                fn ($u) => [
                    'numero' => $u->numero,
                    'titulo' => trim($u->titulo),
                    'contenidos' => array_values(array_filter(array_map('trim', $u->contenidosItems), fn ($x) => $x !== '')),
                    'resultados' => array_values(array_filter(
                        array_map(fn ($r) => trim($r->resultado), $u->resultadosAprendizaje),
                        fn ($x) => $x !== ''
                    )),
                ],
                array_filter($contenido->unidades, fn ($u) => trim($u->titulo) !== '')
            ));

            return [
                'tipo_seccion' => 'unidades',
                'unidades' => $unidades,
                'tiene_contenido' => !empty($unidades),
            ];
        }

        if ($contenido instanceof SeccionVIIBasico) {
            $actividades = array_values(array_map(
                fn ($a) => [
                    'nombre' => trim($a->nombre),
                    'tipo' => trim($a->tipo),
                    'nombre_unidad' => trim($a->nombreUnidad),
                ],
                array_filter($contenido->actividades, fn ($a) => trim($a->nombre) !== '')
            ));

            return [
                'tipo_seccion' => 'planificacion_basico',
                'actividades' => $actividades,
                'tiene_contenido' => !empty($actividades),
            ];
        }

        if ($contenido instanceof SeccionVIICompleto) {
            $resultados = array_values(array_filter(
                array_map(fn ($r) => trim($r->resultado), $contenido->resultadosAprendizajeItems),
                fn ($r) => $r !== ''
            ));
            $metodologia = trim($contenido->metodologiaTipoEstrategia);
            $evaluacion = trim($contenido->evaluacionTipoEvaluacion);

            return [
                'tipo_seccion' => 'planificacion_completo',
                'resultados' => $resultados,
                'metodologia' => $metodologia,
                'evaluacion' => $evaluacion,
                'tiene_contenido' => !empty($resultados) || $metodologia !== '' || $evaluacion !== '',
            ];
        }

        if ($contenido instanceof SeccionVIIIContenido) {
            $seccionVI = $secciones->get('VI');
            $unidadTitulos = [];
            if ($seccionVI instanceof SeccionVIContenido) {
                foreach ($seccionVI->unidades as $u) {
                    $unidadTitulos[$u->numero] = $u->titulo;
                }
            }

            if (!empty($contenido->bibliografias)) {
                $valid = array_filter($contenido->bibliografias, fn ($b) => trim($b->titulo) !== '');
                $gruposRaw = [];
                foreach ($valid as $b) {
                    $key = !empty($b->id_unidad) ? (int) $b->id_unidad : 0;
                    $gruposRaw[$key][] = $b;
                }
                ksort($gruposRaw);

                $grupos = [];
                foreach ($gruposRaw as $idUnidad => $items) {
                    $encabezado = $idUnidad > 0
                        ? 'Unidad ' . $idUnidad . (!empty($unidadTitulos[$idUnidad]) ? ': ' . trim($unidadTitulos[$idUnidad]) : '')
                        : 'Bibliografía general';

                    $grupos[] = [
                        'encabezado' => $encabezado,
                        'items' => array_map(function ($b) {
                            $autor = !empty($b->autor) ? trim($b->autor) : 'Autor desconocido';
                            $titulo = rtrim(trim($b->titulo), '.');
                            $referencia = $this->tituloTraeAutor($titulo, $autor) ? $titulo : "{$autor} ({$b->anio}). {$titulo}";

                            return [
                                'referencia' => $referencia,
                                'editorial' => !empty($b->editorial) ? trim($b->editorial) : null,
                                'cita' => !empty($b->cita) ? trim($b->cita) : null,
                                'es_bibliografia_uta' => (bool) $b->es_bibliografia_uta,
                                'url' => !empty($b->url) ? trim($b->url) : null,
                                'archivo_url' => !empty($b->uuid_archivo) ? url('/api/bibliografias/' . $b->uuid_archivo . '/archivo') : null,
                            ];
                        }, $items),
                    ];
                }

                return [
                    'tipo_seccion' => 'bibliografias',
                    'grupos' => $grupos,
                    'tiene_contenido' => !empty($grupos),
                ];
            }

            $recursos = array_values(array_map(
                fn ($r) => [
                    'descripcion' => trim($r->descripcion),
                    'tipo' => trim($r->tipo),
                    'ubicacion' => $r->ubicacion ? trim($r->ubicacion) : null,
                ],
                array_filter($contenido->recursos, fn ($r) => trim($r->descripcion) !== '')
            ));

            return [
                'tipo_seccion' => 'recursos_legacy',
                'recursos' => $recursos,
                'tiene_contenido' => !empty($recursos),
            ];
        }

        if ($contenido instanceof SeccionIXContenido) {
            $descripcion = trim($contenido->descripcion);
            $componentes = array_filter($contenido->tablaComponentes, fn ($comp) => trim($comp->componente) !== '');

            return [
                'tipo_seccion' => 'evaluacion',
                'descripcion' => $descripcion,
                'tiene_contenido' => $descripcion !== '' || !empty($componentes),
            ];
        }

        return ['tipo_seccion' => 'vacia', 'tiene_contenido' => false];
    }

    /**
     * Extrae contenidos de cada sección para mostrar legible.
     */
    protected function extraeContenidos(?object $contenido, ?SyllabusSecciones $secciones = null, array $asignatura = []): array
    {
        if (!$contenido) {
            return [['texto_contenido' => '', 'orden_item' => 1]];
        }

        $seccionVI = $secciones?->get('VI');

        $text = match (true) {
            $contenido instanceof SeccionIContenido => $this->formatSeccionI($contenido, $asignatura),
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
     * El código es el de la asignatura (metadata.asignatura, que arma el
     * servidor). El que guarda la sección I puede ser el código interno del
     * curso en los syllabus antiguos, y los aprobados ya no se editan.
     *
     * Las horas presenciales (semanales) van con su desglose abajo. Las de
     * dedicación son el trabajo autónomo: metadata.asignatura.horas las trae por
     * semana; los programas cargados desde el EVEA las guardan en la sección I
     * como total del semestre. Un 0 es el valor sin llenar: si no hay ninguna,
     * se dice.
     */
    private function formatSeccionI(SeccionIContenido $c, array $asignatura = []): string
    {
        $h = $c->horas;
        $codigo = ($asignatura['codigo'] ?? '') ?: $c->codigo;
        $lineas = [
            "Asignatura: {$c->nombreAsignatura}",
            "Código: {$codigo}",
            "Créditos SCT: {$c->creditosSct}",
            sprintf('Horas presenciales: %d horas semanales', $h->catedra + $h->taller + $h->laboratorio),
            "Cátedra: {$h->catedra}",
            "Taller: {$h->taller}",
            "Laboratorio: {$h->laboratorio}",
        ];

        $semanales = (int) ($asignatura['horas']['autonomas'] ?? 0);
        $lineas[] = match (true) {
            $semanales > 0 => "Horas dedicación: {$semanales} horas semanales de trabajo autónomo",
            (int) $h->autonomas > 0 => "Horas dedicación: {$h->autonomas} horas semestrales de trabajo autónomo",
            default => 'Horas dedicación: sin registrar en la asignatura',
        };

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
     * @param  BibliografiaSyllabus[]  $bibliografias
     * @param  array<int, string>  $unidadTitulos
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
                $encabezado = 'BIBLIOGRAFÍA GENERAL:';
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
     *
     * @param  RecursoSyllabus[]  $recursos
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
