<?php

namespace App\Syllabus\Secciones;

/** Recurso de aprendizaje (sección VIII). */
final class RecursoSyllabus
{
    public readonly string $descripcion;
    public readonly string $tipo;
    public readonly ?string $ubicacion;

    public function __construct(string $descripcion, string $tipo, ?string $ubicacion = null)
    {
        $this->descripcion = $descripcion;
        $this->tipo = $tipo;
        $this->ubicacion = $ubicacion;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            descripcion: (string) ($data['descripcion'] ?? ''),
            tipo: (string) ($data['tipo'] ?? ''),
            ubicacion: $data['ubicacion'] ?? null,
        );
    }

    /** @param array<int, array> $items */
    public static function listFromArray(array $items): array
    {
        return array_map(fn (array $i) => self::fromArray($i), $items);
    }

    public function toArray(): array
    {
        return ['descripcion' => $this->descripcion, 'tipo' => $this->tipo, 'ubicacion' => $this->ubicacion];
    }

    /** @param RecursoSyllabus[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
