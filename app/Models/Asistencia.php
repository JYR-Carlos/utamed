<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $table = 'utamed.asistencia';
    
    // Composite primary key
    protected $primaryKey = ['id_curso', 'id_estudiante', 'num_intento', 'num_clase'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_curso',
        'id_estudiante',
        'num_intento',
        'num_clase',
        'fecha',
        'presente',
    ];

    protected $casts = [
        'fecha' => 'date',
        'presente' => 'boolean',
    ];

    protected $attributes = [
        'presente' => false,
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
     * Relación: Una asistencia pertenece a una inscripción
     */
    public function inscripcion()
    {
        return $this->belongsTo(Inscribe::class, 'id_curso', 'id_curso')
            ->where('id_estudiante', $this->id_estudiante)
            ->where('num_intento', $this->num_intento);
    }
}
