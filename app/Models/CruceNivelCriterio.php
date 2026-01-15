<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CruceNivelCriterio extends Model
{
    protected $table = 'utamed.cruce_nivel_criterio';
    
    // Composite primary key
    protected $primaryKey = ['id_rubrica', 'id_nivel', 'id_criterio'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_rubrica',
        'id_nivel',
        'id_criterio',
        'descripcion',
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
     * Relación: Un cruce pertenece a un criterio
     */
    public function criterio()
    {
        return $this->belongsTo(CriterioRubrica::class, 'id_rubrica', 'id_rubrica')
            ->where('id_criterio', $this->id_criterio);
    }

    /**
     * Relación: Un cruce pertenece a un nivel
     */
    public function nivel()
    {
        return $this->belongsTo(NivelRubrica::class, 'id_rubrica', 'id_rubrica')
            ->where('id_nivel', $this->id_nivel);
    }
}
