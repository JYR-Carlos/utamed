<?php

namespace Tests\Unit\Services;

use App\Services\SyllabusStructure;
use App\Models\Curso\Programa;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\ProgramaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Usuario $user;
    protected Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = Usuario::factory()->create();
        $this->curso = Curso::factory()->create();
        $this->curso->load('asignacionPlan.asignatura');
    }

    /** @test */
    public function can_generate_programa_with_syllabus_structure()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);

        $this->assertInstanceOf(Programa::class, $programa);
        $this->assertTrue($programa->es_actual);
        $this->assertEquals(1, $programa->version_programa);
        $this->assertNotNull($programa->data_syllabus);
    }

    /** @test */
    public function syllabus_contains_metadata()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        $data = $programa->data_syllabus;

        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('asignatura', $data['metadata']);
        $this->assertArrayHasKey('curso', $data['metadata']);
        $this->assertArrayHasKey('categoria', $data['metadata']);
    }

    /** @test */
    public function syllabus_contains_six_secciones()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        $data = $programa->data_syllabus;

        $this->assertArrayHasKey('secciones', $data);
        $this->assertCount(6, $data['secciones']);

        // Verificar nombres de secciones
        $expected = [
            'Descripción de la Asignatura',
            'Competencias',
            'Resultados de Aprendizaje',
            'Contenidos',
            'Metodología',
            'Evaluación',
        ];

        foreach ($data['secciones'] as $index => $seccion) {
            $this->assertEquals($expected[$index], $seccion['nombre_seccion']);
        }
    }

    /** @test */
    public function asignatura_metadata_includes_hours()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        $asignatura = $programa->data_syllabus['metadata']['asignatura'];

        $this->assertArrayHasKey('horas', $asignatura);
        $this->assertArrayHasKey('catedra', $asignatura['horas']);
        $this->assertArrayHasKey('taller', $asignatura['horas']);
        $this->assertArrayHasKey('laboratorio', $asignatura['horas']);
    }

    /** @test */
    public function categoria_includes_type_and_description()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        $categoria = $programa->data_syllabus['metadata']['categoria'];

        $this->assertArrayHasKey('tipo', $categoria);
        $this->assertArrayHasKey('descripcion', $categoria);
        $this->assertNotEmpty($categoria['tipo']);
        $this->assertNotEmpty($categoria['descripcion']);
    }

    /**
     * Overrides de secciones en el formato asociativo real (I..IX con 'contenido')
     * que produce el wizard, para un syllabus BASICO.
     */
    private function basicoOverrides(): array
    {
        return [
            'tipo_syllabus' => 'BASICO',
            'secciones' => [
                'I' => ['contenido' => [
                    'nombre_asignatura' => 'Programación I',
                    'codigo' => 'TST101',
                    'creditos_sct' => 5,
                    'horas' => ['catedra' => 3, 'taller' => 1, 'laboratorio' => 0],
                    'categoria' => 'Obligatorio',
                ]],
                'II' => ['contenido' => ['texto' => 'Presentación inicial']],
                'VI' => ['contenido' => ['unidades' => []]],
                'VII' => ['contenido' => ['actividades' => []]],
                'VIII' => ['contenido' => ['recursos' => []]],
            ],
        ];
    }

    /** @test */
    public function can_update_seccion_content()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user, $this->basicoOverrides());

        ProgramaService::updateSeccion($programa, 'II', ['texto' => 'Presentación actualizada']);

        $programa->refresh();
        $this->assertEquals(
            'Presentación actualizada',
            $programa->data_syllabus['secciones']['II']['contenido']['texto']
        );
    }

    /** @test */
    public function updating_an_unknown_seccion_throws_exception()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user, $this->basicoOverrides());

        $this->expectException(\Exception::class);
        ProgramaService::updateSeccion($programa, 'III', ['texto' => 'no debería existir en BASICO']);
    }

    /** @test */
    public function can_change_programa_status()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);

        ProgramaService::changeStatus($programa, 'COMPLETO');
        $this->assertEquals('COMPLETO', $programa->estado);

        ProgramaService::changeStatus($programa, 'APROBADO');
        $this->assertEquals('APROBADO', $programa->estado);
    }

    /** @test */
    public function throws_exception_for_invalid_status()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);

        $this->expectException(\InvalidArgumentException::class);
        ProgramaService::changeStatus($programa, 'ESTADO_INVALIDO');
    }

    /** @test */
    public function new_version_increments_for_existing_programa()
    {
        $programa1 = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        $this->assertEquals(1, $programa1->version_programa);

        $programa2 = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        $this->assertEquals(2, $programa2->version_programa);

        // primer programa ya no es actual
        $programa1->refresh();
        $this->assertFalse($programa1->es_actual);
    }

    /** @test */
    public function export_returns_readable_format()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        $export = ProgramaService::export($programa);

        $this->assertArrayHasKey('programa_id', $export);
        $this->assertArrayHasKey('version', $export);
        $this->assertArrayHasKey('estado', $export);
        $this->assertArrayHasKey('metadata', $export);
        $this->assertArrayHasKey('secciones', $export);
    }

    /** @test */
    public function syllabus_structure_for_static_method_works()
    {
        $estructura = SyllabusStructure::for($this->curso);

        $this->assertIsArray($estructura);
        $this->assertArrayHasKey('metadata', $estructura);
        $this->assertArrayHasKey('secciones', $estructura);
    }

    /** @test */
    public function can_create_syllabus_with_custom_categoria()
    {
        $syllabus = (new SyllabusStructure($this->curso))
            ->withAsignatura()
            ->withSecciones()
            ->withCategoriaFromString('ELECTIVO')
            ->build();

        $this->assertEquals('ELECTIVO', $syllabus['metadata']['categoria']['tipo']);
        $this->assertStringContainsString('electiva', strtolower($syllabus['metadata']['categoria']['descripcion']));
    }

    /** @test */
    public function timestamp_is_added_to_syllabus()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);
        
        $this->assertArrayHasKey('timestamp', $programa->data_syllabus);
        $this->assertNotEmpty($programa->data_syllabus['timestamp']);
    }
}
