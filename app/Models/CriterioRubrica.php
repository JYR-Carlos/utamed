<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriterioRubrica extends Model
{
    protected $table = 'utamed.criterio_rubrica';
    
    // Composite primary key
    protected $primaryKey = ['id_rubrica', 'id_criterio'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_rubrica',
        'id_criterio',
        'definicion',
        'ponderacion',
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
     * Relación: Un criterio pertenece a una rúbrica
     */
    public function rubrica()
    {
        return $this->belongsTo(RubricaAnalitica::class, 'id_rubrica', 'id_rubrica');
    }

    /**
     * Relación: Un criterio tiene muchos cruces con niveles
     */
    public function cruces()
    {
        return $this->hasMany(CruceNivelCriterio::class, 'id_rubrica', 'id_rubrica')
            ->where('id_criterio', $this->id_criterio);
    }
}
