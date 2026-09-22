<?php

namespace Database\Seeders\Dm069;

/**
 * Arma las nueve secciones de un syllabus COMPLETO para DM069 a partir de los
 * Excel, con la forma que valida SyllabusRules::completo() y que consumen los
 * DTO de App\Syllabus (cada sección envuelta en ['contenido' => ...]).
 *
 * En producción el paralelo A ya tiene un programa y el B no tiene ninguno, así
 * que lo normal es que B clone el de A. Este builder es el respaldo para cuando
 * no hay programa hermano del que clonar (caso local, o si el de A se borra).
 *
 * Lo que el EVEA no trae y COMPLETO exige con min:1 (estándares de desempeño)
 * se rellena con un texto que dice explícitamente que no estaba definido, nunca
 * con contenido inventado. A diferencia de DM095, DM069 sí declara competencia
 * genérica y sí declara una evaluación diagnóstica.
 */
final class Dm069SyllabusBuilder
{
    public const NO_DEFINIDO_EVEA = 'No definido en el programa de origen (EVEA).';

    public function __construct(private readonly Dm069Datos $datos)
    {
    }

    /**
     * @param  list<array<string, mixed>>  $bibliografias  entradas ya con la forma de VIII.bibliografias[]
     * @return array<string, array{contenido: array<string, mixed>}>
     */
    public function secciones(array $bibliografias = []): array
    {
        return [
            'I'    => ['contenido' => $this->seccionI()],
            'II'   => ['contenido' => $this->seccionII()],
            'III'  => ['contenido' => ['texto' => self::NO_DEFINIDO_EVEA]],
            'IV'   => ['contenido' => $this->seccionIV()],
            'V'    => ['contenido' => $this->seccionV()],
            'VI'   => ['contenido' => $this->seccionVI()],
            'VII'  => ['contenido' => $this->seccionVII()],
            'VIII' => ['contenido' => ['bibliografias' => $bibliografias, 'recursos' => []]],
            'IX'   => ['contenido' => $this->seccionIX()],
        ];
    }

    private function seccionI(): array
    {
        $id = $this->datos->identificacion();

        return [
            'nombre_asignatura' => (string) $id['nombre_asignatura'],
            'codigo'            => (string) $id['codigo'],
            'creditos_sct'      => (int) $id['creditos_sct'],
            'horas'             => [
                'catedra'     => (int) $id['horas_catedra'],
                'taller'      => (int) $id['horas_taller'],
                'laboratorio' => (int) $id['horas_laboratorio'],
                'dirigidas'   => (int) $id['horas_dirigidas'],
                'autonomas'   => (int) $id['horas_autonomas'],
            ],
            'categoria'         => (string) $id['categoria'],
        ];
    }

    private function seccionII(): array
    {
        $textos = $this->datos->textosSecciones();
        // Descripción del curso + Propósito formativo, como dos párrafos.
        return ['texto' => implode("\n\n", $textos['secciones.II.contenido.texto'] ?? [])];
    }

    private function seccionIV(): array
    {
        $textos = $this->datos->textosSecciones();
        $especificas = $textos['secciones.IV.contenido.competencias_especificas[0].titulo'] ?? [];
        $genericas = $textos['secciones.IV.contenido.competencias_genericas[0].titulo'] ?? [];
        $sub = $textos['secciones.IV.contenido.subcompetencias[0].titulo'] ?? [];

        return [
            'competencias_especificas' => array_map(fn ($t) => ['titulo' => $t], $especificas),
            // COMPLETO exige al menos una. El EVEA de DM069 sí declara una.
            'competencias_genericas'   => $genericas
                ? array_map(fn ($t) => ['titulo' => $t], $genericas)
                : [['titulo' => self::NO_DEFINIDO_EVEA]],
            'subcompetencias'          => array_map(fn ($t) => ['titulo' => $t], $sub),
        ];
    }

    private function seccionV(): array
    {
        $textos = $this->datos->textosSecciones();
        $items = $textos['secciones.V.contenido.items[0].titulo'] ?? [];

        // El EVEA sólo da el título de la evaluación diagnóstica, sin descripción.
        return ['items' => array_map(fn ($t) => [
            'titulo'      => $t,
            'descripcion' => self::NO_DEFINIDO_EVEA,
        ], $items ?: [self::NO_DEFINIDO_EVEA])];
    }

