<?php

namespace App\Services;

use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Estudiante;
use App\Services\Student\StudentSyllabusPresenter;
use App\Traits\ParsesSyllabus;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Compila el PDF institucional unificado del Programa de Asignatura (Syllabus)
 * mediante DomPDF para Admin, Docente, Ayudante y Estudiante.
 */
class ProgramaPdfService
{
    use ParsesSyllabus;

    /**
     * Genera el PDF y su nombre de archivo sugerido.
     *
     * @return array{pdf: DomPdfWrapper, filename: string}
     */
    public function generar(
        Curso $curso,
        Programa $programa,
        string $audiencia = 'docente',
        ?Estudiante $estudiante = null,
    ): array {
        $cabecera = SyllabusViewerPresenter::cabeceraCurso($curso);
        $documento = SyllabusViewerPresenter::documento($programa);
        $ultimoRechazo = SyllabusViewerPresenter::ultimoRechazo($programa);

        $asignatura = $curso->asignacionPlan?->asignatura;
        $carrera = $curso->asignacionPlan?->plan?->carrera;

        $raw = is_array($programa->data_syllabus)
            ? $programa->data_syllabus
            : json_decode((string) ($programa->data_syllabus ?? '[]'), true);

        $secciones = StudentSyllabusPresenter::conAsistenciaDelCurso(
            $curso,
            $this->parseSeccionesEstructuradas(is_array($raw) ? $raw : [])
        );

        $docentes = $audiencia === 'estudiante'
            ? StudentSyllabusPresenter::build($curso, $estudiante)['docentes']
            : [];

        $estadoInfo = $this->resolverEstado($programa->estado, $ultimoRechazo['razon_rechazo'] ?? null);

        $logoPath = public_path('icon.png');
        $logoLocal = is_file($logoPath) ? $logoPath : null;

        $tituloAsignatura = $asignatura?->nombre ?? $curso->nombre ?? 'Programa de Asignatura';
        $codigoAsignatura = $cabecera['cod_asignatura'] ?? $asignatura?->cod_asignatura ?? null;

        $pdf = Pdf::loadView('pdf.programa', [
            'curso' => $curso,
            'programa' => $programa,
            'cabecera' => $cabecera,
            'documento' => $documento,
            'estadoInfo' => $estadoInfo,
            'asignatura' => $asignatura,
            'carrera' => $carrera,
            'tituloAsignatura' => $tituloAsignatura,
            'codigoAsignatura' => $codigoAsignatura,
            'docentes' => $docentes,
            'secciones' => array_values(array_filter($secciones, fn (array $s) => !empty($s['tiene_contenido']))),
            'logoPath' => $logoLocal,
            'fechaModificacion' => $this->formatearFecha($documento['fecha_modificacion'] ?? null),
            'fechaAprobacion' => $this->formatearFecha($documento['fecha_aprobacion'] ?? null),
        ])
            ->setPaper('letter', 'portrait')
            ->setOption([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        return [
            'pdf' => $pdf,
            'filename' => $this->buildFilename($codigoAsignatura, $tituloAsignatura, (string) $programa->version_programa),
        ];
    }

    /**
     * @return array{label: string, es_aprobado: bool, bg: string, border: string, color: string}
     */
    private function resolverEstado(?string $estado, ?string $razonRechazo): array
    {
        if ($estado === 'BORRADOR' && trim((string) $razonRechazo) !== '') {
            return [
                'label' => 'RECHAZADO',
                'es_aprobado' => false,
                'bg' => '#fef2f2',
                'border' => '#fecaca',
                'color' => '#b91c1c',
            ];
        }

        return match ($estado) {
            'APROBADO', 'PUBLICADO' => [
                'label' => 'APROBADO',
                'es_aprobado' => true,
                'bg' => '#ecfdf5',
                'border' => '#a7f3d0',
                'color' => '#047857',
            ],
            'BASICO_COMPLETO' => [
                'label' => 'BÁSICO COMPLETO',
                'es_aprobado' => false,
                'bg' => '#eff6ff',
                'border' => '#bfdbfe',
                'color' => '#1d4ed8',
            ],
            'COMPLETO', 'ENVIADO' => [
                'label' => 'EN REVISIÓN',
                'es_aprobado' => false,
                'bg' => '#fffbeb',
                'border' => '#fde68a',
                'color' => '#b45309',
            ],
            default => [
                'label' => $estado ?: 'BORRADOR',
                'es_aprobado' => false,
                'bg' => '#f8fafc',
                'border' => '#cbd5e1',
                'color' => '#475569',
            ],
        };
    }

    private function formatearFecha(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }

        return CarbonImmutable::parse($iso)->format('d/m/Y');
    }

    private function buildFilename(?string $codigo, string $titulo, string $version): string
    {
        $partes = array_filter([
            'Programa',
            $codigo ? Str::slug($codigo, '_') : null,
            Str::slug(Str::limit($titulo, 45, ''), '_'),
            $version !== '' ? "v{$version}" : null,
        ]);

        return implode('_', $partes) . '.pdf';
    }
}
