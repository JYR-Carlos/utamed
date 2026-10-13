<?php

/*
 * AgendaController::storeEntrega guarda el archivo FUERA de la transacción y
 * después abre una transacción corta sólo para la fila de agenda. Dentro de
 * ella vuelve a revisar «ya evaluado» y el plazo: si el docente evaluó
 * mientras el archivo se procesaba, la entrega se rechaza y el archivo, que
 * ya está en disco y en operaciones.archivo, queda marcado para borrar.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

/** Grupo sin agenda, con un integrante de rol Estudiante y plazo vigente. */
function grupoParaSubirEntrega($test): array
{
    $row = DB::selectOne("
        SELECT g.id_actividad_asignada_grupo, g.id_actividad, e.id_usuario
        FROM agenda.actividad_asignada_grupo g
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = e.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado AND r.nombre = 'Estudiante'
          AND NOT EXISTS (SELECT 1 FROM agenda.agenda ag WHERE ag.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo)
        ORDER BY g.id_actividad_asignada_grupo
        LIMIT 1
    ");
    if (!$row) {
        $test->markTestSkipped('No hay un grupo sin agenda con un integrante de rol Estudiante.');
    }

    DB::table('agenda.actividad')->where('id_actividad', $row->id_actividad)
        ->update(['fecha_limite' => now()->addDays(7)->toDateString()]);
    DB::table('agenda.actividad_asignada_grupo')->where('id_actividad_asignada_grupo', $row->id_actividad_asignada_grupo)
        ->update(['nota' => null]);

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return ['grupo' => (int) $row->id_actividad_asignada_grupo, 'usuario' => $usuario];
}

/** Un PDF mínimo pero válido: el pipeline revisa el contenido, no sólo la extensión. */
function pdfDePrueba(): UploadedFile
{
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    return UploadedFile::fake()->createWithContent('entrega.pdf', $pdf);
}

function filasEntrega(int $grupo): int
{
    return Agenda::where('id_actividad_asignada_grupo', $grupo)
        ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
        ->count();
}

test('una subida válida registra la entrega con su archivo', function () {
    Storage::fake('local');
    Storage::fake(config('filesystems.default'));
    $f = grupoParaSubirEntrega($this);

    $this->actingAs($f['usuario'])
        ->from('/estudiante/dashboard')
        ->post("/estudiante/grupos-asignados/{$f['grupo']}/entregas", ['archivo' => pdfDePrueba(), 'mensaje' => 'Mi entrega'])
        ->assertRedirect('/estudiante/dashboard')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $entrega = Agenda::where('id_actividad_asignada_grupo', $f['grupo'])
        ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
        ->firstOrFail();
    expect($entrega->uuid_archivo_subido)->not->toBeNull()
        ->and(Archivo::findOrFail($entrega->uuid_archivo_subido)->pendiente_de_borrado)->toBeFalse();
});

test('si el docente evalúa mientras se procesa el archivo, la entrega se rechaza y el archivo queda para borrar', function () {
    Storage::fake('local');
    Storage::fake(config('filesystems.default'));
    $f = grupoParaSubirEntrega($this);

    // El archivo se registra en operaciones.archivo antes de la transacción
    // corta: en ese momento «llega» la nota del docente.
    Event::listen('eloquent.created: ' . Archivo::class, function (Archivo $archivo) use ($f) {
        DB::table('agenda.actividad_asignada_grupo')
            ->where('id_actividad_asignada_grupo', $f['grupo'])
            ->update(['nota' => 6.0]);
        test()->uuidSubido = $archivo->uuid_archivo;
    });

    $this->actingAs($f['usuario'])
        ->from('/estudiante/dashboard')
        ->post("/estudiante/grupos-asignados/{$f['grupo']}/entregas", ['archivo' => pdfDePrueba(), 'mensaje' => 'Mi entrega'])
        ->assertRedirect('/estudiante/dashboard')
        ->assertSessionHasErrors([
            'error_general' => 'La entrega ya ha sido evaluada por el docente. No es posible subir ni reemplazar archivos.',
        ]);

    expect(filasEntrega($f['grupo']))->toBe(0)
        ->and($this->uuidSubido ?? null)->not->toBeNull()
        ->and(Archivo::findOrFail($this->uuidSubido)->pendiente_de_borrado)->toBeTrue();
});

test('dos envíos idénticos seguidos dejan una sola entrega y el archivo sobrante queda para borrar', function () {
    Storage::fake('local');
    Storage::fake(config('filesystems.default'));
    $f = grupoParaSubirEntrega($this);
    $envio = fn () => $this->actingAs($f['usuario'])
        ->from('/estudiante/dashboard')
        ->post("/estudiante/grupos-asignados/{$f['grupo']}/entregas", ['archivo' => pdfDePrueba(), 'mensaje' => 'Mi entrega']);

    $envio()->assertSessionHasNoErrors()->assertSessionHas('success');
    $envio()->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(filasEntrega($f['grupo']))->toBe(1);
    $vigente = Agenda::where('id_actividad_asignada_grupo', $f['grupo'])
        ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)->firstOrFail();
    // El archivo de la segunda petición quedó guardado pero sin entrega: a borrar.
    $sobrantes = Archivo::where('pendiente_de_borrado', true)
        ->where('uuid_archivo', '!=', $vigente->uuid_archivo_subido)
        ->where('nombre_original', 'entrega.pdf')
        ->count();
    expect($sobrantes)->toBeGreaterThanOrEqual(1)
        ->and(Archivo::findOrFail($vigente->uuid_archivo_subido)->pendiente_de_borrado)->toBeFalse();
});

test('un archivo distinto enviado enseguida sí es un reemplazo, no un duplicado', function () {
    Storage::fake('local');
    Storage::fake(config('filesystems.default'));
    $f = grupoParaSubirEntrega($this);

    $this->actingAs($f['usuario'])->from('/estudiante/dashboard')
        ->post("/estudiante/grupos-asignados/{$f['grupo']}/entregas", ['archivo' => pdfDePrueba(), 'mensaje' => 'v1'])
        ->assertSessionHasNoErrors();

    $otro = UploadedFile::fake()->createWithContent(
        'entrega-v2.pdf',
        "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 300]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"
    );
    $this->actingAs($f['usuario'])->from('/estudiante/dashboard')
        ->post("/estudiante/grupos-asignados/{$f['grupo']}/entregas", ['archivo' => $otro, 'mensaje' => 'v2'])
        ->assertSessionHasNoErrors();

    expect(filasEntrega($f['grupo']))->toBe(2);
});
