<?php

namespace App\Models\Operaciones;

use App\Models\Base\Operaciones\BaseArchivo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Modelo Archivo
 * 
 * Extiende de BaseArchivo (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Archivo extends BaseArchivo
{
    use HasUuids;

    /**
     * Le indica a Laravel qué columnas deben recibir un UUID automáticamente.
     */
    public function uniqueIds(): array
    {
        return ['uuid_archivo'];
    }

    /**
     * Fuerza a Laravel a generar un UUID versión 7 en lugar de la versión 4 por defecto.
     */
    public function newUniqueId(): string
    {
        return (string) \Illuminate\Support\Str::uuid7();
    }
}