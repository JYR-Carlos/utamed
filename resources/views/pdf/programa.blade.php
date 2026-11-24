<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programa de asignatura · {{ $titulo }}</title>
    <style>
        /* Una sola tipografía; en los títulos sólo cambia el tamaño y el peso. */
        @page { margin: 20mm 22mm 24mm 22mm; }

        body {
            margin: 0;
            font-family: Helvetica, 'DejaVu Sans', sans-serif;
            font-size: 10.5pt;
            line-height: 1.45;
            color: #22213f;
        }

        .cabecera { border-bottom: 2px solid #303880; padding-bottom: 10pt; margin-bottom: 16pt; }
        .institucion { font-size: 9pt; font-weight: bold; letter-spacing: 1px; }
        .documento { font-size: 9pt; font-weight: bold; letter-spacing: 1px; color: #303880; }
        h1 { margin: 8pt 0 4pt; font-size: 20pt; line-height: 1.2; font-weight: bold; }
        .aviso { margin-top: 8pt; padding: 5pt 8pt; border: 1px solid #b58900; background: #fff6da; }

        .seccion {
            margin: 16pt 0 8pt;
            padding-bottom: 4pt;
            border-bottom: 1px solid #22213f;
            font-size: 13pt;
            line-height: 1.25;
            font-weight: bold;
            page-break-after: avoid;
        }

        h3 { margin: 10pt 0 4pt; font-size: 10.5pt; font-weight: bold; page-break-after: avoid; }
        p { margin: 0 0 6pt; text-align: justify; overflow-wrap: break-word; }

        .campos { width: 100%; border-collapse: collapse; margin: 0 0 6pt; }
        .campos td { padding: 0 0 4pt; vertical-align: top; text-align: justify; overflow-wrap: break-word; }
        .campos .etiqueta { width: 30%; padding-right: 12pt; font-weight: bold; text-align: left; }

        ul { margin: 0 0 6pt; padding: 0 0 0 14pt; }
        li { margin: 0 0 4pt; padding: 0; text-align: justify; overflow-wrap: break-word; }
        li.anidado { margin-left: 14pt; }
        /* Una dirección no se puede partir: justificada dejaría huecos en la línea. */
        li.con-enlace { text-align: left; }

        .componentes { width: 100%; border-collapse: collapse; margin-top: 8pt; page-break-inside: avoid; }
        .componentes th, .componentes td { border: 1px solid #c9ccd6; padding: 5pt 7pt; text-align: left; }
        .componentes th { background: #eef0f6; font-weight: bold; }

        a { color: #303880; }
    </style>
</head>
<body>
    <div class="cabecera">
        <div class="institucion">UNIVERSIDAD DE TARAPACÁ</div>
        <div class="documento">PROGRAMA DE ASIGNATURA</div>
        <h1>{{ $titulo }}</h1>
        @if ($subtitulo !== '')
            <div>{{ $subtitulo }}</div>
        @endif
        @if ($carrera)
            <div>Carrera: {{ $carrera }}</div>
        @endif
        @if ($docente)
            <div>Docente titular: {{ $docente }}</div>
        @endif
        @if ($noAprobado)
            <div class="aviso">Este programa todavía no está aprobado.</div>
        @endif
    </div>

    @forelse ($secciones as $seccion)
        <h2 class="seccion">{{ $seccion['numeral'] }}. {{ $seccion['titulo'] }}</h2>

        @foreach ($seccion['bloques'] as $bloque)
            @if ($bloque['tipo'] === 'subtitulo')
                <h3>{{ $bloque['texto'] }}</h3>
            @elseif ($bloque['tipo'] === 'campos')
                <table class="campos">
                    @foreach ($bloque['filas'] as $fila)
                        <tr>
                            <td class="etiqueta">{{ $fila['etiqueta'] }}</td>
                            <td>{{ $enlazar($fila['valor']) }}</td>
                        </tr>
                    @endforeach
                </table>
            @elseif ($bloque['tipo'] === 'lista')
                <ul>
                    @foreach ($bloque['items'] as $item)
                        <li @class(['anidado' => $item['anidado'], 'con-enlace' => str_contains($item['texto'].$item['cita'], '://')])>{{ $enlazar($item['texto']) }}@if ($item['cita'])<br>{{ $enlazar($item['cita']) }}@endif</li>
                    @endforeach
                </ul>
            @else
                <p>{{ $enlazar($bloque['texto']) }}</p>
            @endif
        @endforeach

        @if ($seccion['componentes'] !== [])
            <table class="componentes">
                <thead>
                    <tr>
                        <th>Componente</th>
                        <th>Ponderación</th>
                        <th>Genera acta</th>
                        <th>Aprobación obligatoria</th>
                        <th>Asistencia obligatoria</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($seccion['componentes'] as $componente)
                        <tr>
                            <td>{{ $componente['componente'] }}</td>
                            <td>{{ $componente['porcentaje'] }} %</td>
                            <td>{{ ! empty($componente['genera_acta']) ? 'Sí' : 'No' }}</td>
                            <td>{{ ! empty($componente['aprobacion_obligatoria']) ? 'Sí' : 'No' }}</td>
                            <td>{{ isset($componente['asistencia_obligatoria']) ? $componente['asistencia_obligatoria'].' %' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @empty
        <p>Este programa todavía no tiene contenido.</p>
    @endforelse
</body>
</html>
