<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstadoActividad extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'estado_actividad';
    protected $primaryKey = 'id_estado';
    public $incrementing = true;

    protected $fillable = [
        'titulo',
        'descripcion'
    ];


    // Relaciones

    // Relaciones inversas

    public function actividadAsignadas()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignada::class, 'id_estado', 'id_estado');
    }

    // Relaciones muchos-a-muchos

    public function actividadesConEstado()
    {
        return $this->belongsToMany(
            \App\Models\Agenda\Actividad::class,
            'actividad_asignada',
            'id_estado',
            'id_actividad'
        )
            ->withPivot('grupo', 'nota', 'id_actividad', 'id_estado');
    }

}
