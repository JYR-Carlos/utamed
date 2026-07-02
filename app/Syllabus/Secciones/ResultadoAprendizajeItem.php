<?php

namespace App\Syllabus\Secciones;

/** Item con un solo campo `resultado` (resultados de aprendizaje por unidad, sección VI y VII). */
final class ResultadoAprendizajeItem
{
    public readonly string $resultado;

    public function __construct(string $resultado)
    {
        $this->resultado = $resultado;
    }

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['resultado'] ?? ''));
    }

    /** @param array<int, array> $items */
    public static function listFromArray(array $items): array
    {
        return array_map(fn (array $i) => self::fromArray($i), $items);
    }

    public function toArray(): array
    {
        return ['resultado' => $this->resultado];
    }

    /** @param ResultadoAprendizajeItem[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
