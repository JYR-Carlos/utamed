<?php

namespace Database\Seeders\Dm069;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Lector de los libros Excel de database/seeders/data/dm069 que describen el
 * curso DM069 (Taller de Multimedia II) tal como venía del EVEA antiguo.
 *
 * Los xlsx son la fuente editable por el equipo (colores: azul = literal del
 * PDF, amarillo = valor propuesto, gris = legado sin columna en la app). Este
 * lector sólo entrega arreglos limpios; toda la interpretación (a qué tabla va
 * cada dato) vive en los seeders.
 *
 * Diferencia con Dm095Datos: DM069 tiene dos paralelos con las MISMAS siete
 * actividades pero distinto código EVEA y distinto vencimiento (B vence un día
 * antes, salvo el examen). Por eso actividades() pide la letra del paralelo y
 * el libro trae columnas cod_evea_A/fecha_limite_A y cod_evea_B/fecha_limite_B.
 * El programa y la bibliografía sí son comunes a los dos.
 *
 * Vive en su propio directorio de datos para que el glob de las matrices de
 * evaluación no se cruce con las de DM095.
 */
final class Dm069Datos
{
    public const DIR = __DIR__ . '/../data/dm069';

    /** Libros compartidos con el resto del proyecto (la foto de producción). */
    public const DIR_COMUN = __DIR__ . '/../data';

    /** Paralelos que cubren estos libros. */
    public const PARALELOS = ['A', 'B'];

    /** Foto de la base de producción, en DIR_COMUN. La usa sólo el escenario local. */
    public const SNAPSHOT_PRODUCCION = 'estado_produccion_2026-09-21.xlsx';

    /** @var array<string, array<string, list<array<string, mixed>>>> archivo => hoja => filas */
    private array $cache = [];

    // ------------------------------------------------------------------ Cursos

    /**
     * Las secciones del curso (hoja Cursos de programa_dm069.xlsx), una fila por
     * paralelo. `cod_curso_prod` puede venir vacío: entonces el curso se localiza
     * por asignatura + período + indice_grupo.
     *
     * @return list<array{indice_grupo:int, letra_grupo:string, cod_evea:?string, cod_curso_prod:?int, cod_asignatura:string,
     *               agno_real:int, semestre_real:int, fecha_inicio:string, fecha_fin:string,
     *               docente_titular_email:?string, docente_taller_email:?string}>
     */
    public function cursos(): array
    {
        return array_map(fn ($r) => [
            'indice_grupo'          => (int) $r['indice_grupo'],
            'letra_grupo'           => strtoupper(trim((string) $r['letra_grupo'])),
            'cod_evea'              => self::str($r['cod_evea'] ?? null),
            'cod_curso_prod'        => self::intOrNull($r['cod_curso_prod'] ?? null),
            'cod_asignatura'        => (string) $r['cod_asignatura'],
            'agno_real'             => (int) $r['agno_real'],
            'semestre_real'         => (int) $r['semestre_real'],
            'fecha_inicio'          => (string) $r['fecha_inicio'],
            'fecha_fin'             => (string) $r['fecha_fin'],
            'docente_titular_email' => self::str($r['docente_titular_email'] ?? null),
            'docente_taller_email'  => self::str($r['docente_taller_email'] ?? null),
        ], $this->filas('programa_dm069.xlsx', 'Cursos'));
    }

    // ------------------------------------------------------------------ Programa

    /** Hoja Identificacion como mapa campo => valor. */
    public function identificacion(): array
    {
        $out = [];
        foreach ($this->filas('programa_dm069.xlsx', 'Identificacion') as $r) {
            $out[(string) $r['campo']] = $r['valor'];
        }
        return $out;
    }

    /** Hoja Tutor como mapa campo => valor (nombre, grado, especialidad, email, username). */
    public function tutor(): array
    {
        $out = [];
        foreach ($this->filas('programa_dm069.xlsx', 'Tutor') as $r) {
            $out[(string) $r['campo']] = self::str($r['valor']);
        }
        return $out;
    }

