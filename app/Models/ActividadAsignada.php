<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActividadAsignada extends Model
{
    protected $table = 'utamed.actividad_asignada';
    
    // Composite primary key
    protected $primaryKey = ['id_actividad', 'grupo'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'grupo',
        'nota',
        'estado_actual',
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
     * Relación: Una actividad asignada pertenece a una actividad
     */
    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'id_actividad', 'id_actividad');
    }

    /**
     * Relación: Una actividad asignada pertenece a un estado
     */
    public function estadoActividad()
    {
        return $this->belongsTo(EstadoActividad::class, 'estado_actual', 'id_estado');
    }

    /**
     * Relación: Una actividad asignada tiene muchos estudiantes asignados
     */
    public function asignaciones()
    {
        return $this->hasMany(AsignadoActividad::class, 'id_actividad_Actividad_Asignada', 'id_actividad')
            ->where('grupo_Actividad_Asignada', $this->grupo);
    }

    /**
     * Relación: Una actividad asignada tiene muchos registros de agenda
     */
    public function registrosAgenda()
    {
        return $this->hasMany(RegistroAgenda::class, 'id_actividad', 'id_actividad')
            ->where('id_grupo', $this->grupo);
    }
}
