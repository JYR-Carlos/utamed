<?php

namespace App\Services;

use App\DTOs\External\AlumnoIntranetData;
use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EstudianteService
{
    /**
     * Busca un estudiante localmente por su RUT.
     */
    public function buscarPorRut(int|string $rut): ?Estudiante
    {
        $rutStr = (string)$rut;
        $cleanRut = preg_replace('/[.\-]/', '', $rutStr);

        $usuario = Usuario::where('rut', $rutStr)
            ->orWhere('rut', $cleanRut)
            ->orWhere('username', $rutStr)
            ->orWhere('username', $cleanRut)
            ->orWhere(DB::raw("REPLACE(REPLACE(rut, '.', ''), '-', '')"), $cleanRut)
            ->first();

        if (!$usuario) {
            return null;
        }

        return Estudiante::where('id_usuario', $usuario->id_usuario)->first();
    }

    /**
     * Formatea el RUT al estándar de la aplicación: BODY-DV.
     */
    public function formatRut(int|string $rut, ?string $dv = null): string
    {
        $cleanRut = preg_replace('/[.\-]/', '', (string)$rut);
        if ($dv !== null && $dv !== '') {
            return "{$cleanRut}-" . strtoupper(trim($dv));
        }

        if (preg_match('/^\d{7,8}[0-9kK]$/', $cleanRut)) {
            $body = substr($cleanRut, 0, -1);
            $dvChar = strtoupper(substr($cleanRut, -1));
            return "{$body}-{$dvChar}";
        }

        return $cleanRut;
    }

    /**
     * Crea un usuario y estudiante en UTAMED a partir de los datos de la Intranet.
     */
    public function crearDesdeIntranet(AlumnoIntranetData $datos, Carrera $carrera): Estudiante
    {
        return DB::transaction(function () use ($datos, $carrera) {
            $rutFormateado = $this->formatRut($datos->alum_rut, $datos->alum_digito);
            $username = (string)$datos->alum_rut;

            $usuario = Usuario::create([
                'username'    => $username,
                'passhash'    => Hash::make($username),
                'rut'         => $rutFormateado,
                'nombre1'     => $datos->alum_nombre,
                'nombre2'     => null,
                'apellido1'   => $datos->alum_apellido_pat,
                'apellido2'   => $datos->alum_apellido_mat,
                'email'       => null,
                'esta_activo' => true,
            ]);

            return Estudiante::create([
                'id_usuario'   => $usuario->id_usuario,
                'id_carrera'   => $carrera->id_carrera,
                'agno_ingreso' => (int)now()->year,
            ]);
        });
    }

    /**
     * Busca localmente y, si no existe, consulta la intranet para crearlo.
     */
    public function obtenerOCrearDesdeIntranet(int $alumRut, Carrera $carrera): ?Estudiante
    {
        $estudiante = $this->buscarPorRut($alumRut);
        if ($estudiante) {
            return $estudiante;
        }

        /** @var \App\DTOs\External\AlumnoIntranetData|null $datosIntranet */
        $oracleService = app('OracleDataService');
        $datosIntranet = $oracleService->traer_alumno($alumRut);

        if (!$datosIntranet) {
            Log::warning('No se encontraron datos del alumno en la Intranet', ['alum_rut' => $alumRut]);
            return null;
        }

        return $this->crearDesdeIntranet($datosIntranet, $carrera);
    }

    /**
     * Asigna el rol 'Estudiante' al estudiante en el contexto especificado (curso o componente).
     */
    public function asignarRolEnContexto(Estudiante $estudiante, int $idContexto): void
    {
        try {
            if (!$estudiante->id_usuario || !$idContexto) {
                return;
            }

            $actorId = Auth::id() ?? $estudiante->id_usuario;
            $rol = Rol::firstOrCreate(
                ['nombre' => 'Estudiante'],
                ['creado_por' => $actorId]
            );

            $existente = UsuarioRolAsignacion::where('id_usuario', $estudiante->id_usuario)
                ->where('id_contexto', $idContexto)
                ->where('id_rol', $rol->id_rol)
                ->first();

            if ($existente) {
                if (!$existente->esta_activo || $existente->fue_eliminado) {
                    $existente->update([
                        'esta_activo'              => true,
                        'fue_eliminado'            => false,
                        'fecha_fin_real'           => null,
                        'eliminado_por'            => null,
                        'fecha_inicio_planificada' => Carbon::now(),
                        'fecha_fin_planificada'    => Carbon::now()->addYears(100),
                        'asignado_por'             => $actorId,
                    ]);
                }
            } else {
                $now = Carbon::now();
                UsuarioRolAsignacion::create([
                    'id_usuario'               => $estudiante->id_usuario,
                    'id_rol'                   => $rol->id_rol,
                    'id_contexto'              => $idContexto,
                    'asignado_por'             => $actorId,
                    'fecha_inicio_planificada' => $now,
                    'fecha_fin_planificada'    => $now->copy()->addYears(100),
                    'fecha_fin_real'           => null,
                    'fue_eliminado'            => false,
                    'esta_activo'              => true,
                    'creado_por'               => $actorId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error asignando rol Estudiante en contexto: ' . $e->getMessage(), [
                'id_estudiante' => $estudiante->id_estudiante,
                'id_contexto'   => $idContexto,
            ]);
        }
    }
}
