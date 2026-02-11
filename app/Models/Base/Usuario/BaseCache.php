<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseCache extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'cache';
    protected $primaryKey = 'key';
    public $incrementing = true;

    protected $fillable = [
        'value',
        'expiration'
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


}
