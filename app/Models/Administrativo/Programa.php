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
     * Check if programa is open for editing
     * 
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->estado === 'ABIERTO';
    }

    /**
     * Check if programa is under review
     * 
     * @return bool
     */
    public function isUnderReview(): bool
    {
        return $this->estado === 'EN_REVISION';
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
}