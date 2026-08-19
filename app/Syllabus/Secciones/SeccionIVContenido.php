<?php

namespace App\Syllabus\Secciones;

/** Contenido de la sección IV (Competencias). */
final class SeccionIVContenido
{
    /** @var TituloItem[] */
    public readonly array $competenciasEspecificas;
    /** @var TituloItem[] */
    public readonly array $competenciasGenericas;
    /** @var TituloItem[] */
    public readonly array $subcompetencias;

    /**
     * @param TituloItem[] $competenciasEspecificas
     * @param TituloItem[] $competenciasGenericas
     * @param TituloItem[] $subcompetencias
     */
    public function __construct(array $competenciasEspecificas, array $competenciasGenericas, array $subcompetencias)
    {
        $this->competenciasEspecificas = $competenciasEspecificas;
        $this->competenciasGenericas = $competenciasGenericas;
        $this->subcompetencias = $subcompetencias;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            competenciasEspecificas: TituloItem::listFromArray($data['competencias_especificas'] ?? []),
            competenciasGenericas: TituloItem::listFromArray($data['competencias_genericas'] ?? []),
            subcompetencias: TituloItem::listFromArray($data['subcompetencias'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'competencias_especificas' => TituloItem::listToArray($this->competenciasEspecificas),
            'competencias_genericas' => TituloItem::listToArray($this->competenciasGenericas),
            'subcompetencias' => TituloItem::listToArray($this->subcompetencias),
        ];
    }
}
