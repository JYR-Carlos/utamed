<?php

namespace App\Syllabus\Secciones;

/** Item con un solo campo `titulo` (competencias específicas/genéricas/subcompetencias). */
final class TituloItem
{
    public readonly string $titulo;

    public function __construct(string $titulo)
    {
        $this->titulo = $titulo;
    }

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['titulo'] ?? ''));
    }

    /** @param array<int, array> $items */
    public static function listFromArray(array $items): array
    {
        return array_map(fn (array $i) => self::fromArray($i), $items);
    }

    public function toArray(): array
    {
        return ['titulo' => $this->titulo];
    }

    /** @param TituloItem[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
