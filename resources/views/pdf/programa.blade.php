<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programa de Asignatura - {{ $tituloAsignatura }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 16mm 16mm 18mm 16mm;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        a {
            color: #1d4ed8;
            text-decoration: underline;
            word-wrap: break-word;
        }

        /* Cabecera institucional (solo primera hoja) */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
            padding-bottom: 10px;
        }

        .header-logo {
            width: 52px;
            padding-right: 12px;
        }

        .header-logo img {
            width: 44px;
            height: 44px;
            display: block;
        }

        .inst-kicker {
            font-size: 5pt;
            font-weight: 500;
            letter-spacing: 0.8px;
            color: #475569;
            text-transform: uppercase;
            margin: 0 0 1px 0;
        }

        .doc-title {
            font-size: 15pt;
            font-weight: bold;
            color: #0f172a;
            margin: 2px 0 4px 0;
            line-height: 1.2;
        }

        .doc-subtitle {
            font-size: 9pt;
            color: #000;
            margin: 0;
        }

        .status-cell {
            width: 140px;
            text-align: right;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 9px;
            font-size: 7.5pt;
            font-weight: bold;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .draft-banner {
            padding: 7px 10px;
            margin-bottom: 12px;
            font-size: 8.5pt;
            border-radius: 4px;
        }

        /* Ficha de metadatos */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
        }

        .meta-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 8.5pt;
            vertical-align: top;
        }

        .meta-label {
            width: 18%;
            font-weight: bold;
            color: #334155;
            background-color: #f1f5f9;
        }

        /* Secciones I - IX */
        .section {
            margin-bottom: 14px;
        }

        .section-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 3px;
            margin: 0 0 8px 0;
            page-break-after: avoid;
        }

        .section-num {
            display: inline-block;
            min-width: 24px;
        }

        .kv-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .kv-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9pt;
            vertical-align: top;
        }

        .kv-key {
            width: 28%;
            font-weight: bold;
            color: #334155;
        }

        p {
            margin: 0 0 6px 0;
            text-align: justify;
        }

        .subtitulo {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0f172a;
            margin: 8px 0 4px 0;
            page-break-after: avoid;
        }

        ul {
            margin: 3px 0 8px 0;
            padding-left: 18px;
        }

        li {
            margin-bottom: 3px;
            text-align: justify;
        }

        li.anidado {
            margin-left: 14px;
        }

        .unit-box {
            border: 1px solid #cbd5e1;
            margin-bottom: 8px;
            padding: 7px 9px;
            background-color: #ffffff;
        }

        .unit-header {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 5px;
            page-break-after: avoid;
        }

        .uta-tag {
            display: inline-block;
            font-size: 7.5pt;
            font-weight: bold;
            color: #1d4ed8;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 1px 5px;
            border-radius: 3px;
        }

        .cita {
            display: block;
            font-style: italic;
            font-size: 8.5pt;
            color: #475569;
            margin-top: 2px;
        }

        /* Tabla de evaluación (Sección IX) */
        .eval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }

        .eval-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
            padding: 6px 7px;
            border: 1px solid #cbd5e1;
        }

        .eval-table td {
            padding: 5px 7px;
            font-size: 8.5pt;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .empty-doc {
            padding: 16px;
            border: 1px dashed #cbd5e1;
            color: #64748b;
            text-align: center;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    @php
        $renderizarTextoConEnlaces = function (string $texto): string {
            $pattern = '/(https?:\/\/[^\s,\])>]+|\/api\/bibliografias\/[a-zA-Z0-9-]+\/archivo)/';
            $partes = preg_split($pattern, $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($partes === false) {
                return e($texto);
            }
            $html = '';
            foreach ($partes as $parte) {
                if (preg_match('/^\/api\/bibliografias\/[a-zA-Z0-9-]+\/archivo$/', $parte)) {
                    $href = url($parte);
                    $html .= '<a href="' . e($href) . '">' . e($href) . '</a>';
                } elseif (preg_match('/^https?:\/\//', $parte)) {
                    $html .= '<a href="' . e($parte) . '">' . e($parte) . '</a>';
                } else {
                    $html .= e($parte);
                }
            }
            return $html;
        };
    @endphp

    <!-- Cabecera institucional (primera hoja) -->
    <table class="header-table">
        <tr>
            @if($logoPath)
                <td class="header-logo">
                    <img src="{{ $logoPath }}" alt="UTAMED">
                </td>
            @endif
            <td>
                <p class="inst-kicker">Universidad de Tarapacá</p>
                <p class="inst-kicker"> Plataforma UTAMED</p>
                <p class="inst-kicker"> Programa de Asignatura</p>
                <h1 class="doc-title" style="margin-top: 10px">{{ $tituloAsignatura }}</h1>
                <p class="doc-subtitle">
                    {{ collect([
                        $codigoAsignatura,
                        !empty($cabecera['letra_grupo']) ? 'Grupo ' . $cabecera['letra_grupo'] : null,
                        !empty($cabecera['semestre_real']) && !empty($cabecera['agno_real'])
                            ? $cabecera['semestre_real'] . '.º semestre ' . $cabecera['agno_real']
                            : null,
                        $carrera?->nombre,
                    ])->filter()->implode(' · ') }}
                </p>
            </td>
            @if ($estadoInfo['label'] !== 'APROBADO')
                <td class="status-cell">
                <span>
                    {{ $estadoInfo['label'] }}
                </span>
            </td>
            @endif
            
        </tr>
    </table>

    <!-- Secciones en orden canónico I -> IX -->
    @forelse($secciones as $seccion)
        <div class="section">
            <h2 class="section-title">
                <span class="section-num">{{ $seccion['numeral_romano'] }}.</span>
                {{ $seccion['nombre_seccion'] }}
            </h2>

            @switch($seccion['tipo_seccion'] ?? 'vacia')
                @case('identificacion')
                    <table class="kv-table">
                        @foreach($seccion['campos'] ?? [] as $campo)
                            <tr>
                                <td class="kv-key">{{ $campo['etiqueta'] }}</td>
                                <td>{{ $campo['valor'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                    @break

                @case('texto')
                    @foreach($seccion['parrafos'] ?? [] as $parrafo)
                        <p>{!! nl2br(e($parrafo)) !!}</p>
                    @endforeach
                    @break

                @case('competencias')
                    @foreach($seccion['grupos'] ?? [] as $grupo => $items)
                        <div class="subtitulo">{{ $grupo }}</div>
                        <ul>
                            @foreach($items as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endforeach
                    @break

                @case('diagnostica')
                    <ul>
                        @foreach($seccion['items'] ?? [] as $item)
                            <li>
                                @if($item['titulo'] !== '')
                                    <strong>{{ $item['titulo'] }}:</strong>
                                @endif
                                {{ $item['descripcion'] }}
                            </li>
                        @endforeach
                    </ul>
                    @break

                @case('unidades')
                    @foreach($seccion['unidades'] ?? [] as $unidad)
                        <div class="unit-box">
                            <div class="unit-header">Unidad {{ $unidad['numero'] }}: {{ $unidad['titulo'] }}</div>
                            @if(!empty($unidad['contenidos']))
                                <p><strong>Contenidos:</strong> {{ implode(', ', $unidad['contenidos']) }}</p>
                            @endif
                            @if(!empty($unidad['resultados']))
                                <div class="subtitulo" style="margin-top: 4px;">Resultados de aprendizaje</div>
                                <ul>
                                    @foreach($unidad['resultados'] as $res)
                                        <li>{{ $res }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                    @break

                @case('planificacion_basico')
                    <ul>
                        @foreach($seccion['actividades'] ?? [] as $act)
                            <li>
                                <strong>{{ $act['nombre'] }}</strong>{{ $act['tipo'] !== '' ? ' (' . $act['tipo'] . ')' : '' }}{{ $act['nombre_unidad'] !== '' ? ' — Unidad: ' . $act['nombre_unidad'] : '' }}
                            </li>
                        @endforeach
                    </ul>
                    @break

                @case('planificacion_completo')
                    @if(!empty($seccion['resultados']))
                        <div class="subtitulo">Resultados de Aprendizaje</div>
                        <ul>
                            @foreach($seccion['resultados'] as $res)
                                <li>{{ $res }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if(!empty($seccion['metodologia']))
                        <div class="subtitulo">Metodología</div>
                        <p>{!! nl2br(e($seccion['metodologia'])) !!}</p>
                    @endif
                    @if(!empty($seccion['evaluacion']))
                        <div class="subtitulo">Evaluación</div>
                        <p>{!! nl2br(e($seccion['evaluacion'])) !!}</p>
                    @endif
                    @break

                @case('bibliografias')
                    @foreach($seccion['grupos'] ?? [] as $grupo)
                        <div class="subtitulo">{{ $grupo['encabezado'] }}</div>
                        <ul>
                            @foreach($grupo['items'] as $bib)
                                <li>
                                    {{ $bib['referencia'] }}{{ !empty($bib['editorial']) ? '. ' . $bib['editorial'] : '' }}.
                                    @if(!empty($bib['es_bibliografia_uta']))
                                        <span style="font:bold">Biblioteca UTA</span>
                                    @endif
                                    @if(!empty($bib['url']))
                                        <br>Enlace: <a href="{{ $bib['url'] }}">{{ $bib['url'] }}</a>
                                    @endif
                                    @if(!empty($bib['archivo_url']))
                                        <br>Archivo adjunto: <a href="{{ $bib['archivo_url'] }}">{{ $bib['archivo_url'] }}</a>
                                    @endif
                                    @if(!empty($bib['cita']))
                                        <span class="cita">«{{ $bib['cita'] }}»</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                    @break

                @case('recursos_legacy')
                    <ul>
                        @foreach($seccion['recursos'] ?? [] as $rec)
                            <li>
                                {{ $rec['descripcion'] }}{{ !empty($rec['tipo']) ? ' (' . $rec['tipo'] . ')' : '' }}{{ !empty($rec['ubicacion']) ? ' — ' . $rec['ubicacion'] : '' }}
                            </li>
                        @endforeach
                    </ul>
                    @break

                @case('evaluacion')
                    @if(!empty($seccion['descripcion']))
                        <p>{!! nl2br(e($seccion['descripcion'])) !!}</p>
                    @endif
                    @if(!empty($seccion['componentes']))
                        <table class="eval-table">
                            <thead>
                                <tr>
                                    <th>Componente</th>
                                    <th class="text-right">%</th>
                                    <th>Genera Acta</th>
                                    <th>Aprobación oblig.</th>
                                    <th class="text-right">Asistencia oblig.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($seccion['componentes'] as $comp)
                                    <tr>
                                        <td><strong>{{ $comp['componente'] ?? '' }}</strong></td>
                                        <td class="text-right">{{ $comp['porcentaje'] ?? 0 }}%</td>
                                        <td>{{ !empty($comp['genera_acta']) ? 'Sí' : 'No' }}</td>
                                        <td>{{ !empty($comp['aprobacion_obligatoria']) ? 'Sí' : 'No' }}</td>
                                        <td class="text-right">
                                            {{ isset($comp['asistencia_obligatoria']) && $comp['asistencia_obligatoria'] !== null ? $comp['asistencia_obligatoria'] . '%' : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if(!empty($seccion['ponderacion_optativa']['porcentaje']))
                        <p style="font-size: 8.5pt; color: #334155;">
                            <strong>Ponderación de la prueba optativa:</strong> {{ $seccion['ponderacion_optativa']['porcentaje'] }}%
                        </p>
                    @endif
                    @break

                @case('bloques')
                    @foreach($seccion['bloques'] ?? [] as $bloque)
                        @if($bloque['tipo'] === 'subtitulo')
                            <div class="subtitulo">{{ $bloque['texto'] }}</div>
                        @elseif($bloque['tipo'] === 'campo')
                            <p><strong>{{ $bloque['etiqueta'] }}:</strong> {!! $renderizarTextoConEnlaces($bloque['valor']) !!}</p>
                        @elseif($bloque['tipo'] === 'lista')
                            <ul>
                                @foreach($bloque['items'] as $item)
                                    <li class="{{ !empty($item['anidado']) ? 'anidado' : '' }}">
                                        {!! $renderizarTextoConEnlaces($item['texto']) !!}
                                        @if(!empty($item['cita']))
                                            <span class="cita">{!! $renderizarTextoConEnlaces($item['cita']) !!}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p>{!! $renderizarTextoConEnlaces($bloque['texto']) !!}</p>
                        @endif
                    @endforeach

                    @if(!empty($seccion['componentes']))
                        <table class="eval-table">
                            <thead>
                                <tr>
                                    <th>Componente</th>
                                    <th class="text-right">%</th>
                                    <th>Genera Acta</th>
                                    <th>Aprobación oblig.</th>
                                    <th class="text-right">Asistencia oblig.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($seccion['componentes'] as $comp)
                                    <tr>
                                        <td><strong>{{ $comp['componente'] ?? '' }}</strong></td>
                                        <td class="text-right">{{ $comp['porcentaje'] ?? 0 }}%</td>
                                        <td>{{ !empty($comp['genera_acta']) ? 'Sí' : 'No' }}</td>
                                        <td>{{ !empty($comp['aprobacion_obligatoria']) ? 'Sí' : 'No' }}</td>
                                        <td class="text-right">
                                            {{ isset($comp['asistencia_obligatoria']) && $comp['asistencia_obligatoria'] !== null ? $comp['asistencia_obligatoria'] . '%' : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @break
            @endswitch
        </div>
    @empty
        <div class="empty-doc">Este programa todavía no registra contenido en sus secciones.</div>
    @endforelse
</body>
</html>
