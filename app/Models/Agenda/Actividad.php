<?php

namespace App\Models\Agenda;

use App\Models\Base\Agenda\BaseActividad;
use DateTimeInterface;

/**
 * Modelo Actividad
 *
 * Extiende de BaseActividad (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 *
 * @property \Illuminate\Support\Carbon|null $fecha_limite
 */
class Actividad extends BaseActividad
{
    /**
     * Casts adicionales a los ya declarados en BaseActividad.
     *
     * Se usa el método casts() y no la propiedad $casts porque Laravel fusiona
     * ambos (array_merge), mientras que redeclarar la propiedad reemplazaría
     * los casts de la clase base (tipo_actividad, visible, es_grupal…).
     */
    protected function casts(): array
    {
        return [
            // agenda.actividad.fecha_limite es DATE: sin este cast llega como
            // string y revienta todo el código que la trata como Carbon.
            'fecha_limite' => 'date',
        ];
    }

    /**
     * Serializa las fechas como 'Y-m-d' (sin hora ni zona horaria).
     *
     * fecha_limite es una fecha-solo-día; enviarla como ISO8601 con hora UTC
     * provocaría desfases de un día al mostrarla en Chile (UTC-4/-3).
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}