<?php

/**
 * Ciclo de vida de una actividad creada por el docente:
 *
 *  • Eliminarla borra todo lo que cuelga de ella (grupos, agenda, evaluaciones,
 *    rúbricas) y deja los archivos marcados para la limpieza asíncrona. Antes
 *    toda FK hacia agenda.actividad era RESTRICT y ninguna individual se podía
 *    borrar.
 *  • Pasar de individual a grupal descarta los «grupos» de 1 persona (sólo
 *    emulan el registro individual); volver a individual los rehace. Si ya hay
 *    mensajes, entregas o notas, el cambio se rechaza.
 *  • El rótulo de un grupo es el nombre de la persona (individual) o el nombre
 *    propio del grupo / «Grupo N» (grupal), nunca el id interno.
 */

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Rubrica;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/**
 * Curso con docente titular activo, con inscritos y con una actividad de la que
 * tomar componente y unidad.
 *
 * @return array{id_curso: int, id_usuario: int, id_componente: int, id_unidad: int}|null
 */
function fixtureModalidadActividad(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_componente, a.id_unidad
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN curso.componente co ON co.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = co.id_componente
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
          AND c.es_plantilla = false
          AND a.id_unidad IS NOT NULL
          AND EXISTS (SELECT 1 FROM curso.inscripcion_curso i
                      WHERE i.id_curso = c.id_curso AND i.estado_inscripcion = 'INSCRITO')
        ORDER BY c.id_curso, a.id_actividad
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

function docenteModalidadActividad(array $f): Usuario
{
    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return $usuario;
}

function datosActividadModalidad(array $f, bool $grupal): array
{
    return [
        'nombre' => 'ZZ test modalidad',
        'fecha_limite' => now()->addDays(10)->toDateString(),
        'tipo_actividad' => 'SUMATIVA',
        'tipo_entrega' => 'online',
        'es_grupal' => $grupal,
        'max_integrantes' => $grupal ? 3 : 1,
        'visible' => true,
        'ponderacion' => 0,
        'exigencia' => 60,
        'id_componente' => $f['id_componente'],
        'id_unidad' => $f['id_unidad'],
    ];
}

/** Crea la actividad por el mismo endpoint que usa el docente. */
function crearActividadModalidad(Usuario $docente, array $f, bool $grupal): Actividad
{
    test()->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades", datosActividadModalidad($f, $grupal))
        ->assertSessionHasNoErrors();

    return Actividad::where('id_componente', $f['id_componente'])
        ->where('nombre', 'ZZ test modalidad')
        ->latest('id_actividad')
        ->firstOrFail();
}

function gruposDe(Actividad $a): int
{
    return ActividadAsignadaGrupo::where('id_actividad', $a->id_actividad)->count();
}

/** Una fila de agenda con archivo en el primer grupo de la actividad. */
function entregarEnPrimerGrupo(Actividad $a): array
{
    $grupo = ActividadAsignadaGrupo::where('id_actividad', $a->id_actividad)->orderBy('id_actividad_asignada_grupo')->firstOrFail();
    $emisor = DB::table('agenda.integrante_grupo as ig')
        ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'ig.id_estudiante')
        ->where('ig.id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
        ->value('e.id_usuario');

    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "archivos/test/entregas/{$uuid}.pdf",
        'nombre_original' => 'entrega.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 10,
        'pendiente_de_borrado' => false,
    ]);

    $agenda = Agenda::create([
        'fecha_envio' => now()->toDateTimeString(),
        'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
        'mensaje' => 'entrega (test)',
        'uuid_archivo_subido' => $uuid,
        'id_usuario_emisor' => (int) $emisor,
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
    ]);

    return ['agenda' => $agenda, 'uuid' => $uuid];
}

