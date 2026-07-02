<?php

namespace App\Syllabus\Secciones;

/** Item de la sección V (Evaluación Diagnóstica). */
final class EvaluacionDiagnosticaItem
{
    public readonly string $titulo;
    public readonly ?string $descripcion;

    public function __construct(string $titulo, ?string $descripcion = null)
    {
        $this->titulo = $titulo;
        $this->descripcion = $descripcion;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            titulo: (string) ($data['titulo'] ?? ''),
            descripcion: $data['descripcion'] ?? null,
        );
    }

    /** @param array<int, array> $items */
    public static function listFromArray(array $items): array
    {
        return array_map(fn (array $i) => self::fromArray($i), $items);
    }

    public function toArray(): array
    {
        return ['titulo' => $this->titulo, 'descripcion' => $this->descripcion];
    }

    /** @param EvaluacionDiagnosticaItem[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
