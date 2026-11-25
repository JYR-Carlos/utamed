<?php

namespace App\Services;

use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Syllabus\SyllabusBloques;
use App\Traits\ParsesSyllabus;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Documento;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * El programa del curso como documento PDF (revisión «Mi UTAmed»): cabecera
 * institucional, títulos oficiales, texto justificado, viñetas alineadas y una
 * sola tipografía. Sale del mismo texto que muestra la pantalla
 * (App\Traits\ParsesSyllabus), así que dice lo mismo que el visor.
 */
class ProgramaPdf
{
    use ParsesSyllabus;

    public function generar(Curso $curso, Programa $programa): Documento
    {
        $pdf = Pdf::loadView('pdf.programa', $this->datos($curso, $programa))->setPaper('letter');
        $pdf->render();

        // El total de páginas sólo se conoce después de componer el documento.
        $dompdf = $pdf->getDomPDF();
        $lienzo = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('Helvetica');
        $gris = [0.37, 0.38, 0.47];
        $base = $lienzo->get_height() - 40;
        $lienzo->page_text(62, $base, 'Universidad de Tarapacá · Programa de asignatura', $fuente, 8, $gris);
        $total = $lienzo->get_page_count();
        $ancho = $dompdf->getFontMetrics()->getTextWidth("Página {$total} de {$total}", $fuente, 8);
        $lienzo->page_text($lienzo->get_width() - 62 - $ancho, $base, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, $gris);

        return $pdf;
    }

    /** «Programa_DM095_Taller_Profesional_IV_2026-2.pdf». */
    public function nombreArchivo(Curso $curso): string
    {
        $asignatura = $curso->asignacionPlan?->asignatura;
        $periodo = $curso->agno_real && $curso->semestre_real ? "{$curso->agno_real}-{$curso->semestre_real}" : null;
        $partes = array_filter(['Programa', $asignatura?->cod_asignatura, $asignatura?->nombre ?? $curso->nombre, $periodo]);

        return preg_replace('/[^A-Za-z0-9_-]+/', '_', Str::ascii(implode(' ', $partes))).'.pdf';
    }

    /** @return array<string, mixed> */
    private function datos(Curso $curso, Programa $programa): array
    {
        $cabecera = SyllabusViewerPresenter::cabeceraCurso($curso);
        $asignatura = $curso->asignacionPlan?->asignatura;
        $semestre = match ((int) $curso->semestre_real) {
            1 => 'Primer semestre',
            2 => 'Segundo semestre',
            default => null,
        };

        $secciones = [];
        foreach ($this->parseSecciones($programa->data_syllabus ?? []) as $seccion) {
            $bloques = $this->bloques($seccion);
            $componentes = $seccion['componentes'] ?? [];

            if ($bloques !== [] || $componentes !== []) {
                $secciones[] = [
                    'numeral' => $seccion['numeral_romano'],
                    'titulo' => $seccion['nombre_seccion'],
                    'bloques' => $bloques,
                    'componentes' => $componentes,
                ];
            }
        }

        return [
            'titulo' => $asignatura?->nombre ?? $curso->nombre,
            'subtitulo' => implode(' · ', array_filter([
                $cabecera['cod_asignatura'],
                $curso->letra_grupo ? "Grupo {$curso->letra_grupo}" : null,
                $semestre && $curso->agno_real ? "{$semestre} {$curso->agno_real}" : null,
            ])),
            'carrera' => $curso->asignacionPlan?->plan?->carrera?->nombre,
            'docente' => $cabecera['docente_titular'] ? Str::title(Str::lower($cabecera['docente_titular'])) : null,
            // Un programa que aún no se aprueba no debe pasar por oficial.
            'noAprobado' => ! in_array($programa->estado, ['APROBADO', 'PUBLICADO'], true),
            'secciones' => $secciones,
            'enlazar' => $this->enlazar(...),
        ];
    }

    /**
     * Bloques de la sección. En la IX los componentes van en su tabla, así que
     * se quita la lista que los repite como texto.
     *
     * @param  array<string, mixed>  $seccion
     * @return list<array<string, mixed>>
     */
    private function bloques(array $seccion): array
    {
        $texto = implode("\n\n", array_filter(
            array_map(fn ($c) => (string) ($c['texto_contenido'] ?? ''), $seccion['contenidos'] ?? []),
            fn ($t) => trim($t) !== '',
        ));
        // Las unidades suelen traer el rótulo dentro del nombre («Unidad 1: Unidad 1 : …»).
        $texto = preg_replace('/^(unidad\s+\d+\s*:)\s*unidad\s+\d+\s*:/imu', '$1', $texto);
        $bloques = array_map($this->presentar(...), SyllabusBloques::de($texto, $seccion['numeral_romano']));

        if (empty($seccion['componentes'])) {
            return $bloques;
        }

        foreach ($bloques as $i => $bloque) {
            if ($bloque['tipo'] === 'subtitulo' && $bloque['texto'] === 'Componentes de evaluación') {
                return array_slice($bloques, 0, $i);
            }
        }

        return $bloques;
    }

    /**
     * Los subtítulos en mayúsculas («UNIDAD 1: PRODUCCIÓN WEB») pasan a frase, y
     * una lista entera con sangría se alinea con las demás.
     *
     * @param  array<string, mixed>  $bloque
     * @return array<string, mixed>
     */
    private function presentar(array $bloque): array
    {
        if ($bloque['tipo'] === 'subtitulo' && $bloque['texto'] === Str::upper($bloque['texto'])) {
            $bloque['texto'] = preg_replace_callback(
                '/^(unidad \d+: )?(\w)/iu',
                fn (array $m) => Str::ucfirst($m[1]).Str::upper($m[2]),
                Str::lower($bloque['texto']),
            );
        }

        if ($bloque['tipo'] === 'lista' && ! in_array(false, array_column($bloque['items'], 'anidado'), true)) {
            $bloque['items'] = array_map(fn (array $item) => [...$item, 'anidado' => false], $bloque['items']);
        }

        return $bloque;
    }

    /** Texto escapado, con las direcciones y los archivos de la biblioteca como enlaces. */
    private function enlazar(?string $texto): HtmlString
    {
        $html = preg_replace_callback(
            '~https?://[^\s,\])>]+|/api/bibliografias/[a-zA-Z0-9-]+/archivo~',
            fn (array $m) => str_starts_with($m[0], '/api/')
                ? '<a href="'.e(url($m[0])).'">Ver archivo</a>'
                : "<a href=\"{$m[0]}\">{$m[0]}</a>",
            e($texto ?? ''),
        );

        return new HtmlString($html);
    }
}