    /**
     * Textos de las secciones II, IV y V (hoja Secciones_texto), agrupados por la
     * clave del JSON y ordenados por `orden`. Las filas sin texto se omiten.
     *
     * @return array<string, list<string>> clave => textos
     */
    public function textosSecciones(): array
    {
        $out = [];
        $filas = array_filter($this->filas('programa_dm069.xlsx', 'Secciones_texto'), fn ($r) => self::str($r['texto']) !== null);
        usort($filas, fn ($a, $b) => [(string) $a['clave'], (int) $a['orden']] <=> [(string) $b['clave'], (int) $b['orden']]);
        foreach ($filas as $r) {
            $out[(string) $r['clave']][] = (string) $r['texto'];
        }
        return $out;
    }

    /**
     * Unidades (hoja Unidades del programa): numero, titulo, fechas de referencia.
     *
     * @return list<array{numero:int, titulo:string, desde:?string, hasta:?string, modalidad:?string}>
     */
    public function unidades(): array
    {
        $out = [];
        foreach ($this->filas('programa_dm069.xlsx', 'Unidades') as $r) {
            $numero = self::intOrNull($r['numero'] ?? null);
            if ($numero === null) {
                continue;
            }
            $out[] = [
                'numero'    => $numero,
                'titulo'    => trim((string) $r['titulo']),
                'desde'     => self::str($r['desde_evea'] ?? null),
                'hasta'     => self::str($r['hasta_evea'] ?? null),
                'modalidad' => self::str($r['modalidad_evea'] ?? null),
            ];
        }
        usort($out, fn ($a, $b) => $a['numero'] <=> $b['numero']);
        return $out;
    }

    /** @return array<int, list<string>> num_unidad => resultados en orden */
    public function aprendizajes(): array
    {
        return $this->porUnidad('Aprendizajes', 'resultado');
    }

    /** @return array<int, list<string>> num_unidad => contenidos en orden */
    public function contenidos(): array
    {
        return $this->porUnidad('Contenidos', 'item');
    }

    /**
     * Metodología por unidad: lista de [estrategia, texto] en orden.
     *
     * @return array<int, list<array{estrategia:string, texto:string}>>
     */
    public function metodologia(): array
    {
        $filas = $this->filas('programa_dm069.xlsx', 'Metodologia');
        usort($filas, fn ($a, $b) => [(int) $a['num_unidad'], (int) $a['orden']] <=> [(int) $b['num_unidad'], (int) $b['orden']]);
        $out = [];
        foreach ($filas as $r) {
            $out[(int) $r['num_unidad']][] = ['estrategia' => trim((string) $r['estrategia']), 'texto' => trim((string) $r['texto'])];
        }
        return $out;
    }

    // ------------------------------------------------------------------ Actividades

