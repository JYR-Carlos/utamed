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

        // Usar un usuario administrador fresco y verificado
        $this->user = Usuario::factory()->create([
            'esta_activo' => true,
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ]);
        $rolSuperAdmin = \App\Models\Usuario\Rol::firstOrCreate(
            ['nombre' => 'SuperAdmin'],
            ['creado_por' => $this->user->id_usuario]
        );
        \App\Models\Usuario\UsuarioRolAsignacion::firstOrCreate(
            ['id_usuario' => $this->user->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
            [
                'asignado_por' => $this->user->id_usuario,
                'fecha_inicio_planificada' => now()->subMinute(),
                'fecha_fin_planificada' => now()->addYears(100),
                'esta_activo' => true,
                'fue_eliminado' => false,
                'creado_por' => $this->user->id_usuario,
            ]
        );
        $this->actingAs($this->user);
        
        $programa = Programa::query()->first();
        if (!$programa) {
            $this->markTestSkipped('No hay programas en la base de datos para probar.');
        }

        $unidad = Unidad::query()->where('id_curso', $programa->id_curso)->first();
        if (!$unidad) {
            $unidad = Unidad::query()->create([
                'id_curso' => $programa->id_curso,
                'num_unidad' => 99,
                'nombre' => 'Unidad Test',
            ]);
        }

        $docente = \App\Models\Usuario\Docente::query()->first();
        if (!$docente) {
            $this->markTestSkipped('No hay docentes en la base de datos para probar.');
        }

        // Fake storage para probar archivos
        $disk = config('files.storage.disk', 'local_archives');
        Storage::fake($disk);
        Storage::disk($disk)->put('bibliografias/test-file.pdf', 'dummy content');

        $archivo = \App\Models\Operaciones\Archivo::create([
            'uuid_archivo' => (string) Str::uuid(),
            'ruta_fisica' => 'bibliografias/test-file.pdf',
            'nombre_original' => 'libro-de-anatomia.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'peso_bytes' => 13,
        ]);

        $this->bibliografiaLocal = Bibliografia::query()->create([
            'uuid_bibliografia' => (string) Str::uuid(),
            'id_programa' => $programa->id_programa,
            'id_unidad' => $unidad->id_unidad,
            'titulo' => 'Libro de Anatomía',
            'autor' => 'Autor Test',
            'agno' => 2024,
            'es_bibliografia_uta' => false,
            'uuid_archivo' => $archivo->uuid_archivo,
            'url' => null,
            'agregado_por' => $docente->id_docente,
        ]);

        $this->bibliografiaUta = Bibliografia::query()->create([
            'uuid_bibliografia' => (string) Str::uuid(),
            'id_programa' => $programa->id_programa,
            'id_unidad' => $unidad->id_unidad,
            'titulo' => 'Recurso UTA Oficial',
            'autor' => 'UTA',
            'agno' => 2024,
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
