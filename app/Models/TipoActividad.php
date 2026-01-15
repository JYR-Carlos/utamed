<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoActividad extends Model
{
    protected $table = 'utamed.tipo_actividad';
    protected $primaryKey = 'id_tipo';
    public $timestamps = false;

    protected $fillable = [
        'tipo_entrega',
        'es_grupal',
    ];

    protected $casts = [
        'es_grupal' => 'boolean',
    ];

    protected $attributes = [
        'es_grupal' => false,
    ];

    /**
     * Relación: Un tipo de actividad tiene muchas actividades
     */
    public function actividades()
    {
        return $this->hasMany(Actividad::class, 'tipo_actividad', 'id_tipo');
    }
}
