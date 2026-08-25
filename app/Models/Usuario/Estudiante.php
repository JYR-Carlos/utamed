<?php

namespace App\Models\Usuario;

use App\DTOs\External\AlumnoIntranetData;
use App\Models\Administrativo\Carrera;
use App\Models\Base\Usuario\BaseEstudiante;
use App\Models\External\VwAlumno;
use App\Models\Usuario\Usuario;
use App\Support\Rut;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Modelo Estudiante
 * 
 * Extiende de BaseEstudiante (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Estudiante extends BaseEstudiante
{
    /**
     * Factory Method: Crea (o recupera) un Usuario y Estudiante en UTAMED a partir de datos de la Intranet.
     * 
     * Aplica automáticamente todas las transformaciones pertinentes:
     * - Normalización y formato del RUT (vía Support\Rut).
     * - Limpieza de espacios múltiples y conversión a mayúsculas consistentes.
     * - Manejo seguro de segundo apellido opcional/nulo.
     * - Generación de contraseña temporal por defecto (hash del RUT).
     * - Persistencia atómica en transacción de base de datos.
     *
     * @param AlumnoIntranetData|VwAlumno|array $datos Datos del alumno desde la Intranet
     * @param Carrera $carrera Carrera a la que se asocia el estudiante
     * @param int|null $agnoIngreso Año de ingreso opcional (por defecto año actual)
     * @return Estudiante
     */
    public static function createFromIntranet(
        AlumnoIntranetData|VwAlumno|array $datos,
        Carrera $carrera,
        ?int $agnoIngreso = null
    ): Estudiante {
        // 1. Extraer los campos según el tipo de datos proporcionado
        if ($datos instanceof AlumnoIntranetData) {
            $rut = $datos->alum_rut;
            $dv = $datos->alum_digito;
            $nombre = $datos->alum_nombre;
            $paterno = $datos->alum_apellido_pat;
            $materno = $datos->alum_apellido_mat;
        } elseif ($datos instanceof VwAlumno) {
            $rut = $datos->ALUM_RUT;
            $dv = $datos->ALUM_DIGITO;
            $nombre = $datos->ALUM_NOMBRE;
            $paterno = $datos->ALUM_APELLIDO_PAT;
            $materno = $datos->ALUM_APELLIDO_MAT;
        } else {
            $rut = $datos['alum_rut'] ?? $datos['ALUM_RUT'] ?? $datos['rut'] ?? null;
            $dv = $datos['alum_digito'] ?? $datos['ALUM_DIGITO'] ?? $datos['dv'] ?? null;
            $nombre = $datos['alum_nombre'] ?? $datos['ALUM_NOMBRE'] ?? $datos['nombre'] ?? '';
            $paterno = $datos['alum_apellido_pat'] ?? $datos['ALUM_APELLIDO_PAT'] ?? $datos['apellido1'] ?? '';
            $materno = $datos['alum_apellido_mat'] ?? $datos['ALUM_APELLIDO_MAT'] ?? $datos['apellido2'] ?? null;
        }

        if (empty($rut)) {
            throw new \InvalidArgumentException('El RUT del alumno es requerido para crear un Estudiante desde la Intranet.');
        }

        // 2. Transformaciones y normalización de casos de borde
        $rutCuerpo = (int)$rut;
        $dvLimpio = !is_null($dv) ? strtoupper(trim((string)$dv)) : null;
        $rutFormateado = Rut::desdePartes($rutCuerpo, $dvLimpio) ?? "{$rutCuerpo}-{$dvLimpio}";

        $nombreLimpio = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string)$nombre)));
        $paternoLimpio = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string)$paterno)));
        $maternoLimpio = (!is_null($materno) && trim((string)$materno) !== '')
            ? mb_strtoupper(preg_replace('/\s+/', ' ', trim((string)$materno)))
            : null;

        $username = (string)$rutCuerpo;

        // 3. Persistencia atómica
        return DB::transaction(function () use ($username, $rutFormateado, $nombreLimpio, $paternoLimpio, $maternoLimpio, $carrera, $agnoIngreso) {
            $usuario = Usuario::firstOrCreate(
                ['username' => $username],
                [
                    'passhash'    => Hash::make($username),
                    'rut'         => $rutFormateado,
                    'nombre1'     => $nombreLimpio,
                    'nombre2'     => null,
                    'apellido1'   => $paternoLimpio,
                    'apellido2'   => $maternoLimpio,
                    'email'       => null,
                    'esta_activo' => true,
                ]
            );

            return static::firstOrCreate(
                [
                    'id_usuario' => $usuario->id_usuario,
                    'id_carrera' => $carrera->id_carrera,
                ],
                [
                    'agno_ingreso' => $agnoIngreso ?? (int)now()->year,
                ]
            );
        });
    }

    /**
     * Obtiene el nombre abreviado del estudiante.
     *
     * @example "JPérez"
     * 
     * @return string Nombre abreviado formado por la inicial del nombre y el apellido completo
     */
    public function nombreAbreviado(): string
    {
        /** @var Usuario $usuario */
        $usuario = $this->usuario;

        return $usuario->nombreAbreviado();
    }
}