    private function seccionVI(): array
    {
        $contenidos = $this->datos->contenidos();
        $aprendizajes = $this->datos->aprendizajes();

        $unidades = [];
        foreach ($this->datos->unidades() as $u) {
            $unidades[] = [
                'numero'                 => $u['numero'],
                'titulo'                 => self::tituloUnidad($u['numero'], $u['titulo']),
                'contenidos_items'       => array_map(fn ($i) => ['item' => $i], $contenidos[$u['numero']] ?? []),
                'resultados_aprendizaje' => array_map(fn ($r) => ['resultado' => $r], $aprendizajes[$u['numero']] ?? []),
            ];
        }

        return ['unidades' => $unidades];
    }

    private function seccionVII(): array
    {
        $items = [];
        foreach ($this->datos->aprendizajes() as $num => $resultados) {
            foreach ($resultados as $r) {
                $items[] = ['resultado' => "U{$num}: {$r}"];
            }
        }

        // La app guarda UNA metodología por programa; el EVEA la detalla por
        // unidad, así que se concatenan las tres con su encabezado.
        $bloques = [];
        $unidades = collect($this->datos->unidades())->keyBy('numero');
        foreach ($this->datos->metodologia() as $num => $estrategias) {
            $lineas = ["Unidad {$num}: " . ($unidades[$num]['titulo'] ?? '')];
            foreach ($estrategias as $e) {
                $lineas[] = "- {$e['estrategia']}: {$e['texto']}";
            }
            $bloques[] = implode("\n", $lineas);
        }

        // El detalle por actividad sale del paralelo A: nombres, tipo y descripción
        // son iguales en los dos; sólo cambian código EVEA y vencimiento.
        $evaluacion = ['Nota final del módulo (EVEA): promedio de las sumativas × 100 % + promedio de las formativas × 0 %.'];
        foreach ($this->datos->actividades('A') as $a) {
            $evaluacion[] = sprintf(
                '- %s (%s%s)',
                $a['nombre'],
                ucfirst(strtolower($a['tipo_actividad'])),
                $a['ponderacion'] > 0 ? ", {$a['ponderacion']} %" : ''
            );
        }

        return [
            'resultados_aprendizaje' => ['titulo' => 'Resultados de Aprendizaje', 'items' => $items],
            'metodologia'            => ['titulo' => 'Metodología', 'tipo_estrategia' => implode("\n\n", $bloques)],
            'evaluacion'             => ['titulo' => 'Evaluación', 'tipo_evaluacion' => implode("\n", $evaluacion)],
        ];
    }

    private function seccionIX(): array
    {
        // DM069 declara horas de Taller y de Laboratorio (c=0, t=2, l=2), y los dos
        // componentes existen en producción. El EVEA es un módulo único y NO reparte
        // la nota entre ellos: el 50/50 es propuesta nuestra, por partes iguales de
        // horas. Si el equipo decide otra cosa, se cambia aquí y en la columna
        // componente_tipo de actividades_dm069.xlsx.
        $id = $this->datos->identificacion();
        $taller = (int) ($id['horas_taller'] ?? 2);
        $laboratorio = (int) ($id['horas_laboratorio'] ?? 2);
        $total = max(1, $taller + $laboratorio);

        return [
            'descripcion'          => sprintf(
                'Dos componentes: Taller (%d h/semana) y Laboratorio (%d h/semana). El programa de origen (EVEA) es un módulo único y no reparte la nota entre ellos; la ponderación de esta tabla es proporcional a las horas y debe confirmarla el equipo docente. Trabajo autónomo: %d h semestrales.',
                $taller, $laboratorio, (int) ($id['horas_autonomas'] ?? 48)
            ),
            'ponderacion_optativa' => ['porcentaje' => 0],
            'tabla_componentes'    => [
                [
                    'componente'             => 'Taller',
                    'porcentaje'             => (int) round($taller * 100 / $total),
                    'genera_acta'            => true,
                    'aprobacion_obligatoria' => false,
                    'asistencia_obligatoria' => 75,
                ],
                [
                    'componente'             => 'Laboratorio',
                    'porcentaje'             => 100 - (int) round($taller * 100 / $total),
                    'genera_acta'            => false,
                    'aprobacion_obligatoria' => false,
                    'asistencia_obligatoria' => 75,
                ],
            ],
        ];
    }

    /**
     * Formato con el que producción nombra las unidades («Unidad 1 : Título»). Se
     * replica para que A y B queden iguales y para que createUnidadesFromSyllabus
     * las reencuentre por nombre.
     */
    public static function tituloUnidad(int $numero, string $titulo): string
    {
        return "Unidad {$numero} : {$titulo}";
    }

    /** Descripción de unidad: todos los contenidos, uno por línea. */
    public static function descripcionUnidad(array $contenidos): ?string
    {
        return $contenidos ? implode("\n", $contenidos) : null;
    }
}
