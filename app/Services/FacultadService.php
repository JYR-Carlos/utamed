<?php

namespace App\Services;

use App\Models\Administrativo\Facultad;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FacultadService
 *
 * Encapsula la lógica de negocio para crear, actualizar y eliminar
 * facultades, incluyendo la gestión del Contexto asociado y el
 * registro de auditoría.
 */
class FacultadService
{
    /**
     * Crea una nueva facultad junto con su Contexto de permisos.
     *
     * @param  array{nombre: string}  $data
     * @param  Usuario                $actor  Usuario que realiza la acción.
     * @return Facultad
     */
    public function create(array $data, Usuario $actor): Facultad
    {
        return DB::transaction(function () use ($data, $actor) {
            $contexto = Contexto::firstOrCreate(
                ['contexto_display' => 'Facultad: ' . $data['nombre']],
                ['descripcion' => 'Contexto para la facultad ' . $data['nombre']]
            );

            $facultad = new Facultad();
            $facultad->nombre = $data['nombre'];
            $facultad->id_contexto = $contexto->id_contexto;
            $facultad->save();

            Log::info('Facultad creada', [
                'id_facultad' => $facultad->id_facultad,
                'nombre'      => $facultad->nombre,
                'id_contexto' => $facultad->id_contexto,
                'actor_id'    => $actor->id_usuario,
                'actor'       => $actor->email ?? $actor->nombre,
            ]);

            return $facultad;
        });
    }

    /**
     * Actualiza los datos de una facultad existente.
     *
     * @param  Facultad               $facultad
     * @param  array{nombre: string}  $data
     * @param  Usuario                $actor
     * @return Facultad
     */
    public function update(Facultad $facultad, array $data, Usuario $actor): Facultad
    {
        return DB::transaction(function () use ($facultad, $data, $actor) {
            $nombreAnterior = $facultad->nombre;

            $facultad->nombre = $data['nombre'];
            $facultad->save();

            Log::info('Facultad actualizada', [
                'id_facultad'     => $facultad->id_facultad,
                'nombre_anterior' => $nombreAnterior,
                'nombre_nuevo'    => $facultad->nombre,
                'actor_id'        => $actor->id_usuario,
                'actor'           => $actor->email ?? $actor->nombre,
            ]);

            return $facultad;
        });
    }

    /**
     * Elimina (soft-delete) una facultad.
     *
     * @param  Facultad  $facultad
     * @param  Usuario   $actor
     * @return void
     *
     * @throws \Exception  Si la facultad tiene departamentos activos.
     */
    public function delete(Facultad $facultad, Usuario $actor): void
    {
        DB::transaction(function () use ($facultad, $actor) {
            $facultad->delete();

            Log::info('Facultad eliminada', [
                'id_facultad' => $facultad->id_facultad,
                'nombre'      => $facultad->nombre,
                'actor_id'    => $actor->id_usuario,
                'actor'       => $actor->email ?? $actor->nombre,
            ]);
        });
    }
}
