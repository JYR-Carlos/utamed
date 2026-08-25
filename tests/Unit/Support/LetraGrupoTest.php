<?php

namespace Tests\Unit\Support;

use App\Support\LetraGrupo;
use PHPUnit\Framework\TestCase;

/**
 * `LetraGrupo::fromIndice()` debe ser un espejo exacto de `intToLetters()`
 * en cursoWizardModal.svelte — si divergen, el paralelo que ve la persona en
 * pantalla no coincide con el que usa el backend para filtrar en Intranet.
 */
class LetraGrupoTest extends TestCase
{
    public function test_indices_bajos_se_mapean_a_letras_simples(): void
    {
        $this->assertSame('A', LetraGrupo::fromIndice(1));
        $this->assertSame('B', LetraGrupo::fromIndice(2));
        $this->assertSame('Z', LetraGrupo::fromIndice(26));
    }

    public function test_indices_sobre_26_se_mapean_a_letras_dobles_estilo_excel(): void
    {
        $this->assertSame('AA', LetraGrupo::fromIndice(27));
        $this->assertSame('AZ', LetraGrupo::fromIndice(52));
        $this->assertSame('BA', LetraGrupo::fromIndice(53));
    }

    public function test_indice_nulo_o_cero_da_cadena_vacia(): void
    {
        $this->assertSame('', LetraGrupo::fromIndice(null));
        $this->assertSame('', LetraGrupo::fromIndice(0));
    }
}
