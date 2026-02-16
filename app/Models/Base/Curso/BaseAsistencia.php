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
abstract class BaseAsistencia extends Model implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Asistencia';
    protected $primaryKey = ['id_asistencia', 'id_estudiante', 'id_seccion', 'id_curso'];
    public $incrementing = false;

    protected $fillable = [
        'dia',
        'hora_inicio',
        'hora_fin',
        'esta_presente'
    ];


    // Relaciones

    public function inscripcionSeccion()
    {
        return $this->belongsTo(\App\Models\Curso\InscripcionSeccion::class, ['id_estudiante', 'id_seccion', 'id_curso'], ['id_estudiante', 'id_seccion', 'id_curso']);
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

        return $query->whereHas('inscripcionSeccion', function ($q) use ($contextIds) {
                $q->whereHas('estudiante', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
            })
            ->orWhereHas('inscripcionSeccion', function ($q) use ($contextIds) {
                $q->whereHas('seccion', function ($q) use ($contextIds) {
                $q->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
            });
    }
}
