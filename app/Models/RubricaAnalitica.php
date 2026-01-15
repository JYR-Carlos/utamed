<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricaAnalitica extends Model
{
    protected $table = 'utamed.rubrica_analitica';
    protected $primaryKey = 'id_rubrica';
    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'descripcion',
    ];

    /**
     * Relación: Una rúbrica pertenece a una actividad
     */
    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'id_actividad', 'id_actividad');
    }

    /**
     * Relación: Una rúbrica tiene muchos criterios
     */
    public function criterios()
    {
        return $this->hasMany(CriterioRubrica::class, 'id_rubrica', 'id_rubrica');
    }

    /**
     * Relación: Una rúbrica tiene muchos niveles
     */
    public function niveles()
    {
        return $this->hasMany(NivelRubrica::class, 'id_rubrica', 'id_rubrica');
    }
}
