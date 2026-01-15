<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroAgenda extends Model
{
    protected $table = 'utamed.registro_agenda';
    
    // Composite primary key
    protected $primaryKey = ['id_actividad', 'id_grupo', 'id_registro', 'autor'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_grupo',
        'id_registro',
        'autor',
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
     * Relación: Un registro pertenece a una actividad asignada
     */
    public function actividadAsignada()
    {
        return $this->belongsTo(ActividadAsignada::class, 'id_actividad', 'id_actividad')
            ->where('grupo', $this->id_grupo);
    }

    /**
     * Relación: Un registro pertenece a un usuario (autor)
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'autor', 'id_usuario');
    }
}
