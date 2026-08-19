<?php

namespace App\Syllabus;

/** Datos académicos de la asignatura (metadata.asignatura). */
final class SyllabusAsignatura
{
    public readonly int $idAsignatura;
    public readonly string $nombre;
    public readonly string $codigo;
    public readonly ?string $descripcion;
    public readonly int $creditosSct;
    public readonly SyllabusHoras $horas;

    public function __construct(
        int $idAsignatura,
        string $nombre,
        string $codigo,
        ?string $descripcion,
        int $creditosSct,
        SyllabusHoras $horas
    ) {
        $this->idAsignatura = $idAsignatura;
        $this->nombre = $nombre;
        $this->codigo = $codigo;
        $this->descripcion = $descripcion;
        $this->creditosSct = $creditosSct;
        $this->horas = $horas;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            idAsignatura: (int) ($data['id_asignatura'] ?? 0),
            nombre: (string) ($data['nombre'] ?? ''),
            codigo: (string) ($data['codigo'] ?? ''),
            descripcion: $data['descripcion'] ?? null,
            creditosSct: (int) ($data['creditos_sct'] ?? 0),
            horas: SyllabusHoras::fromArray($data['horas'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'id_asignatura' => $this->idAsignatura,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'creditos_sct' => $this->creditosSct,
            'horas' => $this->horas->toArray(),
        ];
    }
}
