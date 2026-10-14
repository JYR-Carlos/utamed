<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Incidencia - {{ $data['id'] ?? 'UTAMED' }}</title>
    <style>
        @page {
            margin: 25mm 20mm 20mm 20mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* Tabla de Encabezado */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 20px;
        }

        .header-table td {
            vertical-align: middle;
            padding-bottom: 12px;
        }

        .header-logo {
            width: 54px;
            padding-right: 12px;
        }

        .header-logo img {
            width: 50px;
            height: 50px;
            display: block;
        }

        .header-title h1 {
            margin: 0;
            font-size: 17px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.2px;
        }

        .header-title p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-meta {
            text-align: right;
            font-size: 9.5px;
            color: #475569;
            line-height: 1.4;
        }

        .header-meta strong {
            color: #0f172a;
        }

        /* Ficha de Metadatos del Reporte */
        .meta-card {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
        }

        .meta-card td {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            font-size: 10.5px;
        }

        .meta-card .meta-label {
            width: 22%;
            font-weight: bold;
            color: #334155;
            background-color: #f1f5f9;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.4px;
        }

        .meta-card .meta-value {
            color: #0f172a;
        }

        .mono {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
        }

        /* Secciones de Contenido */
        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section-header {
            font-size: 10px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-body {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            font-size: 11px;
            color: #0f172a;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
        }

        .section-empty {
            color: #94a3b8;
            font-style: italic;
        }

        /* Contenedor de Evidencia */
        .evidence-wrapper {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .evidence-container {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 12px;
            text-align: center;
        }

        .evidence-container img {
            max-width: 95%;
            max-height: 440px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
        }

        /* Pie de página */
        .footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- Encabezado Institucional -->
    <table class="header-table">
        <tr>
            <td class="header-logo">
                <img src="{{ public_path('img/utamed.webp') }}" alt="UTAMED">
            </td>
            <td class="header-title">
                <h1>Reporte de Incidencia</h1>
                <p>Plataforma UTAMED &bull; Sistema de Gestión de Errores</p>
            </td>
            <td class="header-meta">
                <div><strong>Fecha de emisión:</strong> {{ isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at'])->format('d/m/Y H:i') : date('d/m/Y H:i') }}</div>
                <div><strong>ID:</strong> <span class="mono">{{ $data['id'] ?? 'S/I' }}</span></div>
            </td>
        </tr>
    </table>

    <!-- Ficha de Datos Generales -->
    <table class="meta-card">
        <tr>
            <td class="meta-label">Título del Problema</td>
            <td class="meta-value" colspan="3"><strong>{{ $data['title'] ?? 'Sin título' }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">Usuario Reportante</td>
            <td class="meta-value" style="width: 38%;">{{ $data['rut_usuario'] ?? 'No especificado' }}</td>
            <td class="meta-label" style="width: 18%;">Fecha Reporte</td>
            <td class="meta-value">{{ isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at'])->format('d/m/Y H:i') : 'No especificada' }}</td>
        </tr>
    </table>

    <!-- Detalle de la Incidencia -->
    <div class="section">
        <div class="section-header">Descripción del Problema</div>
        <div class="section-body">{{ $data['description'] ?? 'Sin descripción detallada.' }}</div>
    </div>

    @if(!empty($data['expected']))
    <div class="section">
        <div class="section-header">Comportamiento Esperado</div>
        <div class="section-body">{{ $data['expected'] }}</div>
    </div>
    @endif

    @if(!empty($data['actual']))
    <div class="section">
        <div class="section-header">Comportamiento Observado (Resultado Real)</div>
        <div class="section-body">{{ $data['actual'] }}</div>
    </div>
    @endif

    <!-- Evidencia Gráfica Adjunta -->
    @if(!empty($imagePath))
    <div class="evidence-wrapper">
        <div class="section-header">Evidencia Gráfica Adjunta</div>
        <div class="evidence-container">
            <img src="{{ $imagePath }}" alt="Captura de Evidencia">
        </div>
    </div>
    @endif

    <div class="footer">
        Documento generado automáticamente por el sistema de incidencias de UTAMED &bull; Uso interno y confidencial
    </div>
</body>
</html>