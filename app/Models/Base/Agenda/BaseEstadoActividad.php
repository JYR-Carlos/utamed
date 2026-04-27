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

    public function actividadAsignadaGrupos()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignadaGrupo::class, 'id_estado', 'id_estado');
    }

}
