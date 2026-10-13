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

    public function test_actividad_esta_cerrada_retorna_boolean_segun_estado_base()
    {
        // 1. Actividad vencida visible -> estaCerrada() debe ser true
        $actVencida = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->subDays(10),
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);
        $this->assertTrue($actVencida->estaCerrada());

        // 2. Actividad vigente visible -> estaCerrada() debe ser false
        $actVigente = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->addDays(5),
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);
        $this->assertFalse($actVigente->estaCerrada());

        // 3. Actividad no visible aunque vencida -> 'NO VISIBLE', estaCerrada() es false
        $actNoVisible = new Actividad([
            'visible' => false,
            'fecha_limite' => Carbon::now()->subDays(10),
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);
        $this->assertFalse($actNoVisible->estaCerrada());
    }

    public function test_actividad_asignada_grupo_esta_cerrada_delega_en_calcular_estado_grupo()
    {
        // Actividad con fecha vencida hace 2 días y holgura base 0 (cerrada base)
        $actividad = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->subDays(2),
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);

        // Grupo sin holgura personal -> estaCerrada() debe ser true
        $grupoSinHolgura = new ActividadAsignadaGrupo([
            'nro_dias_adicionales_para_bloqueo_personal' => 0,
        ]);
        $this->assertTrue($grupoSinHolgura->estaCerrada($actividad));

        // Grupo con holgura personal suficiente -> estaCerrada() debe ser false
        $grupoConHolgura = new ActividadAsignadaGrupo([
            'nro_dias_adicionales_para_bloqueo_personal' => 5,
        ]);
        $this->assertFalse($grupoConHolgura->estaCerrada($actividad));
    }

    public function test_actividad_asignada_grupo_esta_pendiente_entrega_descarta_cerradas_y_sin_entrega()
    {
        // 1. Actividad cerrada -> no debe estar pendiente de entrega
        $actCerrada = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->subDays(5),
            'nro_dias_adicionales_para_bloqueo' => 0,
            'tipo_entrega' => 'Con entrega',
        ]);
        $grupo = new ActividadAsignadaGrupo();
        $this->assertFalse($grupo->estaPendienteEntrega($actCerrada));

        // 2. Actividad sin entrega obligatoria -> no está pendiente de entrega
        $actSinEntrega = new Actividad([
            'visible' => true,
            'fecha_limite' => Carbon::now()->addDays(5),
            'nro_dias_adicionales_para_bloqueo' => 0,
            'tipo_entrega' => 'Sin entrega',
        ]);
        $this->assertFalse($grupo->estaPendienteEntrega($actSinEntrega));
    }
}
