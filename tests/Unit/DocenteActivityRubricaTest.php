<?php

namespace Tests\Unit;

use App\Http\Controllers\Docente\DocenteActivityController;
use Tests\TestCase;
use ReflectionClass;

class DocenteActivityRubricaTest extends TestCase
{
    private DocenteActivityController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new DocenteActivityController();
    }

    private function getPrivateMethod(string $name)
    {
        $ref = new ReflectionClass(DocenteActivityController::class);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    public function test_calcular_nota_individual_handles_float_decimas_properly()
    {
        $method = $this->getPrivateMethod('calcularNotaIndividual');

        // nota grupal 5.5 + 0.3 decimas = 5.8
        $this->assertEquals(5.8, $method->invoke($this->controller, 5.5, 0.3));

        // nota grupal 5.5 - 0.2 decimas = 5.3
        $this->assertEquals(5.3, $method->invoke($this->controller, 5.5, -0.2));

        // upper clamp 7.0
        $this->assertEquals(7.0, $method->invoke($this->controller, 6.8, 0.5));

        // lower clamp 1.0
        $this->assertEquals(1.0, $method->invoke($this->controller, 1.2, -0.5));

        // null grupal returns null
        $this->assertNull($method->invoke($this->controller, null, 0.3));
    }

    public function test_regla_puntajes_monotonos_accepts_strictly_ascending_scores()
    {
        $rule = $this->getPrivateMethod('reglaPuntajesMonotonos')->invoke($this->controller);
        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
        };

        $cols = [
            ['puntos' => 0],
            ['puntos' => 10],
            ['puntos' => 20],
            ['puntos' => 30],
        ];
        $rule('columnas', $cols, $fail);
        $this->assertFalse($failed, 'Ascending scores should pass validation');
    }

    public function test_regla_puntajes_monotonos_accepts_strictly_descending_scores()
    {
        $rule = $this->getPrivateMethod('reglaPuntajesMonotonos')->invoke($this->controller);
        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
        };

        $cols = [
            ['puntos' => 30],
            ['puntos' => 20],
            ['puntos' => 10],
            ['puntos' => 0],
        ];
        $rule('columnas', $cols, $fail);
        $this->assertFalse($failed, 'Descending scores should pass validation');
    }

    public function test_regla_puntajes_monotonos_rejects_disordered_and_duplicate_scores()
    {
        $rule = $this->getPrivateMethod('reglaPuntajesMonotonos')->invoke($this->controller);

        // Disordered
        $failed = false;
        $cols = [
            ['puntos' => 10],
            ['puntos' => 30],
            ['puntos' => 20],
        ];
        $rule('columnas', $cols, function () use (&$failed) { $failed = true; });
        $this->assertTrue($failed, 'Disordered scores should fail');

        // Duplicates
        $failed = false;
        $colsDuplicates = [
            ['puntos' => 10],
            ['puntos' => 20],
            ['puntos' => 20],
        ];
        $rule('columnas', $colsDuplicates, function () use (&$failed) { $failed = true; });
        $this->assertTrue($failed, 'Duplicate scores should fail');
    }

    public function test_regla_escala_formativa_alcanzable()
    {
        $rule = $this->getPrivateMethod('reglaEscalaFormativaAlcanzable')->invoke($this->controller, 30.0);

        // Achievable scale thresholds: <= 30
        $failed = false;
        $escalaValida = [
            ['puntaje_minimo' => 20, 'evaluacion' => 'Aprobado'],
            ['puntaje_minimo' => 0, 'evaluacion' => 'Reprobado'],
        ];
        $rule('escala', $escalaValida, function () use (&$failed) { $failed = true; });
        $this->assertFalse($failed, 'Valid scale should pass');

        // Unachievable scale threshold: 60 > 30
        $failed = false;
        $escalaInvalida = [
            ['puntaje_minimo' => 60, 'evaluacion' => 'Aprobado'],
            ['puntaje_minimo' => 0, 'evaluacion' => 'Reprobado'],
        ];
        $rule('escala', $escalaInvalida, function () use (&$failed) { $failed = true; });
        $this->assertTrue($failed, 'Unachievable scale threshold should fail');
    }
}
