<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignadoActividad extends Model
{
    protected $table = 'utamed.asignado_actividad';
    protected $primaryKey = 'id_estudiante';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'grupo_Actividad_Asignada',
        'id_actividad_Actividad_Asignada',
    ];

    /**
     * Relación: Una asignación pertenece a un estudiante
     */
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    /**
     * Relación: Una asignación pertenece a una actividad asignada
     */
    public function actividadAsignada()
    {
        return $this->belongsTo(ActividadAsignada::class, 'id_actividad_Actividad_Asignada', 'id_actividad')
            ->where('grupo', $this->grupo_Actividad_Asignada);
    }
}
