<?php

namespace Tests\Unit\Agenda;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use Carbon\Carbon;
use Tests\TestCase;

class ActividadEstadoTest extends TestCase
{
    public function test_actividad_calcular_estado_base_evalua_correctamente_matriz_de_estados()
    {
        // 1. !visible + Pre-Fin (+holgura base) -> PLANIFICADA
        $act1 = new Actividad([
            'visible' => false,
            'fecha_limite' => Carbon::now()->addDays(5),
            'nro_dias_adicionales_para_bloqueo' => 2,
        ]);
        $this->assertEquals('PLANIFICADA', $act1->calcularEstadoBase());

        // 2. visible + Pre-Fin (+holgura base) -> ACTIVA
        $act2 = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->addDays(5),
            'nro_dias_adicionales_para_bloqueo' => 2,
        ]);
        $this->assertEquals('ACTIVA', $act2->calcularEstadoBase());

        // 3. visible + Post-Fin (+holgura base) -> CERRADA
        $act3 = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->subDays(10),
            'nro_dias_adicionales_para_bloqueo' => 2,
        ]);
        $this->assertEquals('CERRADA', $act3->calcularEstadoBase());

        // 4. !visible + Post-Fin (+holgura base) -> NO VISIBLE
        $act4 = new Actividad([
            'visible' => false,
            'fecha_limite' => Carbon::now()->subDays(10),
            'nro_dias_adicionales_para_bloqueo' => 2,
        ]);
        $this->assertEquals('NO VISIBLE', $act4->calcularEstadoBase());
    }

    public function test_actividad_asignada_grupo_calcular_estado_grupo_combina_holgura_base_y_personal()
    {
        // Actividad con fecha vencida hace 2 días y holgura base de 1 día (estado base: CERRADA)
        $actividad = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->subDays(2),
            'nro_dias_adicionales_para_bloqueo' => 1,
        ]);
        $this->assertEquals('CERRADA', $actividad->calcularEstadoBase());

        // Grupo con holgura personal de +3 días adicionales -> Holgura total = 4 días -> Aún ACTIVA
        $grupoConHolgura = new ActividadAsignadaGrupo([
            'nro_dias_adicionales_para_bloqueo_personal' => 3,
        ]);
        $this->assertEquals('ACTIVA', $grupoConHolgura->calcularEstadoGrupo($actividad));

        // Grupo sin holgura personal (0 días adicionales) -> Estado permanece CERRADA
        $grupoSinHolgura = new ActividadAsignadaGrupo([
            'nro_dias_adicionales_para_bloqueo_personal' => 0,
        ]);
        $this->assertEquals('CERRADA', $grupoSinHolgura->calcularEstadoGrupo($actividad));
    }

    public function test_actividad_no_visible_mantiene_planificada_o_no_visible_en_estado_grupo()
    {
        // Actividad no visible en pre-fin
        $actividadNoVis = new Actividad([
            'visible' => false,
            'fecha_limite' => Carbon::now()->addDays(5),
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);
        $grupo = new ActividadAsignadaGrupo([
            'nro_dias_adicionales_para_bloqueo_personal' => 2,
        ]);
        $this->assertEquals('PLANIFICADA', $grupo->calcularEstadoGrupo($actividadNoVis));
    }
}
