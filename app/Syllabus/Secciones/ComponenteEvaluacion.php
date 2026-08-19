<?php

namespace App\Syllabus\Secciones;

/** Fila de la tabla de componentes de evaluación (sección IX). */
final class ComponenteEvaluacion
{
    public readonly string $componente;
    public readonly float $porcentaje;
    public readonly bool $generaActa;
    public readonly bool $aprobacionObligatoria;
    public readonly ?float $asistenciaObligatoria;

    public function __construct(
        string $componente,
        float $porcentaje,
        bool $generaActa,
        bool $aprobacionObligatoria,
        ?float $asistenciaObligatoria = null
    ) {
        $this->componente = $componente;
        $this->porcentaje = $porcentaje;
        $this->generaActa = $generaActa;
        $this->aprobacionObligatoria = $aprobacionObligatoria;
        $this->asistenciaObligatoria = $asistenciaObligatoria;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            componente: (string) ($data['componente'] ?? ''),
            porcentaje: (float) ($data['porcentaje'] ?? 0),
            generaActa: (bool) ($data['genera_acta'] ?? false),
            aprobacionObligatoria: (bool) ($data['aprobacion_obligatoria'] ?? false),
            asistenciaObligatoria: isset($data['asistencia_obligatoria']) ? (float) $data['asistencia_obligatoria'] : null,
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
            'componente' => $this->componente,
            'porcentaje' => $this->porcentaje,
            'genera_acta' => $this->generaActa,
            'aprobacion_obligatoria' => $this->aprobacionObligatoria,
            'asistencia_obligatoria' => $this->asistenciaObligatoria,
        ];
    }

    /** @param ComponenteEvaluacion[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
