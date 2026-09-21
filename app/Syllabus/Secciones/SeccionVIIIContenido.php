<?php

namespace App\Syllabus\Secciones;

/**
 * Contenido de la sección VIII (Bibliografía y Recursos para el Aprendizaje).
 */
final class SeccionVIIIContenido
{
    /** @var BibliografiaSyllabus[] */
    public readonly array $bibliografias;

    /**
     * @deprecated Sección VIII ahora utiliza $bibliografias con soporte relacional y de archivos. Mantenido por retrocompatibilidad con versiones anteriores del syllabus.
     * @var RecursoSyllabus[]
     */
    public readonly array $recursos;

    /**
     * @param BibliografiaSyllabus[] $bibliografias
     * @param RecursoSyllabus[] $recursos
     */
    public function __construct(array $bibliografias = [], array $recursos = [])
    {
        $this->bibliografias = $bibliografias;
        $this->recursos = $recursos;
    }

    public static function fromArray(array $data): self
    {
        $bibliografias = BibliografiaSyllabus::listFromArray($data['bibliografias'] ?? []);
        $recursos = RecursoSyllabus::listFromArray($data['recursos'] ?? []);

        return new self($bibliografias, $recursos);
    }

    public function toArray(): array
    {
        $res = [
            'bibliografias' => BibliografiaSyllabus::listToArray($this->bibliografias),
        ];

        // Mantener recursos si existen por retrocompatibilidad
        if (!empty($this->recursos)) {
            $res['recursos'] = RecursoSyllabus::listToArray($this->recursos);
        }

        return $res;
    }
}
