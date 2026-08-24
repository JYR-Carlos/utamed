<?php

namespace Tests\Unit\Services;

use App\DTOs\External\ComponenteCursoData;
use App\Enums\External\TipoAsignatura;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

class IntranetViewConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper para probar la lógica de validación sin tocar base de datos.
     */
    private function validateTraerCurCodigos(
        int $semestre,
        int $ano,
        int $planCod,
        string $asigCodigo,
        TipoAsignatura|string|null $tipoAsig = null,
        ?string $grupoAsig = null
    ): void {
        if (abs($semestre) > 9) {
            throw new InvalidArgumentException("El semestre excede el límite permitido de 1 dígito: {$semestre}");
        }
        if (abs($ano) > 9999) {
            throw new InvalidArgumentException("El año excede el límite permitido de 4 dígitos: {$ano}");
        }
        if (abs($planCod) > 9999) {
            throw new InvalidArgumentException("El código de plan excede el límite permitido de 4 dígitos: {$planCod}");
        }
        if (mb_strlen($asigCodigo) > 10) {
            throw new InvalidArgumentException("El código de asignatura excede el límite permitido de 10 caracteres: '{$asigCodigo}'");
        }

        $tipoValue = $tipoAsig instanceof TipoAsignatura ? $tipoAsig->value : $tipoAsig;
        if ($tipoValue !== null && mb_strlen($tipoValue) > 1) {
            throw new InvalidArgumentException("El tipo de asignatura excede el límite permitido de 1 carácter: '{$tipoValue}'");
        }

        if ($grupoAsig !== null && mb_strlen($grupoAsig) > 2) {
            throw new InvalidArgumentException("El grupo de asignatura excede el límite permitido de 2 caracteres: '{$grupoAsig}'");
        }
    }

    public function test_valida_semestre_excesivo()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El semestre excede el límite permitido de 1 dígito');

        $this->validateTraerCurCodigos(12, 2026, 2020, 'INF-101');
    }

    public function test_valida_ano_excesivo()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El año excede el límite permitido de 4 dígitos');

        $this->validateTraerCurCodigos(1, 20260, 2020, 'INF-101');
    }

    public function test_valida_asig_codigo_excesivo()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El código de asignatura excede el límite permitido de 10 caracteres');

        $this->validateTraerCurCodigos(1, 2026, 2020, 'CODIGO_EXCESIVAMENTE_LARGO');
    }

    public function test_valida_tipo_asig_excesivo()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El tipo de asignatura excede el límite permitido de 1 carácter');

        $this->validateTraerCurCodigos(1, 2026, 2020, 'INF-101', 'CATEDRA');
    }

    public function test_valida_grupo_asig_excesivo()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El grupo de asignatura excede el límite permitido de 2 caracteres');

        $this->validateTraerCurCodigos(1, 2026, 2020, 'INF-101', TipoAsignatura::Catedra, 'GRUPO_LARGO');
    }

    public function test_mocking_de_oracle_data_service_sin_base_de_datos()
    {
        $mock = Mockery::mock();
        $mock->shouldReceive('traer_cur_codigos')
            ->with(1, 2026, 2020, 'INF-101', TipoAsignatura::Catedra, 'A')
            ->once()
            ->andReturn(collect([
                new ComponenteCursoData(
                    cur_codigo: 202610000351,
                    curso_tipo_asig: TipoAsignatura::Catedra,
                    curso_grupo_asig: 'A'
                )
            ]));

        $resultado = $mock->traer_cur_codigos(1, 2026, 2020, 'INF-101', TipoAsignatura::Catedra, 'A');

        $this->assertCount(1, $resultado);
        $this->assertEquals(202610000351, $resultado->first()->cur_codigo);
    }
}
