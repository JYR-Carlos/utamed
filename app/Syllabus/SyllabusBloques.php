<?php

namespace App\Syllabus;

/**
 * Recupera la estructura del texto que aplana App\Traits\ParsesSyllabus, para
 * componer el programa en PDF: campos en columnas, subtítulos, viñetas y
 * párrafos. Es la versión en PHP de `bloquesDeSeccion`
 * (resources/js/modules/resources/programa/utils/syllabusTexto.ts), con las
 * mismas convenciones; si una cambia, la otra también.
 *
 * Los campos seguidos quedan juntos en un bloque «campos», para que la columna
 * de los valores quede a la misma altura.
 */
final class SyllabusBloques
{
    /** Secciones de prosa libre (SeccionTextoContenido). */
    private const PROSA = ['II', 'III'];

    /** `Etiqueta: valor`: la etiqueta es corta. */
    private const CAMPO = '/^([^:]{1,40}):[ \t]+(.+)$/u';

    /** `Etiqueta:` sola, encabezando un grupo. */
    private const SUBTITULO = '/^([^:]{1,40}):[ \t]*$/u';

    /** `Unidad 3: Prototipo` abre un bloque, no es un campo. */
    private const UNIDAD = '/^Unidad\s+\d+\s*:/iu';

    /** `- texto`: un punto escrito a mano por el docente; es una viñeta. */
    private const GUION = '/^[-–]\s+/u';

    /** `1. texto`: un punto numerado; se lee como párrafo aunque lleve dos puntos. */
    private const NUMERADO = '/^\d+[.)]\s/u';

    /**
     * @return list<array{tipo:string, texto?:string, filas?:list<array{etiqueta:string,valor:string}>, items?:list<array{texto:string,anidado:bool,cita:?string}>}>
     */
    public static function de(?string $texto, ?string $numeral = null): array
    {
        $bruto = str_replace("\r\n", "\n", $texto ?? '');

        if (trim($bruto) === '') {
            return [];
        }

        if (in_array($numeral, self::PROSA, true)) {
            $parrafos = array_filter(array_map('trim', preg_split('/\n{2,}/', $bruto)), fn ($p) => $p !== '');

            return array_values(array_map(fn ($p) => ['tipo' => 'parrafo', 'texto' => $p], $parrafos));
        }

        $bloques = [];
        // Índice de la lista o de los campos que siguen abiertos, si los hay.
        $abierto = null;

        foreach (explode("\n", $bruto) as $linea) {
            $contenido = trim($linea);

            if ($contenido === '') {
                if ($abierto !== null && $bloques[$abierto]['tipo'] === 'lista') {
                    $abierto = null;
                }

                continue;
            }

            $sangria = preg_match('/^\s{2,}/', $linea) === 1;
            $enLista = $abierto !== null && $bloques[$abierto]['tipo'] === 'lista';

            if (str_starts_with($contenido, '•') || preg_match(self::GUION, $contenido)) {
                $item = trim(mb_substr($contenido, 1));
                if ($item === '') {
                    continue;
                }
                if (! $enLista) {
                    $bloques[] = ['tipo' => 'lista', 'items' => []];
                    $abierto = array_key_last($bloques);
                }
                $bloques[$abierto]['items'][] = ['texto' => $item, 'anidado' => $sangria, 'cita' => null];

                continue;
            }

            // Líneas con sangría o citas que pertenecen al ítem anterior.
            if ($enLista && ($sangria || str_starts_with($contenido, '«'))) {
                $ultimo = array_key_last($bloques[$abierto]['items']);
                if (str_starts_with($contenido, '«')) {
                    $bloques[$abierto]['items'][$ultimo]['cita'] = $contenido;
                } else {
                    $bloques[$abierto]['items'][$ultimo]['texto'] .= ' '.$contenido;
                }

                continue;
            }

            if (preg_match(self::UNIDAD, $contenido)) {
                $bloques[] = ['tipo' => 'subtitulo', 'texto' => $contenido];
                $abierto = null;
            } elseif (preg_match(self::NUMERADO, $contenido)) {
                $bloques[] = ['tipo' => 'parrafo', 'texto' => $contenido];
                $abierto = null;
            } elseif (preg_match(self::SUBTITULO, $contenido, $m)) {
                $bloques[] = ['tipo' => 'subtitulo', 'texto' => trim($m[1])];
                $abierto = null;
            } elseif (preg_match(self::CAMPO, $contenido, $m)) {
                if ($abierto === null || $bloques[$abierto]['tipo'] !== 'campos') {
                    $bloques[] = ['tipo' => 'campos', 'filas' => []];
                    $abierto = array_key_last($bloques);
                }
                $bloques[$abierto]['filas'][] = ['etiqueta' => trim($m[1]), 'valor' => trim($m[2])];
            } else {
                $bloques[] = ['tipo' => 'parrafo', 'texto' => $contenido];
                $abierto = null;
            }
        }

        return $bloques;
    }
}
