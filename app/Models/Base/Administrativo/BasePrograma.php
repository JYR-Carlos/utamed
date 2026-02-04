<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePrograma extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'Programa';
    protected $primaryKey = ['id_programa', 'id_curso', 'es_plantilla', 'es_actual'];
    public $incrementing = false;
    const DELETED_AT = 'fecha_eliminacion';

    public $timestamps = false;

    protected $fillable = [
        'version',
        'unc_programa',
        'id_usuario_autor'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        return is_string($column) && str_contains($column, '.')
            ? $column
            : $this->getTable() . '.' . $column;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }

    // Relaciones

    public function autor()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_autor', 'id_usuario');
    }

    public function curso()
    {
        return $this->belongsTo(\App\Models\Curso\Curso::class, ['id_curso', 'es_plantilla'], ['id_curso', 'es_plantilla']);
    }

}