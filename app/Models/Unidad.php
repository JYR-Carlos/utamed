<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    protected $table = 'utamed.unidad';
    
    // Composite primary key
    protected $primaryKey = ['id_curso', 'num_unidad'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_curso',
        'num_unidad',
    ];

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * Relación: Una unidad pertenece a un curso
     */
    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'id_curso');
    }

    /**
     * Relación: Una unidad tiene muchas actividades
     */
    public function actividades()
    {
        return $this->hasMany(Actividad::class, 'id_curso', 'id_curso')
            ->where('num_unidad', $this->num_unidad);
    }
}
