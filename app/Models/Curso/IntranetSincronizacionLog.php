<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseIntranetSincronizacionLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modelo IntranetSincronizacionLog
 *
 * Extiende de BaseIntranetSincronizacionLog (auto-generado).
 * Registra snapshots atómicos y auditoría de cada sincronización ejecutada
 * contra la Intranet (componentes e inscripciones), permitiendo observabilidad
 * y rollback/reversión segura de los cambios aplicados.
 */
class IntranetSincronizacionLog extends BaseIntranetSincronizacionLog
{
    use HasFactory;

    protected $casts = [
        'fecha_sincronizacion' => 'datetime',
        'fecha_reversion'      => 'datetime',
        'snapshot_cambios'     => 'array',
    ];

    public function scopeAplicada($query)
    {
        return $query->where('estado', 'APLICADA');
    }

    public function scopeRevertida($query)
    {
        return $query->where('estado', 'REVERTIDA');
    }

    public function esReversible(): bool
    {
        return $this->estado === 'APLICADA';
    }
}
