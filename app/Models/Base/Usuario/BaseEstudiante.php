<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstudiante extends Model implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Estudiante';
    protected $primaryKey = 'id_estudiante';
    public $incrementing = true;

    protected $fillable = [
        'agno_ingreso',
        'id_usuario'
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

    public function carrera()
    {
        return $this->belongsTo(\App\Models\Administrativo\Carrera::class, 'id_carrera', 'id_carrera');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    // Relaciones inversas

    public function asignadoActividades()
    {
        return $this->hasMany(\App\Models\Agenda\AsignadoActividad::class, 'id_estudiante', 'id_estudiante');
    }

    public function inscripcionCursos()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionCurso::class, 'id_estudiante', 'id_estudiante');
    }

    public function inscripcionSecciones()
    {
        return $this->hasMany(\App\Models\Curso\InscripcionSeccion::class, 'id_estudiante', 'id_estudiante');
    }

    // Relaciones muchos-a-muchos

    public function actividadesAsignadas()
    {
        return $this->belongsToMany(
            \App\Models\Agenda\ActividadAsignada::class,
            'Asignado_Actividad',
            'id_estudiante',
            'grupo,id_actividad'
        )
            ->withPivot('nota_individual', 'diferencia_decimas');
    }

    public function cursosInscritos()
    {
        return $this->belongsToMany(
            \App\Models\Curso\Curso::class,
            'Inscripcion_Curso',
            'id_estudiante',
            'id_curso'
        )
            ->withPivot('cod_inscripcion_uta', 'num_intento', 'fecha_inscripcion', 'estado_inscripcion', 'promedio_parcial');
    }

    public function seccionesInscritas()
    {
        return $this->belongsToMany(
            \App\Models\Curso\Seccion::class,
            'Inscripcion_Seccion',
            'id_estudiante',
            'id_seccion,id_curso'
        )
            ->withPivot('nota_seccion');
    }

}
