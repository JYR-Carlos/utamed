<?php

namespace App\Syllabus\Secciones;

use App\Syllabus\SyllabusHoras;

/** Contenido de la sección I (Identificación de la Asignatura). */
final class SeccionIContenido
{
    public readonly string $nombreAsignatura;
    public readonly string $codigo;
    public readonly int $creditosSct;
    public readonly SyllabusHoras $horas;
    public readonly string $categoria;

    public function __construct(string $nombreAsignatura, string $codigo, int $creditosSct, SyllabusHoras $horas, string $categoria)
    {
        $this->nombreAsignatura = $nombreAsignatura;
        $this->codigo = $codigo;
        $this->creditosSct = $creditosSct;
        $this->horas = $horas;
        $this->categoria = $categoria;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nombreAsignatura: (string) ($data['nombre_asignatura'] ?? ''),
            codigo: (string) ($data['codigo'] ?? ''),
            creditosSct: (int) ($data['creditos_sct'] ?? 0),
            horas: SyllabusHoras::fromArray($data['horas'] ?? []),
            categoria: (string) ($data['categoria'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'nombre_asignatura' => $this->nombreAsignatura,
            'codigo' => $this->codigo,
            'creditos_sct' => $this->creditosSct,
            'horas' => $this->horas->toArray(),
            'categoria' => $this->categoria,
        ];
    }
}