    /**
     * Las actividades en la forma de agenda.actividad (hoja Actividades de
     * actividades_dm069.xlsx), resueltas para un paralelo: el `cod_evea` y la
     * `fecha_limite` salen de las columnas de ESE paralelo.
     *
     * `descripcion` es el texto que el programa usa para la actividad (la parte
     * después de los dos puntos del nombre).
     *
     * @param  string  $letra  paralelo, 'A' o 'B'
     * @return list<array<string, mixed>>
     */
    public function actividades(string $letra): array
    {
        $letra = strtoupper(trim($letra));
        if (!in_array($letra, self::PARALELOS, true)) {
            throw new RuntimeException("Paralelo «{$letra}» desconocido: los libros de DM069 sólo traen " . implode(' y ', self::PARALELOS) . '.');
        }

        $descripciones = [];
        foreach ($this->filas('programa_dm069.xlsx', 'Actividades') as $r) {
            $descripciones[(int) $r['orden']] = self::str($r['descripcion_evea'] ?? null);
        }

        $colCodigo = "cod_evea_{$letra}";
        $colFecha = "fecha_limite_{$letra}";

        $out = [];
        foreach ($this->filas('actividades_dm069.xlsx', 'Actividades') as $r) {
            if (!array_key_exists($colFecha, $r)) {
                throw new RuntimeException("actividades_dm069.xlsx: la hoja Actividades no tiene la columna «{$colFecha}»; sin ella no se puede sembrar el paralelo {$letra}.");
            }

            $orden = (int) $r['orden'];
            $fecha = self::str($r[$colFecha]);
            if ($fecha === null) {
                throw new RuntimeException("actividades_dm069.xlsx: la actividad de orden {$orden} no tiene «{$colFecha}».");
            }

            $out[] = [
                'orden'                             => $orden,
                'cod_evea'                          => self::intOrNull($r[$colCodigo] ?? null),
                'nombre'                            => trim((string) $r['nombre']),
                'alias_nombre_prod'                 => self::str($r['alias_nombre_prod'] ?? null),
                'tipo_actividad'                    => strtoupper(trim((string) $r['tipo_actividad'])),
                'fecha_limite'                      => self::fecha($fecha),
                'tipo_entrega'                      => (string) $r['tipo_entrega'],
                'ponderacion'                       => (int) $r['ponderacion'],
                'exigencia'                         => (int) $r['exigencia'],
                'nro_dias_adicionales_para_bloqueo' => (int) ($r['nro_dias_adicionales_para_bloqueo'] ?? 0),
                'visible'                           => self::bool($r['visible'] ?? true),
                'es_grupal'                         => self::bool($r['es_grupal'] ?? false),
                'max_integrantes'                   => (int) ($r['max_integrantes'] ?? 1),
                'componente_tipo'                   => trim((string) $r['componente_tipo']),
                'num_unidad'                        => (int) $r['num_unidad'],
                'unidad_nombre'                     => trim((string) $r['unidad_nombre']),
                'descripcion'                       => $descripciones[$orden] ?? null,
                'horas_evea'                        => self::intOrNull($r['horas_evea'] ?? null),
            ];
        }

        usort($out, fn ($a, $b) => $a['orden'] <=> $b['orden']);
        return $out;
    }

    // ------------------------------------------------------------------ Bibliografía

    /**
     * Los recursos bibliográficos en la forma de curso.bibliografia. Comunes a los
     * dos paralelos: cada curso recibe sus propias filas porque la tabla cuelga de
     * id_programa. `url`/`uuid_archivo` vienen vacíos desde el EVEA; el seeder
     * decide qué hacer con la restricción CHECK.
     *
     * @return list<array<string, mixed>>
     */
    public function bibliografia(): array
    {
        $out = [];
        foreach ($this->filas('bibliografia_dm069.xlsx', 'Bibliografia') as $r) {
            $out[] = [
                'orden_global'        => (int) $r['orden_global'],
                'num_unidad'          => self::intOrNull($r['num_unidad'] ?? null),
                'orden_evea'          => (int) $r['orden_evea'],
                'tipo_evea'           => self::str($r['tipo_evea'] ?? null),
                'titulo'              => trim((string) $r['titulo']),
                'autor'               => trim((string) $r['autor']),
                'agno'                => (int) $r['agno'],
                'editorial'           => self::str($r['editorial'] ?? null),
                'cita'                => self::str($r['cita'] ?? null),
                'url'                 => self::str($r['url'] ?? null),
                'uuid_archivo'        => self::str($r['uuid_archivo'] ?? null),
                'es_bibliografia_uta' => self::bool($r['es_bibliografia_uta'] ?? false),
            ];
        }
        usort($out, fn ($a, $b) => $a['orden_global'] <=> $b['orden_global']);
        return $out;
    }

    // ------------------------------------------------------------------ Rúbricas

