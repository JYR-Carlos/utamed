<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasOwnedContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;
use App\Enums\DB\TipoMensaje;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAgenda extends CustomBaseModel implements HasOwnedContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    public TipoMensaje $tipoMensaje;

    protected $connection = 'pgsql';
    protected $table = 'agenda';
    protected $primaryKey = 'id_agenda';
    public $incrementing = true;

    protected $fillable = [
        'fecha_envio',
        'tipo_mensaje',
        'mensaje',
        'uuid_archivo_subido',
        'id_usuario_emisor',
        'id_actividad_asignada_grupo'
    ];

    protected $casts = [
        'tipo_mensaje' => TipoMensaje::class
    ];

    // Relaciones

    public function archivo()
    {
        $instance = new \App\Models\Operaciones\Archivo();
        return new BelongsTo($instance->newQuery(), $this, 'uuid_archivo_subido', 'uuid_archivo', 'archivo');
    }

    public function actividadAsignadaGrupo()
    {
        $instance = new \App\Models\Agenda\ActividadAsignadaGrupo();
        return new BelongsTo($instance->newQuery(), $this, 'id_actividad_asignada_grupo', 'id_actividad_asignada_grupo', 'actividadAsignadaGrupo');
    }

    public function usuario()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario_emisor', 'id_usuario', 'usuario');
    }

    // Relaciones inversas

    public function evaluacion()
    {
        return $this->hasOne(\App\Models\Agenda\Evaluacion::class, 'id_agenda', 'id_agenda');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: actividadAsignadaGrupo
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('actividadAsignadaGrupo', function ($q) use ($contextIds) {
                $q->whereHas('actividad', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
