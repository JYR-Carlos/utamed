<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseAsignatura;

/**
 * Modelo Asignatura
 * 
 * Implementa un patrón de versionado donde:
 * - Editar una asignatura crea una NUEVA versión
 * - La versión anterior es marcada como eliminada (soft delete)
 * - La versión "activa" es la que tiene fecha_eliminacion = NULL y fecha_creacion más reciente
 * 
 * Esto preserva el historial completo de cambios sin alterar registros históricos.
 */
class Asignatura extends BaseAsignatura
{
    /**
     * Scope para obtener solo las versiones ACTIVAS (no eliminadas)
     * de las asignaturas.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('fecha_eliminacion');
    }

    /**
     * Scope para obtener la versión más reciente de cada código de asignatura.
     * 
     * Útil cuando hay múltiples versiones del mismo código y solo se desea
     * la con fecha_creacion más reciente.
     */
    public function scopeLatestByCode($query)
    {
        return $query->distinct('cod_asignatura')
            ->orderBy('cod_asignatura')
            ->latest('fecha_creacion');
    }

    /**
     * Obtiene la versión anterior a esta asignatura (si existe).
     * 
     * Busca la asignatura eliminada más reciente con el mismo código.
     */
    public function getPreviousVersion()
    {
        return static::where('cod_asignatura', $this->cod_asignatura)
            ->where('id_asignatura', '!=', $this->id_asignatura)
            ->whereNotNull('fecha_eliminacion')
            ->latest('fecha_eliminacion')
            ->first();
    }

    /**
     * Obtiene todas las versiones de esta asignatura (incluyendo la actual y versiones históricas).
     */
    public function getAllVersions()
    {
        return static::where('cod_asignatura', $this->cod_asignatura)
            ->orderByDesc('fecha_creacion')
            ->get();
    }

    /**
     * Verifica si esta versión es la más reciente activa de su código.
     */
    public function isActiveVersion(): bool
    {
        if ($this->fecha_eliminacion !== null) {
            return false;
        }

        $latestActive = static::where('cod_asignatura', $this->cod_asignatura)
            ->active()
            ->latest('fecha_creacion')
            ->first();

        return $latestActive && $latestActive->id_asignatura === $this->id_asignatura;
    }
}