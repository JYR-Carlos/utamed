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
abstract class BaseDocente extends Model implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Docente';
    protected $primaryKey = 'id_docente';
    public $incrementing = true;

    protected $fillable = [
        'grado',
        'titulo',
        'cargo',
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

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    // Relaciones inversas

    public function seccionesQueDicta()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_docente', 'id_docente');
    }

}
