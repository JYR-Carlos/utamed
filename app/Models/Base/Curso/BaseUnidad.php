<?php

namespace App\Models\Base\Curso;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUnidad extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Unidad';
    protected $primaryKey = ['id_unidad', 'id_curso'];
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'num_unidad',
        'nombre',
        'descripcion'
    ];

    // Relaciones

    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, 'id_curso', 'id_curso');
    }

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, ['id_unidad', 'id_curso', 'es_plantilla'], ['id_unidad', 'id_curso', 'es_plantilla']);
    }

}