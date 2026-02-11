<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseSeccion extends Model implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Seccion';
    protected $primaryKey = ['id_seccion', 'id_curso'];
    public $incrementing = false;

    protected $fillable = [
        'id_tipo_seccion',
        'id_docente'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        $qualified = parent::qualifyColumn($column);
        // Only quote if not already quoted and contains a dot (table.column)
        if (!str_contains($qualified, '\"') && str_contains($qualified, '.')) {
            return '\"' . str_replace('.', '\".\"', $qualified) . '\"';
        }
        return $qualified;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '\"' . $this->getTable() . '\".\"' . $this->getKeyName() . '\"';
    }


    // Relaciones

    public function tipoSeccion()
    {
        return $this->belongsTo(\App\Models\Curso\TipoSeccion::class, 'id_tipo_seccion', 'id_tipo_seccion');
    }

    public function docente()
    {
        return $this->belongsTo(\App\Models\Usuario\Docente::class, 'id_docente', 'id_docente');
    }

    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, ['id_curso', 'es_plantilla'], ['id_curso', 'es_plantilla']);
    }

    // Relaciones inversas

    public function actividades()
    {
        return $this->hasMany(\App\Models\Agenda\Actividad::class, ['id_seccion', 'id_curso', 'es_plantilla'], ['id_seccion', 'id_curso', 'es_plantilla']);
    }

    public function inscripcionSecciones()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionSeccion::class, ['id_seccion', 'id_curso'], ['id_seccion', 'id_curso']);
    }

    // Relaciones muchos-a-muchos

    public function estudiantesInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            'Inscripcion_Seccion',
            'id_seccion,id_curso',
            'id_estudiante'
        )
            ->withPivot('nota_seccion');
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
