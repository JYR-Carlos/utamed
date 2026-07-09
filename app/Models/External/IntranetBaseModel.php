<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Model;

abstract class IntranetBaseModel extends Model
{
    // 1. Forzamos que todos los hijos usen la conexión Oracle
    protected $connection = 'oracle';

    // 2. Al ser vistas, desactivamos los timestamps de Laravel (created_at/updated_at)
    public $timestamps = false;

    // 3. Desactivamos los incrementales (las vistas no tienen una PK autoincrementable local)
    public $incrementing = false;

    // 4. Bloqueamos cualquier intento accidental de escritura desde tu App
    protected $guarded = ['*'];

    /**
     * Opcional: Oracle suele devolver los nombres de las columnas en MAYÚSCULAS.
     * Si quieres poder escribir $cliente->nombre en vez de $cliente->NOMBRE,
     * este pequeño truco convierte los atributos a minúsculas al leerlos.
     */
    public function getAttribute($key)
    {
        // Intenta buscar la clave original
        if (\array_key_exists($key, $this->attributes)) {
            return parent::getAttribute($key);
        }
        
        // Si no la encuentra, busca su versión en mayúsculas (Formato Oracle)
        $upperKey = strtoupper($key);
        if (\array_key_exists($upperKey, $this->attributes)) {
            return $this->attributes[$upperKey];
        }

        return parent::getAttribute($key);
    }
}