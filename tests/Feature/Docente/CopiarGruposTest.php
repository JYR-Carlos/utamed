<?php

/**
 * Copiar grupos entre actividades del mismo curso. Un grupo pertenece a una
 * actividad; la copia vale aunque el destino ya tenga entregas, y entonces sus
 * grupos no cambian y nadie queda en dos grupos de la misma actividad.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\InscripcionCurso;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/** @return array{id_curso:int, id_componente:int, id_unidad:int, docente:Usuario, estudiantes:array<int>} */
function escenarioCopia(): array
{
    $fila = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_componente, a.id_unidad
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN curso.componente co ON co.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = co.id_componente
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
          AND NOT c.es_plantilla AND a.id_unidad IS NOT NULL
          AND (SELECT count(*) FROM curso.inscripcion_curso i
               WHERE i.id_curso = c.id_curso AND i.estado_inscripcion = 'INSCRITO') >= 3
        ORDER BY c.id_curso, a.id_actividad LIMIT 1
    ") ?? test()->markTestSkipped('Sin un curso con titular y tres inscritos.');

    $docente = Usuario::findOrFail($fila->id_usuario);
    $docente->fecha_cambio_passhash = now();
    $docente->save();

    return [
        'id_curso' => $fila->id_curso,
        'id_componente' => $fila->id_componente,
        'id_unidad' => $fila->id_unidad,
        'docente' => $docente,
        'estudiantes' => InscripcionCurso::where('id_curso', $fila->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->orderBy('id_estudiante')->limit(3)->pluck('id_estudiante')->all(),
    ];
}

function actividadDeCopia(array $f, string $nombre, int $maximo = 3, bool $grupal = true): Actividad
{
    test()->actingAs($f['docente'])->post("/docente/cursos/{$f['id_curso']}/actividades", [
        'nombre' => $nombre,
        'fecha_limite' => now()->addDays(5)->toDateString(),
        'tipo_actividad' => 'SUMATIVA',
        'tipo_entrega' => 'online',
        'es_grupal' => $grupal,
        'max_integrantes' => $maximo,
        'visible' => true,
        'ponderacion' => 0,
        'exigencia' => 60,
        'id_componente' => $f['id_componente'],
        'id_unidad' => $f['id_unidad'],
    ])->assertSessionHasNoErrors();

    return Actividad::where('id_componente', $f['id_componente'])->where('nombre', $nombre)->latest('id_actividad')->firstOrFail();
}

function grupoDeCopia(Actividad $actividad, array $estudiantes, ?string $nombre = null): ActividadAsignadaGrupo
{
    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => $nombre,
        'estado_actividad_asignada' => 'PLANIFICADA',
    ]);
    foreach ($estudiantes as $id) {
        IntegranteGrupo::create(['id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo, 'id_estudiante' => $id]);
    }

    return $grupo;
}

function entregaDeCopia(ActividadAsignadaGrupo $grupo, int $idEstudiante): Agenda
{
    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "archivos/test/{$uuid}.pdf",
        'nombre_original' => 'entrega.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 100,
        'pendiente_de_borrado' => false,
    ]);

    return Agenda::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_usuario_emisor' => (int) DB::table('usuario.estudiante')->where('id_estudiante', $idEstudiante)->value('id_usuario'),
        'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
        'mensaje' => 'Entrega',
        'uuid_archivo_subido' => $uuid,
        'fecha_envio' => now()->toDateTimeString(),
    ]);
}

/** Integrantes de cada grupo de la actividad, por id de grupo. */
function gruposDe(Actividad $actividad): array
{
    return ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
        ->with('miembros')
        ->orderBy('id_actividad_asignada_grupo')
        ->get()
        ->mapWithKeys(fn ($g) => [$g->id_actividad_asignada_grupo => $g->miembros->pluck('id_estudiante')->sort()->values()->all()])
        ->all();
}

test('copia los grupos a varias actividades con su nombre y sus integrantes', function () {
    $f = escenarioCopia();
    [$uno, $dos, $tres] = $f['estudiantes'];
    $origen = actividadDeCopia($f, 'Copia origen');
    $destinos = [actividadDeCopia($f, 'Copia destino 1'), actividadDeCopia($f, 'Copia destino 2')];
    $a = grupoDeCopia($origen, [$uno, $dos], 'Los pares');
    $b = grupoDeCopia($origen, [$tres]);

    $this->actingAs($f['docente'])
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$origen->id_actividad}/grupos-copiar-a", [
            'grupos' => [$a->id_actividad_asignada_grupo, $b->id_actividad_asignada_grupo],
            'destinos' => array_column($destinos, 'id_actividad'),
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    foreach ($destinos as $destino) {
        expect(array_values(gruposDe($destino)))->toBe([[$uno, $dos], [$tres]])
            ->and(ActividadAsignadaGrupo::where('id_actividad', $destino->id_actividad)->pluck('nombre_grupo')->filter()->values()->all())
            ->toBe(['Los pares']);
    }
    expect(gruposDe($origen))->toHaveCount(2);
});

