<?php

namespace App\Syllabus;

/** Clasificación de la asignatura (metadata.categoria). */
final class SyllabusCategoria
{
    public readonly string $tipo;
    public readonly string $descripcion;

    public function __construct(string $tipo, string $descripcion)
    {
        $this->tipo = $tipo;
        $this->descripcion = $descripcion;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tipo: (string) ($data['tipo'] ?? ''),
            descripcion: (string) ($data['descripcion'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return ['tipo' => $this->tipo, 'descripcion' => $this->descripcion];
    }
}
