<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoActividad extends Model
{
    protected $table = 'utamed.estado_actividad';
    protected $primaryKey = 'id_estado';
    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'descripcion',
    ];

    /**
     * Relación: Un estado tiene muchas actividades asignadas
     */
    public function actividadesAsignadas()
    {
        return $this->hasMany(ActividadAsignada::class, 'estado_actual', 'id_estado');
    }
}