test('copiar a una actividad con entregas no toca sus grupos ni deja a nadie en dos', function (string $via) {
    $f = escenarioCopia();
    [$uno, $dos, $tres] = $f['estudiantes'];
    $origen = actividadDeCopia($f, 'Copia origen');
    $destino = actividadDeCopia($f, 'Copia con entregas');
    $grupo = grupoDeCopia($origen, [$uno, $dos, $tres], 'Equipo');
    $previo = grupoDeCopia($destino, [$uno], 'Previo');
    $entrega = entregaDeCopia($previo, $uno);

    $base = "/docente/cursos/{$f['id_curso']}/actividades";
    $this->actingAs($f['docente'])->post(...($via === 'copiar'
        ? ["{$base}/{$origen->id_actividad}/grupos-copiar-a", [
            'grupos' => [$grupo->id_actividad_asignada_grupo],
            'destinos' => [$destino->id_actividad],
        ]]
        : ["{$base}/{$destino->id_actividad}/grupos-copy", [
            'id_actividad_origen' => $origen->id_actividad,
            'grupos' => [$grupo->id_actividad_asignada_grupo],
        ]]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($m) => str_contains($m, '1 estudiante ya tenía grupo'));

    $grupos = gruposDe($destino);
    expect($grupos[$previo->id_actividad_asignada_grupo])->toBe([$uno])
        ->and(array_values($grupos))->toBe([[$uno], [$dos, $tres]])
        ->and($entrega->fresh())->not->toBeNull()
        ->and($entrega->fresh()->id_actividad_asignada_grupo)->toBe($previo->id_actividad_asignada_grupo);
})->with(['copiar', 'reutilizar']);

test('repetir la copia no duplica grupos', function () {
    $f = escenarioCopia();
    $origen = actividadDeCopia($f, 'Copia origen');
    $destino = actividadDeCopia($f, 'Copia destino');
    $grupo = grupoDeCopia($origen, array_slice($f['estudiantes'], 0, 2));
    $url = "/docente/cursos/{$f['id_curso']}/actividades/{$origen->id_actividad}/grupos-copiar-a";
    $datos = ['grupos' => [$grupo->id_actividad_asignada_grupo], 'destinos' => [$destino->id_actividad]];

    $this->actingAs($f['docente'])->post($url, $datos)->assertSessionHasNoErrors();
    $this->actingAs($f['docente'])->post($url, $datos)->assertSessionHasErrors('error');

    expect(gruposDe($destino))->toHaveCount(1);
});

test('un grupo mayor que el máximo del destino no se copia', function () {
    $f = escenarioCopia();
    $origen = actividadDeCopia($f, 'Copia origen', 3);
    $destino = actividadDeCopia($f, 'Copia destino chica', 2);
    $grupo = grupoDeCopia($origen, $f['estudiantes']);

    $this->actingAs($f['docente'])
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$origen->id_actividad}/grupos-copiar-a", [
            'grupos' => [$grupo->id_actividad_asignada_grupo],
            'destinos' => [$destino->id_actividad],
        ])
        ->assertSessionHasErrors('error');

    expect(gruposDe($destino))->toBe([]);
});

test('si un destino no es válido no se copia a ninguno', function () {
    $f = escenarioCopia();
    $origen = actividadDeCopia($f, 'Copia origen');
    $destino = actividadDeCopia($f, 'Copia destino');
    $grupo = grupoDeCopia($origen, array_slice($f['estudiantes'], 0, 2));
    $url = "/docente/cursos/{$f['id_curso']}/actividades/{$origen->id_actividad}/grupos-copiar-a";
    $grupos = [$grupo->id_actividad_asignada_grupo];

    // Una actividad de otro curso.
    $ajena = DB::selectOne('
        SELECT a.id_actividad FROM agenda.actividad a
        JOIN curso.componente co ON co.id_componente = a.id_componente
        WHERE co.id_curso <> ? LIMIT 1
    ', [$f['id_curso']]);
    if ($ajena) {
        $this->actingAs($f['docente'])
            ->post($url, ['grupos' => $grupos, 'destinos' => [$destino->id_actividad, $ajena->id_actividad]])
            ->assertNotFound();
    }

    // La propia actividad.
    $this->actingAs($f['docente'])
        ->post($url, ['grupos' => $grupos, 'destinos' => [$origen->id_actividad]])
        ->assertSessionHasErrors('destinos.0');

    // Una actividad individual.
    $individual = actividadDeCopia($f, 'Copia individual', 1, false);
    $this->actingAs($f['docente'])
        ->post($url, ['grupos' => $grupos, 'destinos' => [$destino->id_actividad, $individual->id_actividad]])
        ->assertStatus(422);

    expect(gruposDe($destino))->toBe([]);
});

test('un docente que no dicta el componente del destino no puede copiar hacia él', function () {
    $f = escenarioCopia();
    $origen = actividadDeCopia($f, 'Copia origen');
    $destino = actividadDeCopia($f, 'Copia destino');
    $grupo = grupoDeCopia($origen, array_slice($f['estudiantes'], 0, 2));

    $ajeno = DB::selectOne("
        SELECT d.id_usuario FROM usuario.docente d
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE d.id_usuario <> ? AND ura.esta_activo AND NOT ura.fue_eliminado AND LOWER(r.nombre) LIKE 'docente%'
          AND NOT EXISTS (
              SELECT 1 FROM curso.docente_componente dc
              JOIN curso.componente co ON co.id_componente = dc.id_componente
              WHERE dc.id_docente = d.id_docente AND co.id_curso = ?)
        LIMIT 1
    ", [$f['docente']->id_usuario, $f['id_curso']]) ?? $this->markTestSkipped('Sin un docente ajeno al curso.');
    $usuario = Usuario::findOrFail($ajeno->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    $this->actingAs($usuario)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$origen->id_actividad}/grupos-copiar-a", [
            'grupos' => [$grupo->id_actividad_asignada_grupo],
            'destinos' => [$destino->id_actividad],
        ])
        ->assertForbidden();

    expect(gruposDe($destino))->toBe([]);
});
