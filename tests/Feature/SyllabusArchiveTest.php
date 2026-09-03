<?php

namespace Tests\Feature;

use App\Exceptions\Archive\FileValidationException;
use App\Http\Requests\Archive\BibliografiaFileRequest;
use App\Models\Curso\Bibliografia;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use App\Services\Archive\Handlers\SyllabusArchiveHandler;
use App\Services\Archive\SyllabusArchiveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class SyllabusArchiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.storage.disk', 'local_archives'));
    }

    public function test_bibliografia_file_request_validation_rules(): void
    {
        $request = new BibliografiaFileRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('archivo', $rules);
        $this->assertArrayHasKey('id_curso', $rules);
        $this->assertArrayHasKey('id_unidad', $rules);
        $this->assertArrayHasKey('id_programa', $rules);
        $this->assertArrayHasKey('nombre_archivo', $rules);
    }

    public function test_syllabus_archive_service_prevalidate_accepts_pdf(): void
    {
        $service = new SyllabusArchiveService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('preValidate');
        $method->setAccessible(true);

        $file = UploadedFile::fake()->create('test_book.pdf', 1000, 'application/pdf');

        // Should not throw exception
        $method->invoke($service, $file, 'test-archive-id');
        $this->assertTrue(true);
    }

    public function test_syllabus_archive_service_prevalidate_accepts_docx(): void
    {
        $service = new SyllabusArchiveService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('preValidate');
        $method->setAccessible(true);

        $file = UploadedFile::fake()->create(
            'documento.docx',
            500,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $method->invoke($service, $file, 'test-archive-id');
        $this->assertTrue(true);
    }

    public function test_syllabus_archive_service_prevalidate_rejects_unsupported_type(): void
    {
        $this->expectException(\Throwable::class);

        $service = new SyllabusArchiveService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('preValidate');
        $method->setAccessible(true);

        $file = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

        $method->invoke($service, $file, 'test-archive-id');
    }

    public function test_syllabus_archive_handler_build_path(): void
    {
        $curso = new Curso([
            'nombre' => 'Medicina Interna I',
            'agno_real' => 2026,
            'semestre_real' => 1,
        ]);
        $curso->id_curso = 10;

        $programa = new Programa([
            'version_programa' => '1.0',
        ]);
        $programa->id_programa = 5;

        $unidad = new Unidad([
            'num_unidad' => 2,
            'nombre' => 'Cardiología Clínica',
        ]);

        $path = SyllabusArchiveHandler::buildPath($curso, $unidad, $programa);

        $this->assertEquals('2026-s1/medicina-interna-i/programa-10/bibliografias/u2-cardiologia-clinica', $path);
    }

    public function test_syllabus_archive_handler_build_path_fallback_when_draft(): void
    {
        $curso = new Curso([
            'nombre' => 'Cirugía General',
            'agno_real' => 2026,
            'semestre_real' => 2,
        ]);
        $curso->id_curso = 20;

        $path = SyllabusArchiveHandler::buildPath($curso, null, null);

        $this->assertEquals('2026-s2/cirugia-general/borrador/bibliografias/general', $path);
    }
}
