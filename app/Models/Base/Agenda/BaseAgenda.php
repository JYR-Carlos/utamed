<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Contracts\HasOwnedContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

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
    protected $connection = 'pgsql';
    protected $table = 'agenda';
    protected $primaryKey = 'id_agenda';
    public $incrementing = true;

    protected $fillable = [
        'fecha_envio',
        'mensaje',
        'uuid_archivo_subido',
        'id_usuario_emisor',
        'grupo',
        'id_tipo_registro_agenda'
    ];

    // Relaciones

    public function usuario()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario_emisor', 'id_usuario', 'usuario');
    }

    public function actividadAsignadaGrupo()
    {
        $instance = new \App\Models\Agenda\ActividadAsignadaGrupo();
        return new BelongsTo($instance->newQuery(), $this, 'grupo', 'grupo', 'actividadAsignadaGrupo');
    }

    public function tipoRegistroAgenda()
    {
        $instance = new \App\Models\Agenda\TipoRegistroAgenda();
        return new BelongsTo($instance->newQuery(), $this, 'id_tipo_registro_agenda', 'id_tipo_registro_agenda', 'tipoRegistroAgenda');
    }

    public function archivos()
    {
        $instance = new \App\Models\Operations\Archivos();
        return new BelongsTo($instance->newQuery(), $this, 'uuid_archivo_subido', 'uuid_archivo', 'archivos');
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
