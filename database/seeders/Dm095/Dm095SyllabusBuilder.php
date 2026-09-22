<?php

namespace Database\Seeders\Dm095;

/**
 * Arma las nueve secciones de un syllabus COMPLETO para DM095 a partir de los
 * Excel, con la forma que valida SyllabusRules::completo() y que consumen los
 * DTO de App\Syllabus (cada sección envuelta en ['contenido' => ...]).
 *
 * Producción ya tiene el programa de la sección A cargado a mano (COMPLETO,
 * APROBADO) y la B debe clonarlo; este builder es el respaldo cuando no hay un
 * programa hermano del que clonar (caso local, o si algún día borran el de A).
 *
 * Lo que el EVEA no trae y COMPLETO exige con min:1 (competencias genéricas,
 * evaluación diagnóstica, estándares) se rellena con un texto que dice
 * explícitamente que no estaba definido, nunca con contenido inventado.
 */
final class Dm095SyllabusBuilder
{
    public const NO_DEFINIDO_EVEA = 'No definido en el programa de origen (EVEA).';

    public function __construct(private readonly Dm095Datos $datos)
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
            'V'    => ['contenido' => ['items' => [[
                'titulo'      => 'Sin evaluación diagnóstica',
                'descripcion' => 'El EVEA declara: «No se han definido Evaluaciones Diagnósticas para este Curso».',
            ]]]],
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
        // Descripción del curso + Propósito formativo (propuesta del Excel: 2º párrafo).
        return ['texto' => implode("\n\n", $textos['secciones.II.contenido.texto'] ?? [])];
    }

    private function seccionIV(): array
    {
        $textos = $this->datos->textosSecciones();
        $especificas = $textos['secciones.IV.contenido.competencias_especificas[0].titulo'] ?? [];
        $sub = $textos['secciones.IV.contenido.subcompetencias[0].titulo'] ?? [];

        return [
            'competencias_especificas' => array_map(fn ($t) => ['titulo' => $t], $especificas),
            // COMPLETO exige al menos una; el EVEA no define ninguna.
            'competencias_genericas'   => [['titulo' => self::NO_DEFINIDO_EVEA]],
            'subcompetencias'          => array_map(fn ($t) => ['titulo' => $t], $sub),
        ];
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

        $evaluacion = ["Evaluación continua con retroalimentación: cada unidad contempla una actividad formativa previa a la sumativa, ambas mediadas por la retroalimentación docente."];
        foreach ($this->datos->actividades() as $a) {
            $evaluacion[] = sprintf('- %s (%s, %s)', $a['nombre'], ucfirst(strtolower($a['tipo_actividad'])), $a['descripcion'] ?? 'con envío de archivo');
        }

        return [
            'resultados_aprendizaje' => ['titulo' => 'Resultados de Aprendizaje', 'items' => $items],
            'metodologia'            => ['titulo' => 'Metodología', 'tipo_estrategia' => implode("\n\n", $bloques)],
            'evaluacion'             => ['titulo' => 'Evaluación', 'tipo_evaluacion' => implode("\n", $evaluacion)],
        ];
    }

    private function seccionIX(): array
    {
        // DM095 sólo declara horas de Taller (c=0, t=4, l=0): un componente al 100 %.
        // Los porcentajes de aprobación/asistencia son los que Intranet dejó en
        // curso.componente (60 % / 75 %).
        return [
            'descripcion'          => 'Componente único: Taller (4 h/semana). La nota final del curso es la nota del componente Taller. Trabajo autónomo: 104 h semestrales.',
            'ponderacion_optativa' => ['porcentaje' => 0],
            'tabla_componentes'    => [[
                'componente'             => 'Taller',
                'porcentaje'             => 100,
                'genera_acta'            => true,
                'aprobacion_obligatoria' => false,
                'asistencia_obligatoria' => 75,
            ]],
        ];
    }

    /**
     * Formato con el que producción ya nombró las unidades de la sección A
     * («Unidad 1 : Fundamentos y Entorno de Trabajo»). Se replica para que A y B
     * queden iguales y para que createUnidadesFromSyllabus las reencuentre por nombre.
     */
    public static function tituloUnidad(int $numero, string $titulo): string
    {
        return "Unidad {$numero} : {$titulo}";
    }

    /** Descripción de unidad como la dejó producción: todos los contenidos, uno por línea. */
    public static function descripcionUnidad(array $contenidos): ?string
    {
        return $contenidos ? implode("\n", $contenidos) : null;
    }
}