    /**
     * Las matrices de evaluación (libros `matriz_evaluacion_*.xlsx` de este
     * directorio) ya en la forma del JSON de agenda.rubrica.rubrica, indexadas por
     * el `orden` de la actividad a la que pertenecen (hoja Rubrica, campo
     * actividad_orden).
     *
     * Cada libro trae la matriz repartida en tres hojas: `filas` (los criterios y su
     * peso), `columnas` (los niveles de desempeño y su puntaje) y `descripcion` (una
     * celda por cruce). Lo que el JSON llama `niveles` son las FILAS y lo que llama
     * `columnas` son los niveles de desempeño: el nombre va al revés de lo que sugiere
     * la matriz, y es el error fácil al leer este código.
     *
     * Los id (c1.., n1.., n1e1..) son fijos en vez de aleatorios como los del editor
     * web, para que re-sembrar no cambie el JSON y el seeder pueda decir «sin cambios».
     *
     * @return array<int, array{archivo:string, actividad_orden:int, actividad_nombre:?string,
     *               estado_rubrica:string, rubrica:array<string, mixed>}>
     */
    public function rubricas(): array
    {
        $out = [];

        foreach (glob(self::DIR . DIRECTORY_SEPARATOR . 'matriz_evaluacion_*.xlsx') ?: [] as $ruta) {
            $archivo = basename($ruta);
            $meta = [];
            foreach ($this->filas($archivo, 'Rubrica') as $r) {
                $meta[(string) $r['campo']] = $r['valor'];
            }

            $orden = self::intOrNull($meta['actividad_orden'] ?? null);
            if ($orden === null) {
                throw new RuntimeException("{$archivo}: la hoja Rubrica no declara «actividad_orden»; sin eso no se sabe a qué actividad engancharla.");
            }
            if (isset($out[$orden])) {
                throw new RuntimeException("{$archivo}: la actividad de orden {$orden} ya tiene matriz en {$out[$orden]['archivo']}. Una actividad, una rúbrica.");
            }

            $out[$orden] = [
                'archivo'          => $archivo,
                'actividad_orden'  => $orden,
                'actividad_nombre' => self::str($meta['actividad_nombre'] ?? null),
                'estado_rubrica'   => strtoupper((string) (self::str($meta['estado_rubrica'] ?? null) ?? 'POSTULADA')),
                'rubrica'          => $this->armarRubrica($archivo),
            ];
        }

        ksort($out);
        return $out;
    }

    /** @return array<string, mixed> el JSON de agenda.rubrica.rubrica */
    private function armarRubrica(string $archivo): array
    {
        $columnas = $this->columnasRubrica($archivo);
        $criterios = $this->criteriosRubrica($archivo);
        $celdas = $this->celdasRubrica($archivo, $columnas);

        $puntajeMaximo = max(array_column($columnas, 'puntos'));

        $niveles = [];
        foreach ($criterios as $i => $c) {
            $clave = self::clave($c['nombre']);
            if (!isset($celdas[$clave])) {
                throw new RuntimeException("{$archivo}: el criterio «{$c['nombre']}» está en la hoja «filas» pero no en la hoja «descripcion».");
            }

            $n = $i + 1;
            $escalas = [];
            foreach ($columnas as $j => $col) {
                $escalas[] = [
                    'id' => "n{$n}e" . ($j + 1),
                    // El puntaje es de la columna; se copia en cada celda porque la
                    // vista del alumno y el cálculo del puntaje obtenido lo leen aquí.
                    'puntos'   => $col['puntos'],
                    'criterio' => $celdas[$clave][$j],
                ];
            }

            $niveles[] = [
                'id'             => "n{$n}",
                'nombre'         => $c['nombre'],
                // La matriz no trae un segundo texto por criterio; el editor web
                // guarda cadena vacía cuando el docente no escribe descripción.
                'descripcion'    => '',
                'ponderacion'    => $c['ponderacion'],
                'nro_escalas'    => count($columnas),
                'puntaje_minimo' => 0,
                'puntaje_total'  => $puntajeMaximo,
                'escalas'        => $escalas,
            ];
        }

        return [
            'columnas' => array_map(
                fn ($col, $j) => ['id' => 'c' . ($j + 1), 'nombre' => $col['nombre'], 'puntos' => $col['puntos']],
                $columnas, array_keys($columnas)
            ),
            'niveles'             => $niveles,
            'detalles_evaluacion' => [
                'puntaje_total' => count($niveles) * $puntajeMaximo,
                // Sólo las FORMATIVAS guardan escala cualitativa; storeRubrica() la
                // descarta en las sumativas. La matriz que tenemos es sumativa.
                'escala_evaluacion' => [],
            ],
        ];
    }

