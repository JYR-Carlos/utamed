<?php

namespace App\Models\Usuario;

use App\DTOs\External\AlumnoIntranetData;
use App\Models\Administrativo\Carrera;
use App\Models\Base\Usuario\BaseEstudiante;
use App\Models\External\VwAlumno;
use App\Models\Usuario\Usuario;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Modelo Estudiante
 * 
 * Extiende de BaseEstudiante (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Estudiante extends BaseEstudiante
{
    use HasFactory;

    /**
     * Redes que el alumno puede declarar en su perfil (T10). X se quitó por
     * decisión del equipo (OBS-20): sólo redes útiles para lo académico y el
     * portafolio. Un enlace a X guardado antes ya no se muestra y se descarta
     * la próxima vez que el alumno guarde su contacto.
     */
    public const REDES_SOCIALES = ['youtube', 'instagram', 'linkedin'];

    /**
     * Contacto personal del alumno: sólo lo ven él y sus docentes. Va oculto
     * al serializar para que ninguna vista que devuelva el modelo entero (un
     * grupo, un listado de curso, una entrega) se lo muestre a un compañero.
     * Quien deba verlo lo pide explícito con {@see contacto()}.
     *
     * No está en `$fillable`: sólo lo escribe Student\PerfilController::update.
     *
     * @var array<int, string>
     */
    protected $hidden = ['correo_personal', 'celular', 'redes_sociales'];

    protected function casts(): array
    {
        return [
            'redes_sociales' => 'array',
        ];
    }

    /**
     * Datos de contacto en la forma que consumen el perfil del alumno y la
     * ficha docente.
     *
     * @return array{correo_personal: ?string, celular: ?string, redes_sociales: array<string, ?string>}
     */
    public function contacto(): array
    {
        $redes = is_array($this->redes_sociales) ? $this->redes_sociales : [];

        return [
            'correo_personal' => $this->correo_personal,
            'celular' => $this->celular,
            'redes_sociales' => collect(self::REDES_SOCIALES)
                ->mapWithKeys(fn (string $red) => [$red => $redes[$red] ?? null])
                ->all(),
        ];
    }

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
            $email = $datos->alum_email ?? $datos->email ?? null;
        } elseif ($datos instanceof VwAlumno) {
            $rut = $datos->ALUM_RUT;
            $dv = $datos->ALUM_DIGITO;
            $nombre = $datos->ALUM_NOMBRE;
            $paterno = $datos->ALUM_APELLIDO_PAT;
            $materno = $datos->ALUM_APELLIDO_MAT;
            $email = $datos->alum_email ?? $datos->ALUM_EMAIL ?? $datos->ALUM_CORREO ?? null;
        } else {
            $rut = $datos['alum_rut'] ?? $datos['ALUM_RUT'] ?? $datos['rut'] ?? null;
            $dv = $datos['alum_digito'] ?? $datos['ALUM_DIGITO'] ?? $datos['dv'] ?? null;
            $nombre = $datos['alum_nombre'] ?? $datos['ALUM_NOMBRE'] ?? $datos['nombre'] ?? '';
            $paterno = $datos['alum_apellido_pat'] ?? $datos['ALUM_APELLIDO_PAT'] ?? $datos['apellido1'] ?? '';
            $materno = $datos['alum_apellido_mat'] ?? $datos['ALUM_APELLIDO_MAT'] ?? $datos['apellido2'] ?? null;
            $email = $datos['alum_email'] ?? $datos['ALUM_EMAIL'] ?? $datos['alum_correo'] ?? $datos['ALUM_CORREO'] ?? $datos['email'] ?? null;
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

        // Normalización y validación del correo electrónico
        $emailLimpio = (!is_null($email) && trim((string)$email) !== '')
            ? mb_strtolower(trim((string)$email))
            : null;

        if ($emailLimpio !== null) {
            if (!filter_var($emailLimpio, FILTER_VALIDATE_EMAIL)) {
                Log::warning("[Estudiante::createFromIntranet] Email inválido descartado para RUT {$username}: '{$emailLimpio}'");
                $emailLimpio = null;
            } elseif (Usuario::where('email', $emailLimpio)->where('username', '!=', $username)->exists()) {
                Log::warning("[Estudiante::createFromIntranet] Colisión de email descartada para RUT {$username}: '{$emailLimpio}' ya pertenece a otro usuario.");
                $emailLimpio = null;
            }
        }

        // 3. Persistencia atómica
        return DB::transaction(function () use ($username, $rutFormateado, $nombreLimpio, $paternoLimpio, $maternoLimpio, $emailLimpio, $carrera, $agnoIngreso) {
            $usuario = Usuario::where('username', $username)->first();

            if (!$usuario) {
                try {
                    $usuario = Usuario::create([
                        'username'    => $username,
                        'passhash'    => Hash::make($username),
                        'rut'         => $rutFormateado,
                        'nombre1'     => $nombreLimpio,
                        'nombre2'     => null,
                        'apellido1'   => $paternoLimpio,
                        'apellido2'   => $maternoLimpio,
                        'email'       => $emailLimpio,
                        'esta_activo' => true,
                    ]);
                } catch (QueryException $e) {
                    if ($emailLimpio !== null && ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uq_email'))) {
                        Log::warning("[Estudiante::createFromIntranet] Colisión concurrente de uq_email para RUT {$username}: '{$emailLimpio}'. Creando usuario con email null.");
                        $usuario = Usuario::create([
                            'username'    => $username,
                            'passhash'    => Hash::make($username),
                            'rut'         => $rutFormateado,
                            'nombre1'     => $nombreLimpio,
                            'nombre2'     => null,
                            'apellido1'   => $paternoLimpio,
                            'apellido2'   => $maternoLimpio,
                            'email'       => null,
                            'esta_activo' => true,
                        ]);
                    } else {
                        throw $e;
                    }
                }
            } else {
                // Política de Autoridad de Intranet:
                // Actualizar nombres y apellidos si difieren de la Intranet
                $usuario->rut = $rutFormateado;
                $usuario->nombre1 = $nombreLimpio;
                $usuario->apellido1 = $paternoLimpio;
                $usuario->apellido2 = $maternoLimpio;

                // Si la Intranet provee una casilla válida y es distinta a la actual, sobreescribe incondicionalmente.
                // Si la Intranet no provee correo (null), NO se borra el que ya tenía.
                if ($emailLimpio !== null && $usuario->email !== $emailLimpio) {
                    $usuario->email = $emailLimpio;
                }

                try {
                    $usuario->save();
                } catch (QueryException $e) {
                    if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uq_email')) {
                        Log::warning("[Estudiante::createFromIntranet] Colisión uq_email al actualizar RUT {$username}: '{$emailLimpio}'. Se mantiene email previo.");
                        $usuario->refresh();
                        $usuario->rut = $rutFormateado;
                        $usuario->nombre1 = $nombreLimpio;
                        $usuario->apellido1 = $paternoLimpio;
                        $usuario->apellido2 = $maternoLimpio;
                        $usuario->save();
                    } else {
                        throw $e;
                    }
                }
            }

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
