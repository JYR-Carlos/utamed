<?php

namespace App\Services\Sso;

use App\Models\Usuario\Usuario;
use App\Support\JwtRs256;
use App\Support\Rut;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Puente de sesión hacia SGEQ (Sistema de Registro y Préstamos de equipos).
 *
 * SGEQ es otra aplicación, con otra base de datos y otro modelo de usuarios. No
 * comparte sesión con UTAmed ni puede hacerlo: lo que se comparte es la
 * *identidad*. UTAmed firma un token de vida muy corta con el RUT, el correo y el
 * rol que le corresponde a la persona allá; SGEQ lo verifica con la clave pública,
 * y con eso crea o reconoce la cuenta y abre su propia sesión.
 *
 * QUIÉN PUEDE PASAR — la decisión se toma acá, no en SGEQ, porque acá está el dato:
 *
 *   - Administrador del sistema  → entra como ADMIN.
 *   - Estudiante de una carrera habilitada (Diseño Multimedia) → entra como ALUMNO.
 *   - Cualquier otro caso (docentes, ayudantes, jefaturas, estudiantes de otras
 *     carreras, usuarios sin rol, cuentas desactivadas) → no se firma nada.
 *
 * Docentes y ayudantes no usan SGEQ, y SUPER_USUARIO —el tercer rol de SGEQ— se
 * administra allá: el SSO no lo asigna ni lo quita.
 *
 * El token NO es la sesión de SGEQ: es el saludo inicial. Vive unos segundos y se
 * usa una sola vez. Cerrar sesión en UTAmed no cierra la de SGEQ; son sistemas
 * distintos y así está aceptado.
 */
class SgeqSsoService
{
    /** Nombres tal como están en la tabla `rol` de SGEQ. */
    public const ROL_ADMIN = 'ADMIN';
    public const ROL_ALUMNO = 'ALUMNO';

    /**
     * Rol con el que este usuario entraría a SGEQ, o null si no debe entrar.
     */
    public function resolverRol(Usuario $usuario): ?string
    {
        // Una cuenta desactivada no puede iniciar sesión en UTAmed; que tampoco
        // pueda abrir SGEQ si llegara con una sesión todavía viva.
        if (!$usuario->esta_activo) {
            return null;
        }

        if ($usuario->esAdministradorGlobal()) {
            return self::ROL_ADMIN;
        }

        $carrera = $usuario->estudiante?->id_carrera;

        if ($carrera !== null && in_array((int) $carrera, $this->carrerasHabilitadas(), true)) {
            return self::ROL_ALUMNO;
        }

        return null;
    }

    /**
     * Arma y firma el token para este usuario.
     *
     * @throws RuntimeException Si falta configuración o la clave no se puede leer.
     */
    public function emitirToken(Usuario $usuario, string $rol): string
    {
        // El mutador de Usuario ya guarda el RUT en formato canónico, pero se
        // normaliza otra vez antes de firmarlo: SGEQ busca a la persona por este
        // valor, y un RUT con puntos no encuentra a nadie y crea un duplicado.
        // Es la última oportunidad de que salga bien escrito.
        $rut = Rut::normalizar($usuario->rut);

        if ($rut === null || trim($rut) === '') {
            throw new RuntimeException("El usuario #{$usuario->id_usuario} no tiene RUT; SGEQ identifica a las personas por RUT.");
        }

        $ahora = now();
        $vigencia = max(15, (int) config('services.sgeq.ttl', 60));

        $claims = [
            'iss' => $this->requerirConfig('issuer'),
            'aud' => $this->requerirConfig('audience'),
            'iat' => $ahora->timestamp,
            'exp' => $ahora->copy()->addSeconds($vigencia)->timestamp,

            // `jti` es lo que le permite a SGEQ rechazar un token reutilizado: si el
            // enlace queda en el historial del navegador, el segundo intento rebota.
            'jti' => (string) Str::uuid(),

            // SGEQ normaliza igual antes de comparar, porque su columna arrastra
            // RUTs escritos con puntos de antes de esta integración.
            'sub' => $rut,
            'rut' => $rut,

            'email' => $usuario->email,
            'nombre1' => $usuario->nombre1,
            'apellido1' => $usuario->apellido1,
            'apellido2' => $usuario->apellido2,

            'rol' => $rol,
            'carrera' => $usuario->estudiante?->carrera?->nombre,
        ];

        return JwtRs256::firmar($claims, $this->leerClavePrivada());
    }

    /**
     * URL de SGEQ que recibe el token y arranca la sesión allá.
     */
    public function urlDeConsumo(string $token): string
    {
        $base = rtrim($this->requerirConfig('url'), '/');

        return $base . '/auth/sso/consume?' . http_build_query(['token' => $token]);
    }

    /**
     * IDs de carrera cuyos estudiantes pueden abrir SGEQ.
     *
     * Se configuran por ID y no por nombre a propósito: el nombre lleva tilde y se
     * puede editar desde el panel de administración, así que un cambio cosmético
     * dejaría a toda la carrera fuera sin que nadie relacione una cosa con la otra.
     *
     * Sin configuración la lista queda vacía y ningún estudiante entra. Es el lado
     * seguro del error: preferible que el botón no funcione a que se abra para
     * carreras que no corresponden.
     *
     * @return list<int>
     */
    protected function carrerasHabilitadas(): array
    {
        $configuradas = config('services.sgeq.carreras', []);

        return array_values(array_filter(array_map('intval', (array) $configuradas)));
    }

    protected function leerClavePrivada(): string
    {
        $ruta = (string) config('services.sgeq.private_key_path', '');

        if ($ruta === '' || !is_file($ruta)) {
            throw new RuntimeException("No se encontró la clave privada del SSO de SGEQ en «{$ruta}». Revisa SGEQ_SSO_PRIVATE_KEY_PATH.");
        }

        $contenido = file_get_contents($ruta);

        if ($contenido === false || trim($contenido) === '') {
            throw new RuntimeException("La clave privada del SSO de SGEQ («{$ruta}») está vacía o no se puede leer.");
        }

        return $contenido;
    }

    protected function requerirConfig(string $clave): string
    {
        $valor = (string) config("services.sgeq.{$clave}", '');

        if (trim($valor) === '') {
            throw new RuntimeException("Falta configurar services.sgeq.{$clave} para el SSO hacia SGEQ.");
        }

        return $valor;
    }
}