    /**
     * Hoja `columnas`: los niveles de desempeño en orden, con el puntaje que vale cada uno.
     *
     * @return list<array{nombre:string, puntos:float|int}>
     */
    private function columnasRubrica(string $archivo): array
    {
        $out = [];
        foreach ($this->filas($archivo, 'columnas') as $r) {
            $nombre = self::str($r['Rango'] ?? null);
            if ($nombre === null) {
                continue;
            }
            $out[] = ['nombre' => $nombre, 'puntos' => self::num($r['Puntaje'] ?? null, "{$archivo}: el nivel «{$nombre}» no tiene puntaje.")];
        }

        if (count($out) < 2) {
            throw new RuntimeException("{$archivo}: la hoja «columnas» necesita al menos dos niveles de desempeño.");
        }

        // Misma regla que reglaPuntajesMonotonos() en DocenteActivityController:
        // estrictamente crecientes o decrecientes, sin repetidos y no negativos.
        $puntos = array_column($out, 'puntos');
        if (min($puntos) < 0) {
            throw new RuntimeException("{$archivo}: hay puntajes negativos en la hoja «columnas».");
        }
        $asc = $desc = true;
        for ($i = 1; $i < count($puntos); $i++) {
            $asc = $asc && $puntos[$i] > $puntos[$i - 1];
            $desc = $desc && $puntos[$i] < $puntos[$i - 1];
        }
        if (!$asc && !$desc) {
            throw new RuntimeException("{$archivo}: los puntajes de la hoja «columnas» (" . implode(', ', $puntos) . ') deben ser estrictamente crecientes o decrecientes; la app rechaza la rúbrica si no.');
        }

        return $out;
    }

    /**
     * Hoja `filas`: los criterios en orden, con su peso. Las ponderaciones deben sumar
     * exactamente 100 o la app rechaza la rúbrica.
     *
     * @return list<array{nombre:string, ponderacion:float|int}>
     */
    private function criteriosRubrica(string $archivo): array
    {
        $out = [];
        foreach ($this->filas($archivo, 'filas') as $r) {
            $nombre = self::str($r['Aspectos a evaluar'] ?? null);
            if ($nombre === null) {
                continue;
            }
            $out[] = ['nombre' => $nombre, 'ponderacion' => self::num($r['Porcentaje'] ?? null, "{$archivo}: el criterio «{$nombre}» no tiene porcentaje.")];
        }

        if (!$out) {
            throw new RuntimeException("{$archivo}: la hoja «filas» no tiene criterios.");
        }

        $suma = round(array_sum(array_column($out, 'ponderacion')), 2);
        if (abs($suma - 100) > 0.001) {
            throw new RuntimeException("{$archivo}: las ponderaciones de la hoja «filas» suman {$suma} y deben sumar exactamente 100.");
        }

        return $out;
    }

