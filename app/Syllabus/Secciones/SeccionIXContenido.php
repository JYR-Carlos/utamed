<?php

namespace App\Syllabus\Secciones;

/** Contenido de la sección IX (Aspectos Administrativos y Evaluación). */
final class SeccionIXContenido
{
    public readonly string $descripcion;
    public readonly float $ponderacionOptativaPorcentaje;
    /** @var ComponenteEvaluacion[] */
    public readonly array $tablaComponentes;

    /** @param ComponenteEvaluacion[] $tablaComponentes */
    public function __construct(string $descripcion, float $ponderacionOptativaPorcentaje, array $tablaComponentes)
    {
        $this->descripcion = $descripcion;
        $this->ponderacionOptativaPorcentaje = $ponderacionOptativaPorcentaje;
        $this->tablaComponentes = $tablaComponentes;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            descripcion: (string) ($data['descripcion'] ?? ''),
            ponderacionOptativaPorcentaje: (float) ($data['ponderacion_optativa']['porcentaje'] ?? 0),
            tablaComponentes: ComponenteEvaluacion::listFromArray($data['tabla_componentes'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'descripcion' => $this->descripcion,
            'ponderacion_optativa' => ['porcentaje' => $this->ponderacionOptativaPorcentaje],
            'tabla_componentes' => ComponenteEvaluacion::listToArray($this->tablaComponentes),
        ];
    }
}
