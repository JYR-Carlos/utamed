<?php

namespace Tests\Unit\Services;

use App\DTOs\External\AlumnoIntranetData;
use App\Models\External\VwAlumno;
use PHPUnit\Framework\TestCase;

class EstudianteCreateFromIntranetLogicTest extends TestCase
{
    public function test_alumno_intranet_data_normaliza_email_con_espacios_y_mayusculas(): void
    {
        $dto = new AlumnoIntranetData(
            alum_rut: 12345678,
            alum_digito: '9',
            alum_nombre: 'JUAN',
            alum_apellido_pat: 'PEREZ',
            alum_email: "  \t Juan.Perez_2026@ALUMNOS.UTA.CL \n "
        );

        $this->assertSame('juan.perez_2026@alumnos.uta.cl', $dto->alum_email);
        $this->assertSame('juan.perez_2026@alumnos.uta.cl', $dto->email);
    }

    public function test_alumno_intranet_data_convierte_espacios_en_blanco_a_null(): void
    {
        $dto = new AlumnoIntranetData(
            alum_rut: 12345678,
            alum_digito: '9',
            alum_nombre: 'JUAN',
            alum_apellido_pat: 'PEREZ',
            alum_email: "   \t  "
        );

        $this->assertNull($dto->alum_email);
        $this->assertNull($dto->email);
    }

    public function test_alumno_intranet_data_prioriza_alum_email_sobre_email(): void
    {
        $dto = new AlumnoIntranetData(
            alum_rut: 12345678,
            alum_digito: '9',
            alum_nombre: 'JUAN',
            alum_apellido_pat: 'PEREZ',
            alum_email: 'oficial@uta.cl',
            email: 'legacy@uta.cl'
        );

        $this->assertSame('oficial@uta.cl', $dto->alum_email);
        $this->assertSame('oficial@uta.cl', $dto->email);
    }

    public function test_vw_alumno_normaliza_correo_y_maneja_nulo_o_vacio(): void
    {
        $alumnoConEmail = new VwAlumno(['ALUM_EMAIL' => '  ESTUDIANTE@UTA.CL  ']);
        $this->assertSame('estudiante@uta.cl', $alumnoConEmail->alum_email);
        $this->assertSame('estudiante@uta.cl', $alumnoConEmail->ALUM_EMAIL);

        $alumnoVacio = new VwAlumno(['ALUM_EMAIL' => '   ']);
        $this->assertNull($alumnoVacio->alum_email);
        $this->assertNull($alumnoVacio->ALUM_EMAIL);

        $alumnoNulo = new VwAlumno(['ALUM_EMAIL' => null]);
        $this->assertNull($alumnoNulo->alum_email);
        $this->assertNull($alumnoNulo->ALUM_EMAIL);
    }

    public function test_vw_alumno_lee_alum_correo_como_fallback(): void
    {
        $alumno = new VwAlumno(['ALUM_CORREO' => 'CORREO_ALTERNO@UTA.CL']);
        $this->assertSame('correo_alterno@uta.cl', $alumno->alum_email);
    }
}
