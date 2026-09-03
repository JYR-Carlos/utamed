<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Curso\Bibliografia;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Str;
use App\Models\Curso\Programa;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;

class BibliografiaControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $bibliografiaLocal;
    protected $bibliografiaUta;

    protected function setUp(): void
    {
        parent::setUp();

        // Usar registros de prueba frescos
        $this->user = Usuario::query()->first();
        if (!$this->user) {
            $this->user = Usuario::factory()->create();
        }
        $this->actingAs($this->user);
        
        $programa = Programa::query()->first();
        if (!$programa) {
            $this->markTestSkipped('No hay programas en la base de datos para probar.');
        }

        $unidad = Unidad::query()->where('id_programa', $programa->id_programa)->first();
        if (!$unidad) {
            $unidad = Unidad::query()->create([
                'id_programa' => $programa->id_programa,
                'numero' => 99,
                'titulo' => 'Unidad Test',
            ]);
        }

        $docente = App\Models\Usuario\Docente::query()->first();
        if (!$docente) {
            $this->markTestSkipped('No hay docentes en la base de datos para probar.');
        }

        // Fake storage para probar archivos
        Storage::fake('local');
        Storage::put('bibliografias/test-file.pdf', 'dummy content');

        $this->bibliografiaLocal = Bibliografia::query()->create([
            'id_programa' => $programa->id_programa,
            'id_unidad' => $unidad->id_unidad,
            'titulo' => 'Libro de Anatomía',
            'autor' => 'Autor Test',
            'anio' => 2024,
            'es_bibliografia_uta' => false,
            'uuid_archivo' => 'test-file.pdf',
            'url' => null,
            'agregado_por' => $docente->id_docente,
        ]);

        $this->bibliografiaUta = Bibliografia::query()->create([
            'id_programa' => $programa->id_programa,
            'id_unidad' => $unidad->id_unidad,
            'titulo' => 'Recurso UTA Oficial',
            'autor' => 'UTA',
            'anio' => 2024,
            'es_bibliografia_uta' => true,
            'uuid_archivo' => null,
            'url' => null,
            'agregado_por' => $docente->id_docente,
        ]);
    }

    public function test_show_endpoint_returns_json_info()
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.bibliografias.show', $this->bibliografiaLocal->id_bibliografia));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id_bibliografia',
                    'titulo',
                    'autor',
                ]
            ])
            ->assertJsonPath('data.titulo', 'Libro de Anatomía');
    }

    public function test_show_archivo_returns_file()
    {
        $response = $this->actingAs($this->user)
            ->get(route('api.bibliografias.archivo', $this->bibliografiaLocal->id_bibliografia));

        $response->assertStatus(200);
        // Assert that the response is a file download or inline stream
        $this->assertEquals('dummy content', $response->streamedContent() ?? file_get_contents($response->getFile()->getPathname()));
    }

    public function test_show_archivo_fails_if_uta_resource()
    {
        $response = $this->actingAs($this->user)
            ->get(route('api.bibliografias.archivo', $this->bibliografiaUta->id_bibliografia));

        // Debe dar 404 porque no tiene archivo físico
        $response->assertStatus(404);
    }

    public function test_download_archivo_forces_download()
    {
        $response = $this->actingAs($this->user)
            ->get(route('api.bibliografias.descarga', $this->bibliografiaLocal->id_bibliografia));

        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename=libro-de-anatomia.pdf');
    }
}
