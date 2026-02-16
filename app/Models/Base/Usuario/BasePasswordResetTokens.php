<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePasswordResetTokens extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'password_reset_tokens';
    protected $primaryKey = 'email';
    public $incrementing = true;

    protected $fillable = [
        'token',
        'created_at'
    ];


}
