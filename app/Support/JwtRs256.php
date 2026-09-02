<?php

namespace App\Support;

use RuntimeException;

/**
 * Firma de JWT con RS256 (RSA + SHA-256).
 *
 * POR QUÉ EXISTE: UTAmed le entrega la identidad del usuario a SGEQ (el sistema
 * de préstamo de equipos, otro dominio y otra base de datos) mediante un token
 * firmado. Firmar con clave privada —y no con un secreto compartido— permite que
 * SGEQ verifique con la clave pública sin poder emitir tokens a nombre de UTAmed.
 *
 * AQUÍ SOLO SE FIRMA, NUNCA SE VERIFICA. La verificación es la mitad delicada del
 * JWT (confusión de algoritmo, `alg: none`, claves mal tipadas) y vive en SGEQ,
 * que la resuelve con firebase/php-jwt. Firmar, en cambio, es armar dos JSON en
 * base64url y pasarlos por `openssl_sign`: no hay decisión que un atacante pueda
 * torcer, por eso no se arrastra una dependencia para esto.
 *
 * Si alguna vez hiciera falta *verificar* un JWT dentro de UTAmed, no se extiende
 * esta clase: se instala una librería con revisión de seguridad.
 */
final class JwtRs256
{
    /**
     * Devuelve el JWT compacto `header.payload.firma`.
     *
     * @param  array<string, mixed>  $claims        Cuerpo del token, ya completo.
     * @param  string                $clavePrivada  Contenido PEM de la clave privada.
     *
     * @throws RuntimeException Si la clave no se puede leer o la firma falla.
     */
    public static function firmar(array $claims, string $clavePrivada): string
    {
        $entrada = self::aBase64Url(self::aJson(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.'
            . self::aBase64Url(self::aJson($claims));

        $clave = openssl_pkey_get_private($clavePrivada);

        if ($clave === false) {
            throw new RuntimeException(
                'La clave privada del SSO no es un PEM válido: ' . (openssl_error_string() ?: 'sin detalle')
            );
        }

        $firma = '';

        if (openssl_sign($entrada, $firma, $clave, OPENSSL_ALGO_SHA256) === false) {
            throw new RuntimeException(
                'No se pudo firmar el token SSO: ' . (openssl_error_string() ?: 'sin detalle')
            );
        }

        return $entrada . '.' . self::aBase64Url($firma);
    }

    /**
     * JSON sin escapes decorativos: los `/` y las tildes escapadas alargan el
     * token sin aportar nada, y el receptor los decodifica igual.
     *
     * @param  array<string, mixed>  $valor
     */
    private static function aJson(array $valor): string
    {
        $json = json_encode($valor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el token SSO: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * base64url del RFC 7515: alfabeto seguro para URLs y sin relleno, porque el
     * token viaja en la query string del redirect hacia SGEQ.
     */
    private static function aBase64Url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
