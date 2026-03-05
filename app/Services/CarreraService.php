<?php

namespace App\Services;

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CarreraService
 *
 * Encapsula la lógica de negocio para crear, actualizar y eliminar
 * carreras, incluyendo la gestión automática del Contexto RBAC
 * asociado y el registro de auditoría.
 */
class CarreraService
{
    /**
     * Crea una nueva carrera junto con su Contexto de permisos RBAC.
     *
     * @param  array{nombre: string, jornada?: string, sede?: string, modalidad?: string, id_departamento: int, id_facultad?: int}  $data
     * @param  Usuario  $actor  Usuario que realiza la acción.
     * @return Carrera
     */
    public function create(array $data, Usuario $actor): Carrera
    {
        return DB::transaction(function () use ($data, $actor) {
            $contexto = Contexto::firstOrCreate(
                ['contexto_display' => 'Carrera: ' . $data['nombre']],
                ['descripcion' => 'Contexto de permisos RBAC para la carrera ' . $data['nombre']]
            );

            $carrera = new Carrera();
            $carrera->nombre         = $data['nombre'];
            $carrera->jornada        = $data['jornada']  ?? null;
            $carrera->sede           = $data['sede']     ?? null;
            $carrera->modalidad      = $data['modalidad'] ?? null;
            $carrera->id_departamento = $data['id_departamento'];
            $carrera->id_contexto    = $contexto->id_contexto;
            $carrera->save();

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
