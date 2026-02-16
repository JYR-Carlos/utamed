<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseActividad extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Actividad';
    protected $primaryKey = 'id_actividad';
    public $incrementing = true;

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

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

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

}
