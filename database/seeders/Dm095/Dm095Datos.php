<?php

namespace Database\Seeders\Dm095;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Lector de los libros Excel de database/seeders/data que describen el curso
 * DM095 (Taller Profesional IV) tal como venía del EVEA antiguo.
 *
 * Los xlsx son la fuente editable por el equipo (colores: azul = literal del
 * PDF, amarillo = valor propuesto, gris = legado sin columna en la app). Este
 * lector sólo entrega arreglos limpios; toda la interpretación (a qué tabla va
 * cada dato) vive en los seeders.
 *
 * Se lee con PhpSpreadsheet (ya viene con maatwebsite/excel) y se cachea por
 * archivo para que los cuatro seeders no lo abran cuatro veces.
 */
final class Dm095Datos
{
    public const DIR = __DIR__ . '/../data';

    /** @var array<string, array<string, list<array<string, mixed>>>> archivo => hoja => filas */
    private array $cache = [];

    // ------------------------------------------------------------------ Cursos

    /**
     * Las secciones del curso (hoja Cursos de programa_dm095.xlsx), una fila por
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
            'letra_grupo'           => (string) $r['letra_grupo'],
            'cod_evea'              => self::str($r['cod_evea'] ?? null),
            'cod_curso_prod'        => self::intOrNull($r['cod_curso_prod'] ?? null),
            'cod_asignatura'        => (string) $r['cod_asignatura'],
            'agno_real'             => (int) $r['agno_real'],
            'semestre_real'         => (int) $r['semestre_real'],
            'fecha_inicio'          => (string) $r['fecha_inicio'],
            'fecha_fin'             => (string) $r['fecha_fin'],
            'docente_titular_email' => self::str($r['docente_titular_email'] ?? null),
            'docente_taller_email'  => self::str($r['docente_taller_email'] ?? null),
        ], $this->filas('programa_dm095.xlsx', 'Cursos'));
    }

    /** Hoja Identificacion como mapa campo => valor. */
    public function identificacion(): array
    {
        $out = [];
        foreach ($this->filas('programa_dm095.xlsx', 'Identificacion') as $r) {
            $out[(string) $r['campo']] = $r['valor'];
        }
        return $out;
    }

    /** Hoja Tutor como mapa campo => valor (nombre, grado, titulo, cargo, email, username). */
    public function tutor(): array
    {
        $out = [];
        foreach ($this->filas('programa_dm095.xlsx', 'Tutor') as $r) {
            $out[(string) $r['campo']] = self::str($r['valor']);
        }
        return $out;
    }

    // ------------------------------------------------------------------ Programa

    /**
     * Textos de las secciones II y IV (hoja Secciones_texto), agrupados por clave
     * JSON y ordenados por `orden`. Las filas sin texto (genéricas, diagnóstica)
     * se omiten: en el EVEA venían «no definidas».
     *
     * @return array<string, list<string>> clave => textos
     */
    public function textosSecciones(): array
    {
        $out = [];
        $filas = array_filter($this->filas('programa_dm095.xlsx', 'Secciones_texto'), fn ($r) => self::str($r['texto']) !== null);
        usort($filas, fn ($a, $b) => [(string) $a['clave'], (int) $a['orden']] <=> [(string) $b['clave'], (int) $b['orden']]);
        foreach ($filas as $r) {
            $out[(string) $r['clave']][] = (string) $r['texto'];
        }
        return $out;
    }

