<?php

namespace App\Syllabus;

/** Información de la oferta de curso (metadata.curso). */
final class SyllabusCurso
{
    public readonly int $idCurso;
    public readonly string $codigo;
    public readonly int $agnoAcademico;
    public readonly int $semestre;
    public readonly bool $esPlantilla;
    public readonly int $seccionCount;
    public readonly ?SyllabusDocentePrincipal $docentePrincipal;

    public function __construct(
        int $idCurso,
        string $codigo,
        int $agnoAcademico,
        int $semestre,
        bool $esPlantilla,
        int $seccionCount,
        ?SyllabusDocentePrincipal $docentePrincipal
    ) {
        $this->idCurso = $idCurso;
        $this->codigo = $codigo;
        $this->agnoAcademico = $agnoAcademico;
        $this->semestre = $semestre;
        $this->esPlantilla = $esPlantilla;
        $this->seccionCount = $seccionCount;
        $this->docentePrincipal = $docentePrincipal;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            idCurso: (int) ($data['id_curso'] ?? 0),
            codigo: (string) ($data['codigo'] ?? ''),
            agnoAcademico: (int) ($data['año_academico'] ?? 0),
            semestre: (int) ($data['semestre'] ?? 0),
            esPlantilla: (bool) ($data['es_plantilla'] ?? false),
            seccionCount: (int) ($data['seccion_count'] ?? 0),
            docentePrincipal: isset($data['docente_principal']) && $data['docente_principal'] !== null
                ? SyllabusDocentePrincipal::fromArray($data['docente_principal'])
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id_curso' => $this->idCurso,
            'codigo' => $this->codigo,
            'año_academico' => $this->agnoAcademico,
            'semestre' => $this->semestre,
            'es_plantilla' => $this->esPlantilla,
            'seccion_count' => $this->seccionCount,
            'docente_principal' => $this->docentePrincipal?->toArray(),
        ];
    }
}
