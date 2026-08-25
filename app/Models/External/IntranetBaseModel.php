<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Model;

abstract class IntranetBaseModel extends Model
{
    // 1. Forzamos que todos los hijos usen la conexión Oracle
    protected $connection = 'oracle';

    // 2. Al ser vistas/tablas externas, desactivamos los timestamps de Laravel
    public $timestamps = false;

    // 3. Desactivamos los incrementales (no tienen autoincrementable local de Laravel)
    public $incrementing = false;

    // 4. Permitir instanciación masiva en memoria para DTOs y tests
    protected $guarded = [];

    /**
     * Bloquear operaciones de escritura para proteger la base de datos externa.
     */
    public function save(array $options = [])
    {
        throw new \BadMethodCallException('Los modelos de la Intranet externa son de solo lectura.');
    }

    public function delete()
    {
        throw new \BadMethodCallException('Los modelos de la Intranet externa son de solo lectura.');
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new \BadMethodCallException('Los modelos de la Intranet externa son de solo lectura.');
    }

    /**
     * Oracle suele devolver los nombres de las columnas en MAYÚSCULAS.
     * Permite acceder a los atributos tanto en mayúsculas ($a->ALUM_RUT) como en minúsculas ($a->alum_rut).
     */
    public function getAttribute($key)
    {
        if (\array_key_exists($key, $this->attributes)) {
            return parent::getAttribute($key);
        }

        $upperKey = strtoupper($key);
        if (\array_key_exists($upperKey, $this->attributes)) {
            return parent::getAttribute($upperKey);
        }

        $lowerKey = strtolower($key);
        if (\array_key_exists($lowerKey, $this->attributes)) {
            return parent::getAttribute($lowerKey);
        }

        return parent::getAttribute($key);
    }
}