<?php

namespace App\Syllabus\Secciones;

/** Contenido de la sección VII en syllabus COMPLETO: planificación de la enseñanza. */
final class SeccionVIICompleto
{
    public readonly string $resultadosAprendizajeTitulo;
    /** @var ResultadoAprendizajeItem[] */
    public readonly array $resultadosAprendizajeItems;
    public readonly string $metodologiaTitulo;
    public readonly string $metodologiaTipoEstrategia;
    public readonly string $evaluacionTitulo;
    public readonly string $evaluacionTipoEvaluacion;

    /** @param ResultadoAprendizajeItem[] $resultadosAprendizajeItems */
    public function __construct(
        string $resultadosAprendizajeTitulo,
        array $resultadosAprendizajeItems,
        string $metodologiaTitulo,
        string $metodologiaTipoEstrategia,
        string $evaluacionTitulo,
        string $evaluacionTipoEvaluacion
    ) {
        $this->resultadosAprendizajeTitulo = $resultadosAprendizajeTitulo;
        $this->resultadosAprendizajeItems = $resultadosAprendizajeItems;
        $this->metodologiaTitulo = $metodologiaTitulo;
        $this->metodologiaTipoEstrategia = $metodologiaTipoEstrategia;
        $this->evaluacionTitulo = $evaluacionTitulo;
        $this->evaluacionTipoEvaluacion = $evaluacionTipoEvaluacion;
    }

    public static function fromArray(array $data): self
    {
        $resultados = $data['resultados_aprendizaje'] ?? [];
        $metodologia = $data['metodologia'] ?? [];
        $evaluacion = $data['evaluacion'] ?? [];

        return new self(
            resultadosAprendizajeTitulo: (string) ($resultados['titulo'] ?? ''),
            resultadosAprendizajeItems: ResultadoAprendizajeItem::listFromArray($resultados['items'] ?? []),
            metodologiaTitulo: (string) ($metodologia['titulo'] ?? ''),
            metodologiaTipoEstrategia: (string) ($metodologia['tipo_estrategia'] ?? ''),
            evaluacionTitulo: (string) ($evaluacion['titulo'] ?? ''),
            evaluacionTipoEvaluacion: (string) ($evaluacion['tipo_evaluacion'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'resultados_aprendizaje' => [
                'titulo' => $this->resultadosAprendizajeTitulo,
                'items' => ResultadoAprendizajeItem::listToArray($this->resultadosAprendizajeItems),
            ],
            'metodologia' => [
                'titulo' => $this->metodologiaTitulo,
                'tipo_estrategia' => $this->metodologiaTipoEstrategia,
            ],
            'evaluacion' => [
                'titulo' => $this->evaluacionTitulo,
                'tipo_evaluacion' => $this->evaluacionTipoEvaluacion,
            ],
        ];
    }
}
