<?php

namespace App\Syllabus\Secciones;

/** Actividad de aprendizaje asociada a una unidad (sección VII en BASICO). */
final class ActividadSyllabus
{
    public readonly ?int $idActividad;
    public readonly string $nombre;
    public readonly string $tipo;
    public readonly string $nombreUnidad;

    public function __construct(?int $idActividad, string $nombre, string $tipo, string $nombreUnidad)
    {
        $this->idActividad = $idActividad;
        $this->nombre = $nombre;
        $this->tipo = $tipo;
        $this->nombreUnidad = $nombreUnidad;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            idActividad: isset($data['id_actividad']) ? (int) $data['id_actividad'] : null,
            nombre: (string) ($data['nombre'] ?? ''),
            tipo: (string) ($data['tipo'] ?? ''),
            nombreUnidad: (string) ($data['nombre_unidad'] ?? ''),
        );
    }

    /** @param array<int, array> $items */
    public static function listFromArray(array $items): array
    {
        return array_map(fn (array $i) => self::fromArray($i), $items);
    }

    public function toArray(): array
    {
        return [
            'id_actividad' => $this->idActividad,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'nombre_unidad' => $this->nombreUnidad,
        ];
    }

    /** @param ActividadSyllabus[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
