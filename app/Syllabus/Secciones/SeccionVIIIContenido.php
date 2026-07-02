<?php

namespace App\Syllabus\Secciones;

/** Contenido de la sección VIII (Recursos para el Aprendizaje). */
final class SeccionVIIIContenido
{
    /** @var RecursoSyllabus[] */
    public readonly array $recursos;

    /** @param RecursoSyllabus[] $recursos */
    public function __construct(array $recursos)
    {
        $this->recursos = $recursos;
    }

    public static function fromArray(array $data): self
    {
        return new self(RecursoSyllabus::listFromArray($data['recursos'] ?? []));
    }

    public function toArray(): array
    {
        return ['recursos' => RecursoSyllabus::listToArray($this->recursos)];
    }
}
