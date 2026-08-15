<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deja todos los RUT de `usuario.usuario` escritos de una sola forma: sin puntos
 * y con guion ("12345678-9"), que es el formato que produce {@see App\Support\Rut}.
 *
 * POR QUÉ: la columna es texto con UNIQUE, y el UNIQUE compara cadenas. Mientras
 * convivieron "23.671.848-4" (sembrado) y "23671848-4" (creado por la app), la
 * misma persona podía entrar dos veces sin que la restricción lo notara. Desde
 * ahora la aplicación normaliza al escribir; esta migración arregla lo ya escrito.
 *
 * QUÉ NO TOCA:
 *  - Valores que no tienen forma de RUT (largo fuera de 7-8 dígitos + DV): se
 *    dejan como están, para que alguien los revise en vez de inventarles formato.
 *  - Grupos que al normalizar colisionarían entre sí: son personas realmente
 *    duplicadas y fusionarlas implica mover inscripciones, mensajes y roles. La
 *    migración los reporta por consola y por log, y no los toca.
 */
return new class extends Migration
{
    /**
     * Deriva el RUT canónico de cada fila.
     *
     * `sin_separadores` deja sólo dígitos y la K en mayúscula; el DV es el último
     * carácter. El filtro descarta lo que no tenga forma de RUT.
     */
    private const CTE_CANONICO = <<<'SQL'
        WITH plano AS (
            SELECT id_usuario,
                   rut,
                   regexp_replace(upper(rut), '[^0-9K]', '', 'g') AS sin_separadores
              FROM usuario.usuario
        ),
        canonico AS (
            SELECT id_usuario,
                   rut,
                   left(sin_separadores, length(sin_separadores) - 1)
                       || '-' || right(sin_separadores, 1) AS rut_canonico
              FROM plano
             WHERE sin_separadores ~ '^[0-9]{7,8}[0-9K]$'
        )
        SQL;

    public function up(): void
    {
        $conexion = DB::connection($this->getConnection());

        $colisiones = $conexion->select(self::CTE_CANONICO . <<<'SQL'
            SELECT rut_canonico,
                   string_agg(rut || ' (id_usuario ' || id_usuario || ')', ', ' ORDER BY id_usuario) AS filas
              FROM canonico
             GROUP BY rut_canonico
            HAVING count(*) > 1
             ORDER BY rut_canonico
            SQL);

        foreach ($colisiones as $colision) {
            $aviso = "RUT duplicado, sin normalizar: {$colision->rut_canonico} → {$colision->filas}. "
                . 'Hay que decidir a mano cuál queda y trasladar lo asociado.';

            Log::warning($aviso);
            echo "  ! {$aviso}\n";
        }

        // Sólo los que quedan solos tras normalizar: cambiar los duplicados
        // chocaría contra uq_rut y abortaría la migración entera.
        $normalizadas = $conexion->update(self::CTE_CANONICO . <<<'SQL'
            , unicos AS (
                SELECT rut_canonico
                  FROM canonico
                 GROUP BY rut_canonico
                HAVING count(*) = 1
            )
            UPDATE usuario.usuario u
               SET rut = c.rut_canonico
              FROM canonico c
             WHERE u.id_usuario = c.id_usuario
               AND u.rut IS DISTINCT FROM c.rut_canonico
               AND c.rut_canonico IN (SELECT rut_canonico FROM unicos)
            SQL);

        $sinForma = $conexion->selectOne(<<<'SQL'
            SELECT count(*) AS total
              FROM usuario.usuario
             WHERE regexp_replace(upper(rut), '[^0-9K]', '', 'g') !~ '^[0-9]{7,8}[0-9K]$'
            SQL);

        echo "  RUT normalizados: {$normalizadas}\n";

        if ((int) $sinForma->total > 0) {
            echo "  ! RUT que no tienen forma de RUT y quedaron intactos: {$sinForma->total}\n";
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: no se guarda cómo estaba escrito antes cada
        // RUT, y no hace falta — el valor identifica a la misma persona en ambos
        // formatos, así que revertir sólo devolvería la ambigüedad que causó los
        // duplicados. Un rollback del despliegue no necesita deshacer esto.
    }
};
