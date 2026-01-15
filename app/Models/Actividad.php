<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    protected $table = 'utamed.actividad';
    protected $primaryKey = 'id_actividad';
    public $timestamps = false;

    protected $fillable = [
        'id_curso',
        'num_unidad',
        'nombre',
        'fecha_limite',
        'visible',
        'tipo_actividad',
    ];

    protected $casts = [
        'fecha_limite' => 'date',
        'visible' => 'boolean',
    ];

    protected $attributes = [
        'visible' => true,
    ];

    /**
     * Relación: Una actividad pertenece a un curso
     */
    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'id_curso');
    }

    /**
     * Relación: Una actividad pertenece a una unidad
     */
    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'id_curso', 'id_curso')
            ->where('num_unidad', $this->num_unidad);
    }

    /**
     * Relación: Una actividad pertenece a un tipo de actividad
     */
    public function tipoActividad()
    {
        return $this->belongsTo(TipoActividad::class, 'tipo_actividad', 'id_tipo');
    }

    /**
     * Relación: Una actividad tiene muchas asignaciones (grupos)
     */
    public function actividadesAsignadas()
    {
        return $this->hasMany(ActividadAsignada::class, 'id_actividad', 'id_actividad');
    }

    /**
     * Relación: Una actividad tiene una rúbrica analítica
     */
    public function rubricaAnalitica()
    {
        return $this->hasOne(RubricaAnalitica::class, 'id_actividad', 'id_actividad');
    }
}
