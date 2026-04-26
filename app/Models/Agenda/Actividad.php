<?php

namespace App\Models\Agenda;

use App\Models\Base\Agenda\BaseActividad;

/**
 * Modelo Actividad
 * 
 * Extiende de BaseActividad (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Actividad extends BaseActividad
{
    // id_contexto no está en el fillable del modelo base auto-generado
    protected $fillable = [
        'nombre',
        'fecha_limite',
        'visible',
        'tipo_actividad',
        'tipo_entrega',
        'es_grupal',
        'max_integrantes',
        'es_plantilla',
        'id_componente',
        'id_unidad',
        'id_contexto',
    ];
}