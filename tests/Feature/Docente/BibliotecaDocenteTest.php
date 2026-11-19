<?php

use App\Models\Curso\Bibliografia;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

function crearSuperAdminBiblioteca(): Usuario
{
    $user = Usuario::factory()->create([
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
        'fecha_verificacion_email' => now(),
    ]);

    $rolSuperAdmin = Rol::firstOrCreate(
        ['nombre' => 'SuperAdmin'],
        ['creado_por' => $user->id_usuario]
    );

    UsuarioRolAsignacion::firstOrCreate(
        ['id_usuario' => $user->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
        [
            'asignado_por' => $user->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada' => now()->addYears(100),
            'esta_activo' => true,
            'fue_eliminado' => false,
            'creado_por' => $user->id_usuario,
        ]
    );

    return $user;
}

test('biblioteca docente lee bibliografias desde la tabla bibliografia', function () {
    $programa = Programa::where('es_actual', true)->first();
    if (!$programa) {
        $this->markTestSkipped('No hay programas vigentes en la base de datos.');
    }

    $user = crearSuperAdminBiblioteca();
    $this->actingAs($user);

    $bib = Bibliografia::create([
        'uuid_bibliografia' => (string) Str::uuid(),
        'id_programa' => $programa->id_programa,
        'titulo' => 'Libro Desde Tabla Test',
        'autor' => 'Autor Tabla',
        'agno' => 2025,
        'es_bibliografia_uta' => false,
        'url' => 'https://example.com/libro',
        'agregado_por' => $user->id_usuario,
    ]);

    $response = $this->get(route('docente.cursos.biblioteca', $programa->id_curso));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('docente/Biblioteca')
        ->where('bibliografias', fn ($items) => collect($items)->contains('uuid_bibliografia', $bib->uuid_bibliografia))
    );
});

test('docente autorizado puede agregar bibliografia desde biblioteca y sincroniza data_syllabus', function () {
    $programa = Programa::where('es_actual', true)->first();
    if (!$programa) {
        $this->markTestSkipped('No hay programas vigentes en la base de datos.');
    }

    $user = crearSuperAdminBiblioteca();
    $this->actingAs($user);

    $unidad = Unidad::where('id_curso', $programa->id_curso)->orderBy('num_unidad')->first();

    $response = $this->post(route('docente.cursos.biblioteca.store', $programa->id_curso), [
        'titulo' => 'Nueva Bibliografia Sincronizada',
        'autor' => 'Autor Nuevo',
        'editorial' => 'Editorial Test',
        'anio' => 2026,
        'es_bibliografia_uta' => false,
        'url' => 'https://example.com/recurso-nuevo',
        'id_unidad' => $unidad?->num_unidad,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $creada = Bibliografia::where('id_programa', $programa->id_programa)
        ->where('titulo', 'Nueva Bibliografia Sincronizada')
        ->first();

    expect($creada)->not->toBeNull();
    expect($creada->id_unidad)->toBe($unidad?->id_unidad);

    $programa->refresh();
    $raw = is_array($programa->data_syllabus)
        ? $programa->data_syllabus
        : json_decode((string) $programa->data_syllabus, true);

    $enSyllabus = collect($raw['secciones']['VIII']['contenido']['bibliografias'] ?? [])
        ->firstWhere('id_bibliografia', $creada->uuid_bibliografia);

    expect($enSyllabus)->not->toBeNull();
    expect($enSyllabus['titulo'])->toBe('Nueva Bibliografia Sincronizada');
    expect($enSyllabus['id_unidad'])->toBe($unidad?->num_unidad);
});

test('usuario sin permiso de edicion recibe 403 al agregar bibliografia', function () {
    $programa = Programa::where('es_actual', true)->first();
    if (!$programa) {
        $this->markTestSkipped('No hay programas vigentes en la base de datos.');
    }

    $sinPermiso = Usuario::factory()->create([
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
        'fecha_verificacion_email' => now(),
    ]);
    $this->actingAs($sinPermiso);

    $response = $this->post(route('docente.cursos.biblioteca.store', $programa->id_curso), [
        'titulo' => 'Intento No Autorizado',
        'anio' => 2026,
        'es_bibliografia_uta' => true,
    ]);

    $response->assertForbidden();
});
