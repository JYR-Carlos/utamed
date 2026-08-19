<?php

namespace App\Syllabus\Shell;

/**
 * Item de contenido del formato transitorio de 6 secciones (SyllabusStructure::build(),
 * antes de que el wizard guarde contenido real).
 */
final class ContenidoSecuencial
{
    public readonly string $textoContenido;
    public readonly int $ordenItem;
    public readonly ?string $descripcion;
    public readonly ?int $numUnidad;

    public function __construct(string $textoContenido, int $ordenItem, ?string $descripcion = null, ?int $numUnidad = null)
    {
        $this->textoContenido = $textoContenido;
        $this->ordenItem = $ordenItem;
        $this->descripcion = $descripcion;
        $this->numUnidad = $numUnidad;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            textoContenido: (string) ($data['texto_contenido'] ?? ''),
            ordenItem: (int) ($data['orden_item'] ?? 1),
            descripcion: $data['descripcion'] ?? null,
            numUnidad: isset($data['num_unidad']) ? (int) $data['num_unidad'] : null,
        );
    }

    /** @param array<int, array> $items */
    public static function listFromArray(array $items): array
    {
        return array_map(fn (array $i) => self::fromArray($i), $items);
    }

    public function toArray(): array
    {
        return array_filter([
            'texto_contenido' => $this->textoContenido,
            'orden_item' => $this->ordenItem,
            'descripcion' => $this->descripcion,
            'num_unidad' => $this->numUnidad,
        ], fn ($v) => $v !== null);
    }

    /** @param ContenidoSecuencial[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