    /**
     * Hoja `descripcion`: una fila por criterio, una columna por nivel de desempeño.
     * Se verifica que los encabezados sigan el orden de la hoja `columnas`, porque el
     * cruce se arma por POSICIÓN: si alguien reordena las columnas sin reordenar la
     * otra hoja, cada texto quedaría colgando del puntaje equivocado.
     *
     * @param  list<array{nombre:string, puntos:float|int}>  $columnas
     * @return array<string, list<string>> criterio normalizado => textos en orden
     */
    private function celdasRubrica(string $archivo, array $columnas): array
    {
        $filas = $this->filas($archivo, 'descripcion');
        if (!$filas) {
            throw new RuntimeException("{$archivo}: la hoja «descripcion» está vacía.");
        }

        $encabezados = array_keys($filas[0]);
        $niveles = array_slice($encabezados, 1);
        if (count($niveles) !== count($columnas)) {
            throw new RuntimeException("{$archivo}: la hoja «descripcion» tiene " . count($niveles) . ' niveles y la hoja «columnas» ' . count($columnas) . '.');
        }
        foreach ($columnas as $j => $col) {
            if (self::clave($niveles[$j]) !== self::clave($col['nombre'])) {
                throw new RuntimeException("{$archivo}: la columna " . ($j + 2) . " de «descripcion» se llama «{$niveles[$j]}» y en «columnas» ese nivel es «{$col['nombre']}». Los dos órdenes deben calzar.");
            }
        }

        $out = [];
        foreach ($filas as $r) {
            $criterio = self::str($r[$encabezados[0]] ?? null);
            if ($criterio === null) {
                continue;
            }
            $textos = [];
            foreach ($niveles as $nivel) {
                $textos[] = (string) (self::str($r[$nivel] ?? null) ?? '');
            }
            $out[self::clave($criterio)] = $textos;
        }

        return $out;
    }

    // ------------------------------------------------------------------ Estudiantes

    /**
     * Los alumnos que producción tenía inscritos en un paralelo, según la hoja
     * Inscripciones de la foto del 2026-09-21. Es la única fuente con el RUT
     * completo (los PDF del EVEA imprimen el cuerpo sin dígito verificador y con
     * la ñ rota).
     *
     * Los apellidos vienen en un solo campo: se parte por el ÚLTIMO espacio, que
     * es lo que acierta con los compuestos («DE LA FUENTE SOTO» → «DE LA FUENTE»
     * + «SOTO»).
     *
     * Sólo la usa Dm069EscenarioLocalSeeder: en producción los alumnos ya están.
     *
     * @param  string  $letra  paralelo, 'A' o 'B'
     * @return list<array{rut:int, dv:string, rut_completo:string, nombres:string, apellido1:string, apellido2:?string, email:?string, estado:?string}>
     */
    public function inscripcionesProduccion(string $letra): array
    {
        $etiqueta = 'DM069 ' . strtoupper(trim($letra));

        $out = [];
        foreach ($this->filas(self::SNAPSHOT_PRODUCCION, 'Inscripciones') as $r) {
            if (trim((string) ($r['curso'] ?? '')) !== $etiqueta) {
                continue;
            }

            $completo = trim((string) $r['rut']);
            if (!str_contains($completo, '-')) {
                throw new RuntimeException("La inscripción «{$completo}» de {$etiqueta} no trae dígito verificador.");
            }
            [$cuerpo, $dv] = explode('-', $completo, 2);

            $apellidos = preg_replace('/\s+/u', ' ', trim((string) $r['apellidos']));
            $corte = mb_strrpos($apellidos, ' ');

            $out[] = [
                'rut'          => (int) $cuerpo,
                'dv'           => strtoupper(trim($dv)),
                'rut_completo' => $completo,
                'nombres'      => preg_replace('/\s+/u', ' ', trim((string) $r['nombres'])),
                'apellido1'    => $corte === false ? $apellidos : mb_substr($apellidos, 0, $corte),
                'apellido2'    => $corte === false ? null : mb_substr($apellidos, $corte + 1),
                'email'        => self::str($r['email'] ?? null),
                'estado'       => self::str($r['estado_inscripcion'] ?? null),
            ];
        }

        if (!$out) {
            throw new RuntimeException("La hoja Inscripciones de " . self::SNAPSHOT_PRODUCCION . " no tiene filas para «{$etiqueta}».");
        }

        return $out;
    }

    // ------------------------------------------------------------------ infraestructura