test('eliminar una actividad individual con entregas borra sus dependencias y marca los archivos', function () {
    $f = fixtureModalidadActividad() ?? $this->markTestSkipped('Sin curso con titular, inscritos y actividades.');
    $docente = docenteModalidadActividad($f);
    $actividad = crearActividadModalidad($docente, $f, false);
    $idContexto = $actividad->id_contexto;

    expect(gruposDe($actividad))->toBeGreaterThan(0);
    $entrega = entregarEnPrimerGrupo($actividad);
    Rubrica::create([
        'rubrica' => ['niveles' => [['nombre' => 'Criterio']]],
        'estado_rubrica' => EstadoRubrica::CERRADA,
        'id_actividad' => $actividad->id_actividad,
    ]);

    $this->actingAs($docente)
        ->delete("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}")
        ->assertSessionHasNoErrors();

    expect(Actividad::find($actividad->id_actividad))->toBeNull()
        ->and(DB::table('agenda.actividad_asignada_grupo')->where('id_actividad', $actividad->id_actividad)->count())->toBe(0)
        ->and(DB::table('agenda.agenda')->where('id_agenda', $entrega['agenda']->id_agenda)->exists())->toBeFalse()
        ->and(DB::table('agenda.rubrica')->where('id_actividad', $actividad->id_actividad)->exists())->toBeFalse()
        ->and(DB::table('usuario.contexto')->where('id_contexto', $idContexto)->exists())->toBeFalse()
        ->and(Archivo::where('uuid_archivo', $entrega['uuid'])->value('pendiente_de_borrado'))->toBeTrue();
});

test('pasar de individual a grupal descarta los grupos de 1 y deja a todos sin asignar', function () {
    $f = fixtureModalidadActividad() ?? $this->markTestSkipped('Sin curso con titular, inscritos y actividades.');
    $docente = docenteModalidadActividad($f);
    $actividad = crearActividadModalidad($docente, $f, false);
    expect(gruposDe($actividad))->toBeGreaterThan(0);

    $this->actingAs($docente)
        ->put("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}", datosActividadModalidad($f, true))
        ->assertSessionHasNoErrors();

    expect((bool) $actividad->fresh()->es_grupal)->toBeTrue()
        ->and(gruposDe($actividad))->toBe(0);
});

test('volver de grupal a individual rehace el grupo de 1 de cada inscrito', function () {
    $f = fixtureModalidadActividad() ?? $this->markTestSkipped('Sin curso con titular, inscritos y actividades.');
    $docente = docenteModalidadActividad($f);
    $actividad = crearActividadModalidad($docente, $f, true);
    expect(gruposDe($actividad))->toBe(0);

    $this->actingAs($docente)
        ->put("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}", datosActividadModalidad($f, false))
        ->assertSessionHasNoErrors();

    $inscritos = DB::table('curso.inscripcion_curso')
        ->where('id_curso', $f['id_curso'])->where('estado_inscripcion', 'INSCRITO')->count();

    expect((bool) $actividad->fresh()->es_grupal)->toBeFalse()
        ->and(gruposDe($actividad))->toBe($inscritos);
});

test('con entregas o mensajes el cambio de modalidad se rechaza y no se toca nada', function () {
    $f = fixtureModalidadActividad() ?? $this->markTestSkipped('Sin curso con titular, inscritos y actividades.');
    $docente = docenteModalidadActividad($f);
    $actividad = crearActividadModalidad($docente, $f, false);
    $antes = gruposDe($actividad);
    entregarEnPrimerGrupo($actividad);

    $this->actingAs($docente)
        ->put("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}", datosActividadModalidad($f, true))
        ->assertSessionHasErrors('es_grupal');

    expect((bool) $actividad->fresh()->es_grupal)->toBeFalse()
        ->and(gruposDe($actividad))->toBe($antes);
});

test('editar sin cambiar la modalidad no toca los grupos aunque haya entregas', function () {
    $f = fixtureModalidadActividad() ?? $this->markTestSkipped('Sin curso con titular, inscritos y actividades.');
    $docente = docenteModalidadActividad($f);
    $actividad = crearActividadModalidad($docente, $f, false);
    $antes = gruposDe($actividad);
    entregarEnPrimerGrupo($actividad);

    $datos = datosActividadModalidad($f, false) + [];
    $datos['nombre'] = 'ZZ test modalidad';
    $this->actingAs($docente)
        ->put("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}", $datos)
        ->assertSessionHasNoErrors();

    expect(gruposDe($actividad))->toBe($antes);
});

