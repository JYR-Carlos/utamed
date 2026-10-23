<?php

namespace App\Models\Curso;

use App\Models\BaseModel;
use App\Models\Usuario\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo IntranetSincronizacionLog
 *
 * Registra snapshots atómicos y auditoría de cada sincronización ejecutada
 * contra la Intranet (componentes e inscripciones), permitiendo observabilidad
 * y rollback/reversión segura de los cambios aplicados.
 */
class IntranetSincronizacionLog extends BaseModel
{
    use HasFactory;

    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'curso.intranet_sincronizacion_log';
    protected $primaryKey = 'id_log';
    public $incrementing = true;

    protected $fillable = [
        'id_curso',
        'id_usuario',
        'fecha_sincronizacion',
        'tipo_sincronizacion',
        'estado',
        'snapshot_cambios',
        'fecha_reversion',
        'id_usuario_reversion',
        'motivo_reversion',
    ];

    protected $casts = [
        'fecha_sincronizacion' => 'datetime',
        'fecha_reversion'      => 'datetime',
        'snapshot_cambios'     => 'array',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'id_curso');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function usuarioReversion(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_reversion', 'id_usuario');
    }

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
