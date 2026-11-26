<?php

/**
 * Subir un archivo a la Biblioteca del curso: sólo quien puede agregarle
 * recursos (el titular, o quien tenga delegada la sección VIII). El alumno
 * inscrito ve la Biblioteca y abre sus archivos, pero no sube nada; un alumno
 * de otro curso no los abre.
 */

use App\Models\Curso\Bibliografia;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->disco = config('files.storage.disk', 'local_archives');
    Storage::fake($this->disco);
});

/** Un curso con titular y un alumno inscrito; si no tiene programa vigente, se le crea uno aprobado. */
function cursoConProgramaTitularYAlumno(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario AS id_titular, e.id_usuario AS id_alumno
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN curso.inscripcion_curso ic ON ic.id_curso = c.id_curso AND ic.estado_inscripcion = 'INSCRITO'
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        ORDER BY (SELECT count(*) FROM curso.programa p WHERE p.id_curso = c.id_curso AND p.es_actual) DESC, c.id_curso
        LIMIT 1
    ");

    if (!$row) {
        return null;
    }

    // Sin fecha de cambio de clave, el middleware manda a cambiarla.
    $usuarios = [];
    foreach (['titular' => $row->id_titular, 'alumno' => $row->id_alumno] as $rol => $id) {
        $usuarios[$rol] = Usuario::findOrFail($id);
        $usuarios[$rol]->forceFill(['fecha_cambio_passhash' => now()])->save();
    }

    $idPrograma = DB::table('curso.programa')->where('id_curso', $row->id_curso)->where('es_actual', true)->value('id_programa')
        ?? DB::table('curso.programa')->insertGetId([
            'id_curso'         => $row->id_curso,
            'version_programa' => '1',
            'estado'           => 'APROBADO',
            'data_syllabus'    => '{}',
            'creado_por'       => $row->id_titular,
            'es_actual'        => true,
        ], 'id_programa');

    return $usuarios + [
        'curso'    => Curso::findOrFail($row->id_curso),
        'programa' => Programa::findOrFail($idPrograma),
    ];
}

function pdfDeBiblioteca(string $nombre): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $nombre,
        "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj "
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n",
    );
}

test('un alumno inscrito no sube archivos a la Biblioteca', function () {
    $f = cursoConProgramaTitularYAlumno() ?? $this->markTestSkipped('No hay un curso con programa y alumnos inscritos.');
    $antes = Archivo::count();

    $this->actingAs($f['alumno'])
        ->postJson('/api/bibliografias/archivo', [
            'archivo'  => pdfDeBiblioteca('del-alumno.pdf'),
            'id_curso' => $f['curso']->id_curso,
        ])
        ->assertForbidden();

    expect(Archivo::count())->toBe($antes)
        ->and(Storage::disk($this->disco)->allFiles())->toBeEmpty();
});

/** El titular sube «lectura.pdf» y lo deja en la Biblioteca del programa; devuelve su uuid. */
function lecturaEnLaBiblioteca($test, array $f): string
{
    $uuid = $test->actingAs($f['titular'])
        ->postJson('/api/bibliografias/archivo', [
            'archivo'     => pdfDeBiblioteca('lectura.pdf'),
            'id_curso'    => $f['curso']->id_curso,
            'id_programa' => $f['programa']->id_programa,
        ])
        ->assertOk()
        ->json('uuid_archivo');

    Bibliografia::create([
        'id_programa'         => $f['programa']->id_programa,
        'titulo'              => 'Lectura de la unidad',
        'autor'               => 'S/A',
        'agno'                => 2026,
        'es_bibliografia_uta' => false,
        'uuid_archivo'        => $uuid,
        'agregado_por'        => $f['titular']->id_usuario,
    ]);

    return $uuid;
}

test('el titular sube el archivo y el alumno inscrito lo abre y lo descarga', function () {
    $f = cursoConProgramaTitularYAlumno() ?? $this->markTestSkipped('No hay un curso con programa y alumnos inscritos.');

    $uuid = lecturaEnLaBiblioteca($this, $f);

    $this->actingAs($f['alumno'])
        ->get("/api/bibliografias/{$uuid}/archivo")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($f['alumno'])
        ->get("/api/bibliografias/{$uuid}/descarga")
        ->assertOk()
        ->assertDownload('lectura.pdf');
});

test('un alumno que no está inscrito en el curso no abre ni descarga sus archivos', function () {
    $f = cursoConProgramaTitularYAlumno() ?? $this->markTestSkipped('No hay un curso con programa y alumnos inscritos.');

    $idAjeno = DB::table('usuario.estudiante as e')
        ->whereNotExists(fn ($q) => $q->from('curso.inscripcion_curso as ic')
            ->whereColumn('ic.id_estudiante', 'e.id_estudiante')
            ->where('ic.id_curso', $f['curso']->id_curso)
            ->where('ic.estado_inscripcion', 'INSCRITO'))
        ->whereNotExists(fn ($q) => $q->from('usuario.docente as d')->whereColumn('d.id_usuario', 'e.id_usuario'))
        ->value('e.id_usuario')
        ?? $this->markTestSkipped('No hay un alumno fuera de ese curso.');

    $ajeno = Usuario::findOrFail($idAjeno);
    $ajeno->forceFill(['fecha_cambio_passhash' => now()])->save();

    $uuid = lecturaEnLaBiblioteca($this, $f);

    $this->actingAs($ajeno)->get("/api/bibliografias/{$uuid}/archivo")->assertForbidden();
    $this->actingAs($ajeno)->get("/api/bibliografias/{$uuid}/descarga")->assertForbidden();
});