test('el rótulo del grupo es el nombre de la persona o el nombre propio, nunca el id', function () {
    // Individual: el nombre del estudiante; sin nombre, un texto genérico.
    expect(ActividadAsignadaGrupo::etiqueta(false, 'Ignorado', 'HUARACHE CHURATA IGNACIO', 3))->toBe('HUARACHE CHURATA IGNACIO')
        ->and(ActividadAsignadaGrupo::etiqueta(false, null, null, 3))->toBe('Estudiante')
        // Grupal: el nombre que le pusieron; sin él, «Grupo N» por posición.
        ->and(ActividadAsignadaGrupo::etiqueta(true, 'Los Tigres', null, 2))->toBe('Los Tigres')
        ->and(ActividadAsignadaGrupo::etiqueta(true, '   ', null, 2))->toBe('Grupo 2')
        ->and(ActividadAsignadaGrupo::etiqueta(true, null, null, 1))->toBe('Grupo 1');
});

test('sumar décimas sobre un 7,0 avisa que la escala lo topa; un ajuste normal no', function () {
    $f = fixtureModalidadActividad() ?? $this->markTestSkipped('Sin curso con titular, inscritos y actividades.');
    $docente = docenteModalidadActividad($f);
    $actividad = crearActividadModalidad($docente, $f, false);

    $grupo = ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
        ->orderBy('id_actividad_asignada_grupo')->firstOrFail();
    $grupo->update(['nota' => 7.0]);
    $integrante = DB::table('agenda.integrante_grupo')
        ->where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)->first();
    $ruta = "/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}"
        . "/grupos/{$grupo->id_actividad_asignada_grupo}/integrantes/{$integrante->id_asignado_actividad}";

    $this->actingAs($docente)->put($ruta, ['diferencia_decimas' => 0.1])
        ->assertSessionHas('success', 'Nota individual actualizada: queda en 7,0 porque la escala va de 1,0 a 7,0.');

    $this->actingAs($docente)->put($ruta, ['diferencia_decimas' => -0.5])
        ->assertSessionHas('success', 'Nota individual actualizada.');
    expect((float) DB::table('agenda.integrante_grupo')
        ->where('id_asignado_actividad', $integrante->id_asignado_actividad)->value('nota_individual'))->toBe(6.5);
});

test('el docente ve cuál entrega es la vigente, cuáles llegaron fuera de plazo y quién entregó', function () {
    $f = fixtureModalidadActividad() ?? $this->markTestSkipped('Sin curso con titular, inscritos y actividades.');
    $docente = docenteModalidadActividad($f);
    $actividad = crearActividadModalidad($docente, $f, false);
    // La fecha límite original ya pasó: toda entrega queda «fuera de plazo»
    // aunque la holgura la mantenga abierta.
    $actividad->update(['fecha_limite' => now()->subDays(3)->toDateString(), 'nro_dias_adicionales_para_bloqueo' => 10]);

    $primera = entregarEnPrimerGrupo($actividad)['agenda'];
    $segunda = entregarEnPrimerGrupo($actividad)['agenda'];
    $idGrupo = (int) $primera->id_actividad_asignada_grupo;
    $base = "/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}";

    $entregas = collect($this->actingAs($docente)->getJson("{$base}/grupos/{$idGrupo}/entregas")
        ->assertOk()->json())->keyBy('id_agenda');

    expect($entregas)->toHaveCount(2)
        ->and($entregas[$segunda->id_agenda]['vigente'])->toBeTrue()
        ->and($entregas[$primera->id_agenda]['vigente'])->toBeFalse()
        ->and($entregas[$segunda->id_agenda]['fuera_de_plazo'])->toBeTrue();

    $props = $this->actingAs($docente)->get("{$base}/evaluacion")->assertOk()->viewData('page')['props'];
    $grupos = collect($props['grupos'])->keyBy('grupo');
    $otro = $grupos->first(fn ($g) => $g['grupo'] !== $idGrupo);

    expect($grupos[$idGrupo]['entrega'])->toMatchArray(['fuera_de_plazo' => true, 'cantidad' => 2])
        ->and($otro['entrega'])->toBeNull();
});
