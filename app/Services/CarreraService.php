<?php

namespace App\Services;

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CarreraService
 *
 * Encapsula la lógica de negocio para crear, actualizar y eliminar
 * carreras y el registro de auditoría. El Contexto RBAC lo crea el
 * trigger de la BD al insertar.
 */
class CarreraService
{
    /**
     * Crea una nueva carrera (el trigger le crea su Contexto RBAC).
     *
     * @param  array{nombre: string, jornada?: string, sede?: string, modalidad?: string, id_departamento: int, id_facultad?: int}  $data
     * @param  Usuario  $actor  Usuario que realiza la acción.
     * @return Carrera
     */
    public function create(array $data, Usuario $actor): Carrera
    {
        return DB::transaction(function () use ($data, $actor) {
            // id_contexto lo asigna el trigger tr_carrera_pre_insert (con tipo y
            // padre en el departamento); refresh() trae ese valor al modelo.
            $carrera = new Carrera();
            $carrera->nombre         = $data['nombre'];
            $carrera->jornada        = $data['jornada']  ?? null;
            $carrera->sede           = $data['sede']     ?? null;
            $carrera->modalidad      = $data['modalidad'] ?? null;
            $carrera->id_departamento = $data['id_departamento'];
            $carrera->save();
            $carrera->refresh();

            Log::info('Carrera creada con contexto RBAC', [
                'id_carrera'      => $carrera->id_carrera,
                'nombre'          => $carrera->nombre,
                'id_departamento' => $carrera->id_departamento,
                'id_contexto'     => $carrera->id_contexto,
                'actor_id'        => $actor->id_usuario,
                'actor'           => $actor->email ?? $actor->username,
            ]);

            return $carrera;
        });
    }
}
