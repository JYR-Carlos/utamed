<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwUsuariosCompleto extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'vw_usuarios_completo';
    protected $primaryKey = 'id';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'rut',
        'username',
        'nombre_completo',
        'email',
        'tipo_usuario',
        'id_estudiante',
        'agno_ingreso',
        'carrera_nombre',
        'id_docente',
        'grado',
        'titulo',
        'cargo',
        'esta_activo'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        // If already qualified (contains table.column format with quotes), return as-is
        if (preg_match('/^"[^"]+"\."[^"]+"$/', $column)) {
            return $column;
        }
        
        // Remove any existing quotes
        $column = str_replace(['"', "'"], '', $column);
        
        // If column contains a dot, it's already in table.column format
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);
            return '"' . $table . '"."' . $col . '"';
        }
        
        // Single column, qualify with table name
        return '"' . $this->getTable() . '"."' . $column . '"';
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '"' . $this->getTable() . '"."' . $this->getKeyName() . '"';
    }


}
