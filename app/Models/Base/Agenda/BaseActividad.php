<?php

namespace App\Models\Base\Agenda;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseActividad extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Actividad';
    protected $primaryKey = 'id_actividad';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'fecha_limite',
        'visible',
        'tipo_actividad',
        'tipo_entrega',
        'es_grupal',
        'max_integrantes',
        'es_plantilla',
        'id_curso',
        'id_seccion',
        'id_unidad'
    ];

    // Relaciones

    public function seccion()
    {
        return $this->belongsTo(\App\Models\Curso\Seccion::class, ['id_seccion', 'id_curso', 'es_plantilla'], ['id_seccion', 'id_curso', 'es_plantilla']);
    }

    public function unidad()
    {
        return $this->belongsTo(\App\Models\Curso\Unidad::class, ['id_unidad', 'id_curso', 'es_plantilla'], ['id_unidad', 'id_curso', 'es_plantilla']);
    }

    // Relaciones inversas

    public function actividadAsignadas()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignada::class, 'id_actividad', 'id_actividad');
    }

    // Relaciones muchos-a-muchos

    public function estadoActividades()
    {
        return $this->belongsToMany(
            \App\Models\Agenda\EstadoActividad::class,
            '\"utamed.Agenda\".\"Actividad_Asignada\"',
            'id_actividad',
            'id_estado'
        )
        ->withPivot('grupo', 'nota');
    }

}