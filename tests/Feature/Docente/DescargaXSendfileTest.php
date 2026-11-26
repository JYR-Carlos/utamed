<?php

/**
 * Con FILES_X_SENDFILE (config files.x_sendfile) las descargas por
 * response()->download()/file() dejan que el servidor web envíe el archivo:
 * PHP responde la cabecera X-Sendfile y el cuerpo vacío. Apache fija la
 * cabecera de la petición X-Sendfile-Type; sin ella, PHP sigue enviando el
 * archivo como siempre.
 */

use App\Models\Agenda\Actividad;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(DatabaseTransactions::class);

/** Actividad de un titular activo con un enunciado real en el disco local_archives (falso). */
function enunciadoParaDescargar($test): array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_actividad
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN agenda.actividad a ON a.id_componente IN (SELECT id_componente FROM curso.componente WHERE id_curso = c.id_curso)
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
        ORDER BY a.id_actividad
        LIMIT 1
    ");
    if (!$row) {
        $test->markTestSkipped('No hay una actividad de un titular activo.');
    }

    Storage::fake('local_archives');
    $ruta = 'test/enunciados/enunciado.pdf';
    Storage::disk('local_archives')->put($ruta, "%PDF-1.4\n%%EOF\n");

    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => $ruta,
        'nombre_original' => 'enunciado.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 15,
        'pendiente_de_borrado' => false,
    ]);
    Actividad::whereKey($row->id_actividad)->update(['uuid_archivo_enunciado' => $uuid]);

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return [
        'usuario' => $usuario,
        'url' => "/docente/cursos/{$row->id_curso}/actividades/{$row->id_actividad}/enunciado/descargar",
        'ruta_absoluta' => Storage::disk('local_archives')->path($ruta),
    ];
}

test('con x_sendfile y la cabecera de Apache, la descarga la envía el servidor web', function () {
    $f = enunciadoParaDescargar($this);
    BinaryFileResponse::trustXSendfileTypeHeader();

    $response = $this->actingAs($f['usuario'])
        ->withHeaders(['X-Sendfile-Type' => 'X-Sendfile'])
        ->get($f['url']);

    $response->assertOk();
    expect($response->headers->get('X-Sendfile'))->toBe(realpath($f['ruta_absoluta']));
});

test('sin la cabecera de Apache, PHP sigue enviando el archivo', function () {
    $f = enunciadoParaDescargar($this);

    $response = $this->actingAs($f['usuario'])->get($f['url']);

    $response->assertOk();
    expect($response->headers->has('X-Sendfile'))->toBeFalse()
        ->and($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
});