    /**
     * Filas de una hoja como arreglos asociativos por nombre de encabezado.
     * Las filas completamente vacías se descartan.
     *
     * @return list<array<string, mixed>>
     */
    public function filas(string $archivo, string $hoja): array
    {
        if (isset($this->cache[$archivo][$hoja])) {
            return $this->cache[$archivo][$hoja];
        }

        $libro = $this->abrir($archivo);
        $ws = $libro->getSheetByName($hoja);
        if (!$ws) {
            throw new RuntimeException("La hoja «{$hoja}» no existe en {$archivo}.");
        }

        return $this->cache[$archivo][$hoja] = $this->filasDe($ws);
    }

    private function abrir(string $archivo): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        // Los libros propios de DM069 están en data/dm069; los compartidos con el
        // resto del proyecto (la foto de producción) viven un nivel más arriba.
        $ruta = self::DIR . DIRECTORY_SEPARATOR . $archivo;
        if (!is_file($ruta)) {
            $ruta = self::DIR_COMUN . DIRECTORY_SEPARATOR . $archivo;
        }
        if (!is_file($ruta)) {
            throw new RuntimeException("No se encuentra {$archivo} ni en " . self::DIR . ' ni en ' . self::DIR_COMUN . '.');
        }
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);

        // La versión de PhpSpreadsheet que trae maatwebsite/excel dispara cientos
        // de E_DEPRECATED bajo PHP 8.3 al leer las columnas; silenciarlos sólo
        // durante la carga evita que tapen la salida del seeder.
        $nivel = error_reporting(E_ALL & ~E_DEPRECATED);
        try {
            return $reader->load($ruta);
        } finally {
            error_reporting($nivel);
        }
    }

    /** @return list<array<string, mixed>> */
    private function filasDe(Worksheet $ws): array
    {
        $matriz = $ws->toArray(null, true, false, false);
        if (empty($matriz)) {
            return [];
        }
        $encabezados = array_map(fn ($h) => trim((string) $h), array_shift($matriz));

        $out = [];
        foreach ($matriz as $fila) {
            if (!array_filter($fila, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            $asoc = [];
            foreach ($encabezados as $i => $h) {
                if ($h !== '') {
                    $asoc[$h] = $fila[$i] ?? null;
                }
            }
            $out[] = $asoc;
        }
        return $out;
    }

    /** @return array<int, list<string>> */
    private function porUnidad(string $hoja, string $columna): array
    {
        $filas = $this->filas('programa_dm069.xlsx', $hoja);
        usort($filas, fn ($a, $b) => [(int) $a['num_unidad'], (int) $a['orden']] <=> [(int) $b['num_unidad'], (int) $b['orden']]);
        $out = [];
        foreach ($filas as $r) {
            $out[(int) $r['num_unidad']][] = trim((string) $r[$columna]);
        }
        return $out;
    }

    private static function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private static function intOrNull(mixed $v): ?int
    {
        $s = self::str($v);
        return $s === null ? null : (int) $s;
    }

    /**
     * Fecha como Y-m-d. Las celdas se escribieron como texto, pero si alguien
     * reabre el libro en Excel y las reformatea pueden volver como serial o como
     * DateTime: los tres casos se normalizan aquí.
     */
    private static function fecha(string $valor): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        if (is_numeric($valor)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
        }
        $ts = strtotime($valor);
        if ($ts === false) {
            throw new RuntimeException("«{$valor}» no es una fecha reconocible; se esperaba Y-m-d.");
        }
        return date('Y-m-d', $ts);
    }

    /** Número de una celda; int cuando no tiene decimales, para que el JSON diga 4 y no 4.0. */
    private static function num(mixed $v, string $error): float|int
    {
        $s = self::str($v);
        if ($s === null || !is_numeric($s)) {
            throw new RuntimeException($error);
        }
        $n = (float) $s;
        return $n == (int) $n ? (int) $n : $n;
    }

    /** Texto normalizado para comparar encabezados y criterios entre hojas. */
    private static function clave(string $s): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
    }

    private static function bool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtoupper(trim((string) $v)), ['1', 'TRUE', 'VERDADERO', 'SÍ', 'SI'], true);
    }
}
