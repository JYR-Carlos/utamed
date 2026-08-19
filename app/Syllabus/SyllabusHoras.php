<?php

namespace App\Syllabus;

/**
 * Desglose de horas de dedicación. Reutilizado por metadata.asignatura.horas
 * (5 campos) y por secciones.I.contenido.horas (solo catedra/taller/laboratorio,
 * dirigidas/autonomas quedan null).
 */
final class SyllabusHoras
{
    public readonly int $catedra;
    public readonly int $taller;
    public readonly int $laboratorio;
    public readonly ?int $dirigidas;
    public readonly ?int $autonomas;

    public function __construct(
        int $catedra,
        int $taller,
        int $laboratorio,
        ?int $dirigidas = null,
        ?int $autonomas = null
    ) {
        $this->catedra = $catedra;
        $this->taller = $taller;
        $this->laboratorio = $laboratorio;
        $this->dirigidas = $dirigidas;
        $this->autonomas = $autonomas;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            catedra: (int) ($data['catedra'] ?? 0),
            taller: (int) ($data['taller'] ?? 0),
            laboratorio: (int) ($data['laboratorio'] ?? 0),
            dirigidas: isset($data['dirigidas']) ? (int) $data['dirigidas'] : null,
            autonomas: isset($data['autonomas']) ? (int) $data['autonomas'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'catedra' => $this->catedra,
            'taller' => $this->taller,
            'laboratorio' => $this->laboratorio,
            'dirigidas' => $this->dirigidas,
            'autonomas' => $this->autonomas,
        ], fn ($v) => $v !== null);
    }
}
