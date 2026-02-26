<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BasePrograma;

/**
 * Modelo Programa
 * 
 * Extiende de BasePrograma (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Programa extends BasePrograma
{
    /**
     * Casting de atributos
     * 
     * El campo data_syllabus es JSONB y se castea automáticamente
     * a array para facilitar el manejo en la aplicación
     */
    protected $casts = [
        'data_syllabus' => 'json',
        'fecha_creacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
        'fecha_eliminacion' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'es_actual' => 'boolean',
    ];

    /**
     * Get the syllabus structure
     * 
     * @return array|null
     */
    public function getSyllabusStructure(): ?array
    {
        return $this->data_syllabus;
    }

    /**
     * Get metadata from syllabus
     * 
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->data_syllabus['metadata'] ?? [];
    }

    /**
     * Get sections from syllabus
     * 
     * @return array
     */
    public function getSecciones(): array
    {
        return $this->data_syllabus['secciones'] ?? [];
    }

    /**
     * Get asignatura data from metadata
     * 
     * @return array
     */
    public function getAsignatura(): array
    {
        return $this->getMetadata()['asignatura'] ?? [];
    }

    /**
     * Get curso data from metadata
     * 
     * @return array
     */
    public function getCursoData(): array
    {
        return $this->getMetadata()['curso'] ?? [];
    }

    /**
     * Get categoria from metadata
     * 
     * @return array
     */
    public function getCategoria(): array
    {
        return $this->getMetadata()['categoria'] ?? [];
    }

    /**
     * Get tipo_syllabus from metadata
     * 
     * @return string|null (BASICO or COMPLETO)
     */
    public function getTipoSyllabus(): ?string
    {
        return $this->data_syllabus['metadata']['tipo_syllabus'] ?? null;
    }

    /**
     * Check if programa is basic type
     * 
     * @return bool
     */
    public function isBasico(): bool
    {
        return $this->getTipoSyllabus() === 'BASICO';
    }

    /**
     * Check if programa is complete type
     * 
     * @return bool
     */
    public function isCompleto(): bool
    {
        return $this->getTipoSyllabus() === 'COMPLETO';
    }

    /**
     * Check if programa is basic and complete
     * 
     * @return bool
     */
    public function isBasicoCompleto(): bool
    {
        return $this->estado === 'BASICO_COMPLETO';
    }

    /**
     * Check if programa is complete version
     * 
     * @return bool
     */
    public function isCompletoState(): bool
    {
        return $this->estado === 'COMPLETO';
    }

    /**
     * Check if programa is approved
     * 
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->estado === 'APROBADO';
    }

    /**
     * Check if programa is published
     * 
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->estado === 'PUBLICADO';
    }

    /**
     * Check if programa can be edited
     * 
     * @return bool
     */
    public function isEditable(): bool
    {
        return in_array($this->estado, ['BASICO_COMPLETO', 'COMPLETO']);
    }

    /**
     * Get required sections based on tipo_syllabus
     * 
     * @return array
     */
    public function getRequiredSecciones(): array
    {
        return $this->getTipoSyllabus() === 'BASICO' 
            ? ['I', 'II', 'VI', 'VII', 'VIII']
            : ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
    }

    /**
     * Check if programa has all required sections with content
     * 
     * @return bool
     */
    public function isCompleteWithAllSections(): bool
    {
        $secciones = $this->getSecciones();
        $required = $this->getRequiredSecciones();
        
        foreach ($required as $seccionId) {
            if (!isset($secciones[$seccionId]) || empty($secciones[$seccionId]['contenido'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Calculate completeness percentage based on required sections
     * 
     * @return int (0-100)
     */
    public function getCompletenessPercentage(): int
    {
        $secciones = $this->getSecciones();
        $required = $this->getRequiredSecciones();
        $completed = 0;
        
        foreach ($required as $seccionId) {
            if (isset($secciones[$seccionId]) && !empty($secciones[$seccionId]['contenido'])) {
                $completed++;
            }
        }
        
        return count($required) > 0 ? (int)(($completed / count($required)) * 100) : 0;
    }
}