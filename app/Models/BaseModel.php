<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /**
     * Sobrescribe el método create() para manejar claves compuestas
     * En PostgreSQL con GENERATED ALWAYS AS IDENTITY, recupera todos los valores generados
     */
    public static function create(array $attributes = [])
    {
        $instance = static::query()->create($attributes);
        
        // Si es una clave compuesta y algún valor está null, refresca desde la BD
        if (is_array($instance->getKeyName())) {
            /** @var array<string> $keyNames */
            $keyNames = $instance->getKeyName();
            $keys = array_combine($keyNames, (array)$instance->getKey());
            
            // Si alguna parte de la clave está null, fetch el registro completo
            if (in_array(null, $keys, true)) {
                // Construir where dinámico con solo los valores no-null
                $query = static::query();
                foreach ($keyNames as $keyName) {
                    if ($instance->$keyName !== null) {
                        $query->where($keyName, $instance->$keyName);
                    }
                }
                // Usar orderBy con el primer campo de clave para obtener el más reciente
                $firstKey = $keyNames[0];
                $fresh = $query->orderBy($firstKey, 'desc')->first();
                
                if ($fresh) {
                    // Actualiza los atributos del modelo actual con los valores frescos
                    foreach ($keyNames as $keyName) {
                        $instance->setAttribute($keyName, $fresh->$keyName);
                    }
                    $instance->syncOriginal();
                }
            }
        }
        
        return $instance;
    }
}