<?php

namespace App\Support;

/**
 * Formato único con el que se guarda el RUT: cuerpo sin puntos, guion y dígito
 * verificador en mayúscula — "12345678-9", "9123456-K".
 *
 * POR QUÉ EXISTE: `usuario.usuario.rut` es texto con UNIQUE, y el UNIQUE compara
 * cadenas. "12.345.678-9" y "12345678-9" son la misma persona y dos valores
 * distintos, así que la restricción no los ve iguales y la persona entra dos
 * veces. La única defensa es que a la columna llegue siempre la misma escritura.
 *
 * Por eso todo lo que escribe un RUT lo pasa por aquí: las peticiones del
 * administrador, la importación masiva, la sincronización con la intranet y —como
 * última red— el mutador de {@see \App\Models\Usuario\Usuario}.
 *
 * NORMALIZAR NO ES VALIDAR: si el valor no tiene forma de RUT se devuelve tal
 * cual (recortado) para que la validación lo rechace con un mensaje entendible.
 * Inventar un formato sobre un dato que no entendemos sería peor.
 */
final class Rut
{
    /** Cuerpo de 7 u 8 dígitos + DV, ya sin separadores ("123456789"). */
    private const PATRON_PLANO = '/^(\d{7,8})([0-9K])$/';

    /** El formato que se guarda: cuerpo, guion y DV. */
    public const PATRON_CANONICO = '/^\d{7,8}-[0-9K]$/';

    /**
     * Lleva cualquier escritura del RUT al formato canónico.
     *
     * Acepta puntos, espacios, guion o nada de eso, y la K en cualquier caja.
     */
    public static function normalizar(int|string|null $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $original = trim((string) $valor);

        if ($original === '') {
            return null;
        }

        $plano = strtoupper(preg_replace('/[^0-9kK]/u', '', $original) ?? '');

        if (preg_match(self::PATRON_PLANO, $plano, $partes) === 1) {
            return "{$partes[1]}-{$partes[2]}";
        }

        return $original;
    }

    /**
     * Arma el RUT cuando el cuerpo y el dígito verificador vienen separados,
     * como los entrega la intranet (`ALUM_RUT` numérico + `ALUM_DIGITO`).
     *
     * Con el DV explícito no hay nada que adivinar, así que se respeta el corte
     * que trae el origen en vez de deducirlo del largo.
     */
    public static function desdePartes(int|string $cuerpo, int|string|null $dv = null): ?string
    {
        $soloCuerpo = preg_replace('/\D/', '', (string) $cuerpo) ?? '';
        $dv = strtoupper(trim((string) ($dv ?? '')));

        if ($soloCuerpo !== '' && preg_match('/^[0-9K]$/', $dv) === 1) {
            return "{$soloCuerpo}-{$dv}";
        }

        return self::normalizar(trim((string) $cuerpo) . $dv);
    }

    /** ¿El valor ya está escrito como se guarda? */
    public static function esCanonico(?string $valor): bool
    {
        return $valor !== null && preg_match(self::PATRON_CANONICO, $valor) === 1;
    }

    /** ¿Es un RUT reconocible (7 u 8 dígitos + DV), venga como venga escrito? */
    public static function esValido(int|string|null $valor): bool
    {
        return self::esCanonico(self::normalizar($valor));
    }

    /**
     * Sólo dígitos y la K, sin separadores. Para comparar contra datos antiguos
     * que todavía puedan tener otro formato (búsquedas, login).
     */
    public static function soloDigitos(int|string|null $valor): string
    {
        return strtoupper(preg_replace('/[^0-9kK]/u', '', (string) $valor) ?? '');
    }
}