    /**
     * Unidades (hoja Unidades del programa): numero, titulo, fechas 2026 de referencia.
     *
     * @return list<array{numero:int, titulo:string, desde:?string, hasta:?string, modalidad:?string}>
     */
    public function unidades(): array
    {
        $out = [];
        foreach ($this->filas('programa_dm095.xlsx', 'Unidades') as $r) {
            $out[] = [
                'numero'    => (int) $r['numero'],
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
     * Metodología por unidad: lista de [estrategia, texto] en orden. Incluye la
     * «Justificación Metodológica» como una estrategia más.
     *
     * @return array<int, list<array{estrategia:string, texto:string}>>
     */
    public function metodologia(): array
    {
        $filas = $this->filas('programa_dm095.xlsx', 'Metodologia');
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
     * actividades_dm095.xlsx). `alias_nombre_prod` es el nombre con el que la
     * actividad ya puede existir en el curso; `descripcion` viene del programa.
     *
     * @return list<array<string, mixed>>
     */
    public function actividades(): array
    {
        $descripciones = [];
        foreach ($this->filas('programa_dm095.xlsx', 'Actividades') as $r) {
            $descripciones[(int) $r['orden']] = self::str($r['descripcion_evea'] ?? null);
        }

        $out = [];
        foreach ($this->filas('actividades_dm095.xlsx', 'Actividades') as $r) {
            $orden = (int) $r['orden'];
            $out[] = [
                'orden'                             => $orden,
                'cod_evea'                          => self::intOrNull($r['cod_evea'] ?? null),
                'nombre'                            => trim((string) $r['nombre']),
                'alias_nombre_prod'                 => self::str($r['alias_nombre_prod'] ?? null),
                'tipo_actividad'                    => strtoupper(trim((string) $r['tipo_actividad'])),
                'fecha_limite'                      => (string) $r['fecha_limite'],
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
     * Los recursos bibliográficos en la forma de curso.bibliografia. `num_unidad`
     * null = unidad General (id_unidad NULL). `url`/`uuid_archivo` vienen vacíos
     * desde el EVEA; el seeder decide qué hacer con la restricción CHECK.
     *
     * @return list<array<string, mixed>>
     */
    public function bibliografia(): array
    {
        $out = [];
        foreach ($this->filas('bibliografia_dm095.xlsx', 'Bibliografia') as $r) {
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
                'formato_evea'        => self::str($r['formato_evea'] ?? null),
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
     * Las matrices de evaluación (libros `matriz_evaluacion_*.xlsx`) ya en la forma
     * del JSON de agenda.rubrica.rubrica, indexadas por el `orden` de la actividad
     * a la que pertenecen (hoja Rubrica, campo actividad_orden).
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
                // descarta en las sumativas. Las matrices que tenemos son sumativas.
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
     * @param list<array{nombre:string, puntos:float|int}> $columnas
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
     * estudiantes.xlsx (hoja única): los 38 alumnos con su paralelo. El RUT viene
     * sin dígito verificador.
     *
     * @return list<array{rut:int, nombre1:string, nombre2:?string, apellido1:string, apellido2:?string, email:?string, tipo_usuario:string, grupo:string}>
     */
    public function estudiantes(): array
    {
        $hoja = $this->primeraHoja('estudiantes.xlsx');
        return array_map(fn ($r) => [
            'rut'          => (int) $r['rut'],
            'nombre1'      => trim((string) $r['nombre1']),
            'nombre2'      => self::str($r['nombre2'] ?? null),
            'apellido1'    => trim((string) $r['apellido1']),
            'apellido2'    => self::str($r['apellido2'] ?? null),
            'email'        => self::str($r['email'] ?? null),
            'tipo_usuario' => strtolower(trim((string) ($r['tipo_usuario'] ?? 'estudiante'))),
            'grupo'        => strtoupper(trim((string) $r['grupo'])),
        ], $hoja);
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

    private function primeraHoja(string $archivo): array
    {
        if (isset($this->cache[$archivo]['__primera'])) {
            return $this->cache[$archivo]['__primera'];
        }
        return $this->cache[$archivo]['__primera'] = $this->filasDe($this->abrir($archivo)->getSheet(0));
    }

    private function abrir(string $archivo): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $ruta = self::DIR . DIRECTORY_SEPARATOR . $archivo;
        if (!is_file($ruta)) {
            throw new RuntimeException("No se encuentra {$ruta}.");
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
        $filas = $this->filas('programa_dm095.xlsx', $hoja);
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

    /** Número de una celda; int cuando no tiene decimales, para que el JSON diga 7 y no 7.0. */
    private static function num(mixed $v, string $error): float|int
    {
        $s = self::str($v);
        if ($s === null || !is_numeric($s)) {
            throw new RuntimeException($error);
        }
        $n = (float) $s;
        return $n == (int) $n ? (int) $n : $n;
    }

    /** Texto normalizado para comparar encabezados y criterios entre hojas (la matriz escribe «Muy bueno» en una y «Muy Bueno» en otra). */
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
