<?php

namespace App\Syllabus\Secciones;

/** Contenido de las secciones II (Presentación) y III (Estándares): un solo texto. */
final class SeccionTextoContenido
{
    public readonly string $texto;

    public function __construct(string $texto)
    {
        $this->texto = $texto;
    }

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['texto'] ?? ''));
    }

    public function toArray(): array
    {
        return ['texto' => $this->texto];
    }
}
