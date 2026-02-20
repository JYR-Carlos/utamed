<?php

namespace Tests\Unit\Services;

use App\Services\SyllabusStructure;
use App\Models\Administrativo\Programa;
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

    /** @test */
    public function can_update_seccion_content()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);

        $newContent = [
            ['texto_contenido' => 'Competencia 1: Programación básica', 'orden_item' => 1],
            ['texto_contenido' => 'Competencia 2: Algoritmos', 'orden_item' => 2],
        ];

        ProgramaService::updateSeccion($programa, 2, $newContent);

        $programa->refresh();
        $secciones = $programa->data_syllabus['secciones'];
        $competenciasSeccion = collect($secciones)->firstWhere('orden', 2);

        $this->assertCount(2, $competenciasSeccion['contenidos']);
        $this->assertEquals('Competencia 1: Programación básica', $competenciasSeccion['contenidos'][0]['texto_contenido']);
    }

    /** @test */
    public function can_add_content_to_seccion()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);

        ProgramaService::addContentToSeccion(
            $programa,
            5, // Metodología
            'Metodología constructivista con enfoque práctico'
        );

        $programa->refresh();
        $secciones = $programa->data_syllabus['secciones'];
        $metodologiaSeccion = collect($secciones)->firstWhere('orden', 5);

        $this->assertCount(1, $metodologiaSeccion['contenidos']);
        $this->assertStringContainsString('constructivista', $metodologiaSeccion['contenidos'][0]['texto_contenido']);
    }

    /** @test */
    public function can_change_programa_status()
    {
        $programa = ProgramaService::generateProgramaWithSyllabus($this->curso, $this->user);

        ProgramaService::changeStatus($programa, 'EN_REVISION');
        $this->assertEquals('EN_REVISION', $programa->estado);

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
