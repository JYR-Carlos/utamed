<?php

namespace App\Models\Base\Curso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseInscripcionSeccion extends Model implements HasContext
{
    use HasFactory;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Inscripcion_Seccion';
    protected $primaryKey = ['id_estudiante', 'id_seccion', 'id_curso'];
    public $incrementing = false;

    protected $fillable = [
        'id_estudiante',
        'id_seccion',
        'id_curso',
        'nota_seccion'
    ];


    // Relaciones

    public function estudiante()
    {
        return $this->belongsTo(\App\Models\Usuario\Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function seccion()
    {
        return $this->belongsTo(\App\Models\Curso\Seccion::class, ['id_seccion', 'id_curso'], ['id_seccion', 'id_curso']);
    }

    // Relaciones inversas

    public function asistencias()
    {
        return $this->hasMany(\App\Models\Curso\Asistencia::class, ['id_estudiante', 'id_seccion', 'id_curso'], ['id_estudiante', 'id_seccion', 'id_curso']);
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Paths múltiples detectados.
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('estudiante', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            })
            ->orWhereHas('seccion', function ($q) use ($contextIds) {
                $q->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
