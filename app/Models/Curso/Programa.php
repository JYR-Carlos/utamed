<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BasePrograma;
use App\Services\ProgramaService;
use App\Syllabus\SyllabusSecciones;

/**
 * Modelo Programa
 * 
 * Extiende de BasePrograma (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Programa extends BasePrograma
{
    protected $casts = [
        'data_syllabus' => 'array',
    ];

    /**
     * Devuelve el tipo de syllabus: 'BASICO', 'COMPLETO', o null si no está definido.
     */
    public function getTipoSyllabus(): ?string
    {
        return $this->data_syllabus['metadata']['tipo_syllabus'] ?? null;
    }

    /**
     * Devuelve el porcentaje de completitud del programa (0-100).
     */
    public function getCompletenessPercentage(): int
    {
        return ProgramaService::calculateCompletenessPercentage($this);
    }

    /**
     * Devuelve true si el programa tiene todas las secciones requeridas completas.
     */
    public function isCompleteWithAllSections(): bool
    {
        return ProgramaService::isComplete($this);
    }

    /**
     * Devuelve las secciones requeridas según el tipo de syllabus.
     */
    public function getRequiredSecciones(): array
    {
        return ProgramaService::getRequiredSecciones($this);
    }

    /**
     * Devuelve las secciones del data_syllabus.
     */
    public function getSecciones(): SyllabusSecciones
    {
        return ProgramaService::getSecciones($this);
    }

    /**
     * True si el estado es BASICO_COMPLETO.
     */
    public function isBasicoCompleto(): bool
    {
        return $this->estado === 'BASICO_COMPLETO';
    }

    /**
     * True si el tipo de syllabus es BASICO.
     */
    public function isBasico(): bool
    {
        return $this->getTipoSyllabus() === 'BASICO';
    }

    /**
     * True si el estado es COMPLETO.
     */
    public function isCompletoState(): bool
    {
        return $this->estado === 'COMPLETO';
    }
}