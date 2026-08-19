<?php

namespace App\Syllabus\Secciones;

/** Contenido de la sección VI (Unidades y Contenidos de Aprendizaje). */
final class SeccionVIContenido
{
    /** @var UnidadSyllabus[] */
    public readonly array $unidades;

    /** @param UnidadSyllabus[] $unidades */
    public function __construct(array $unidades)
    {
        $this->unidades = $unidades;
    }

    public static function fromArray(array $data): self
    {
        return new self(UnidadSyllabus::listFromArray($data['unidades'] ?? []));
    }

    public function toArray(): array
    {
        return ['unidades' => UnidadSyllabus::listToArray($this->unidades)];
    }
}
