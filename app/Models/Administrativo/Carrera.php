<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseCarrera;
use App\Models\Usuario\UsuarioRolAsignacion;

/**
 * Modelo Carrera
 *
 * Extiende de BaseCarrera (auto-generado).
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Carrera extends BaseCarrera
{
    /**
     * Devuelve las asignaciones RBAC activas cuyo rol es 'Jefe de Carrera'
     * y cuyo contexto es de tipo 'carrera'.
     *
     * Ambos lados (carrera y usuario_rol_asignacion) comparten id_contexto como
     * referencia al mismo registro de usuario.contexto, por lo que la relación
     * se expresa como hasMany a través de esa clave foránea compartida.
     *
     * Usado por withCount('jefesDeCarreraActivos as has_director') en el controlador.
     */
    public function jefesDeCarreraActivos()
    {
        return $this->hasMany(UsuarioRolAsignacion::class, 'id_contexto', 'id_contexto')
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->where('nombre', 'Jefe de Carrera'))
            ->whereHas('contexto.tipoContexto', fn($q) => $q->where('categoria', 'carrera'));
    }
}