<?php

namespace App\Syllabus\Secciones;

/** Contenido de la sección V (Evaluación Diagnóstica). */
final class SeccionVContenido
{
    /** @var EvaluacionDiagnosticaItem[] */
    public readonly array $items;

    /** @param EvaluacionDiagnosticaItem[] $items */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function fromArray(array $data): self
    {
        return new self(EvaluacionDiagnosticaItem::listFromArray($data['items'] ?? []));
    }

    public function toArray(): array
    {
        return ['items' => EvaluacionDiagnosticaItem::listToArray($this->items)];
    }
}
