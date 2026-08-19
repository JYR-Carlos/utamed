<?php

namespace App\Syllabus\Secciones;

/** Contenido de la sección VII en syllabus BASICO: actividades de aprendizaje. */
final class SeccionVIIBasico
{
    /** @var ActividadSyllabus[] */
    public readonly array $actividades;

    /** @param ActividadSyllabus[] $actividades */
    public function __construct(array $actividades)
    {
        $this->actividades = $actividades;
    }

    public static function fromArray(array $data): self
    {
        return new self(ActividadSyllabus::listFromArray($data['actividades'] ?? []));
    }

    public function toArray(): array
    {
        return ['actividades' => ActividadSyllabus::listToArray($this->actividades)];
    }
}
