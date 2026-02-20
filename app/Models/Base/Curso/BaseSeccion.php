<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseSeccion extends CustomBaseModel implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'seccion';
    protected $primaryKey = 'id_seccion';
    public $incrementing = true;

    protected $fillable = [
        'genera_acta',
        'porcentaje_aprobacion',
        'aprobacion_obligatoria',
        'porcentaje_asistencia_obligatoria',
        'id_docente',
        'id_tipo_seccion',
        'id_curso'
    ];


    // Relaciones

    public function docente()
    {
        $instance = new \App\Models\Usuario\Docente();
        return new BelongsTo($instance->newQuery(), $this, 'id_docente', 'id_docente', 'docente');
    }

    public function tipoSeccion()
    {
        $instance = new \App\Models\Curso\TipoSeccion();
        return new BelongsTo($instance->newQuery(), $this, 'id_tipo_seccion', 'id_tipo_seccion', 'tipoSeccion');
    }

    public function curso()
    {
        $instance = new \App\Models\Curso\Curso();
        return new BelongsTo($instance->newQuery(), $this, 'id_curso', 'id_curso', 'curso');
    }

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, 'id_seccion', 'id_seccion');
    }

    public function inscripcionSecciones()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionSeccion::class, 'id_seccion', 'id_seccion');
    }

    // Relaciones muchos-a-muchos

    public function estudiantesInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            'inscripcion_seccion',
            'id_seccion',
            'id_estudiante'
        )
            ->withPivot('id_inscripcion_seccion', 'nota_seccion');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: curso
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
    }
}
