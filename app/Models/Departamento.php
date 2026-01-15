<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $table = 'utamed.departamento';
    
    // Composite primary key
    protected $primaryKey = ['id_departamento', 'id_facultad'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_departamento',
        'id_facultad',
        'nombre',
    ];

    /**
     * Set the keys for a save update query.
     * Needed for composite keys
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

    /**
     * Get the value for a given key.
     */
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
     * Relación: Un departamento pertenece a una facultad
     */
    public function facultad()
    {
        return $this->belongsTo(Facultad::class, 'id_facultad', 'id_facultad');
    }

    /**
     * Relación: Un departamento tiene muchas carreras
     */
    public function carreras()
    {
        return $this->hasMany(Carrera::class, 'id_departamento', 'id_departamento');
    }
}
