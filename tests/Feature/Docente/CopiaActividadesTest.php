<?php

/**
 * «Copiar a otro curso»: las actividades de un curso pasan a sus cursos
 * hermanos (mismo titular, asignatura, año y semestre), ocultas, con el
 * enunciado compartido y la rúbrica como copia independiente.
 */

use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use App\Services\Agenda\CopiaActividadesService;
use App\Services\CursoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/**
 * Curso de origen con titular activo y al menos una actividad con unidad, más
 * un curso hermano creado para el test (sin actividades).
 *
 * @return array{origen: Curso, hermano: Curso, usuario: Usuario}|null
 */
function fixtureCopiaActividades(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
          AND c.es_plantilla = false
          AND EXISTS (
            SELECT 1 FROM agenda.actividad a
            JOIN curso.componente co ON co.id_componente = a.id_componente
            WHERE co.id_curso = c.id_curso
          )
        ORDER BY c.id_curso
        LIMIT 1
    ");
    if (!$row) {
        return null;
    }

    $origen = Curso::with('asignacionPlan')->findOrFail($row->id_curso);

    // El hermano se arma con la copia de cursos del admin (unidades y
    // componentes iguales) y se le quitan las actividades que trae.
    $hermano = app(CursoService::class)->copiar($origen, [
        'cod_curso' => (string) random_int(900000000, 999999999),
        'fecha_inicio' => $origen->fecha_inicio ?? now()->toDateString(),
    ]);
    $hermano->update([
        'agno_real' => $origen->agno_real,
        'semestre_real' => $origen->semestre_real,
    ]);
    Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $hermano->id_curso))->delete();

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return ['origen' => $origen, 'hermano' => $hermano->fresh(['asignacionPlan']), 'usuario' => $usuario];
}

function actividadesDelCurso(Curso $curso)
{
    return Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
        ->with(['componente.tipoComponente', 'unidad'])
        ->orderBy('id_actividad')
        ->get();
}

test('el curso hermano aparece como destino y el propio curso no', function () {
    $f = fixtureCopiaActividades() ?? $this->markTestSkipped('Sin curso con titular y actividades.');

    $ids = app(CopiaActividadesService::class)->cursosHermanos($f['origen'])->pluck('id_curso');

    expect($ids)->toContain($f['hermano']->id_curso)
        ->not->toContain($f['origen']->id_curso);
});

test('copia oculta, con la fecha elegida, el enunciado compartido y la rúbrica independiente', function () {
    $f = fixtureCopiaActividades() ?? $this->markTestSkipped('Sin curso con titular y actividades.');
    $origen = actividadesDelCurso($f['origen'])->first();

    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "archivos/test/enunciados/{$uuid}.pdf",
        'nombre_original' => 'enunciado.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 100,
        'pendiente_de_borrado' => false,
    ]);
    $origen->update(['uuid_archivo_enunciado' => $uuid, 'visible' => true]);
    $rubricaOrigen = Rubrica::create([
        'rubrica' => ['niveles' => [['nombre' => 'Criterio de prueba']]],
        'estado_rubrica' => \App\Enums\DB\EstadoRubrica::CERRADA,
        'id_actividad' => $origen->id_actividad,
    ]);

    $resultado = app(CopiaActividadesService::class)->copiar(
        collect([$origen->fresh(['componente.tipoComponente', 'unidad'])]),
        collect([$f['hermano']]),
        conRubrica: true,
        conEnunciado: true,
        fechas: [$origen->id_actividad => '2026-12-01'],
    );

    expect($resultado['creadas'])->toHaveCount(1)->and($resultado['omitidas'])->toBe([]);

    $copia = Actividad::findOrFail($resultado['creadas'][0]['id_actividad']);
    expect($copia->nombre)->toBe($origen->nombre)
        ->and($copia->visible)->toBeFalse()
        ->and($copia->fecha_limite->format('Y-m-d'))->toBe('2026-12-01')
        ->and($copia->uuid_archivo_enunciado)->toBe($uuid)
        ->and($copia->componente->id_curso)->toBe($f['hermano']->id_curso)
        ->and($copia->unidad->id_curso)->toBe($f['hermano']->id_curso)
        ->and($copia->unidad->num_unidad)->toBe($origen->unidad->num_unidad);

    $rubricaCopia = Rubrica::where('id_actividad', $copia->id_actividad)->firstOrFail();
    expect($rubricaCopia->id_rubrica)->not->toBe($rubricaOrigen->id_rubrica)
        ->and($rubricaCopia->rubrica)->toBe($rubricaOrigen->rubrica)
        ->and($rubricaCopia->estado_rubrica->value)->toBe('POSTULADA');

    if (!$copia->es_grupal) {
        expect(DB::table('agenda.actividad_asignada_grupo')->where('id_actividad', $copia->id_actividad)->count())
            ->toBe(DB::table('curso.inscripcion_curso')->where('id_curso', $f['hermano']->id_curso)->where('estado_inscripcion', 'INSCRITO')->count());
    }
});

test('sin la unidad equivalente en el destino la actividad se omite con el motivo', function () {
    $f = fixtureCopiaActividades() ?? $this->markTestSkipped('Sin curso con titular y actividades.');
    $origen = actividadesDelCurso($f['origen'])->first();

    Unidad::where('id_curso', $f['hermano']->id_curso)
        ->where('num_unidad', $origen->unidad->num_unidad)
        ->delete();

    $servicio = app(CopiaActividadesService::class);
    $compat = $servicio->compatibilidad(collect([$origen]), collect([$f['hermano']]));
    expect($compat[0]['actividades'][$origen->id_actividad]['copiable'])->toBeFalse()
        ->and($compat[0]['actividades'][$origen->id_actividad]['motivo'])->toContain('unidad');

    $resultado = $servicio->copiar(collect([$origen]), collect([$f['hermano']]), true, true);
    expect($resultado['creadas'])->toBe([])
        ->and($resultado['omitidas'][0]['motivo'])->toContain('unidad');
});

test('el endpoint copia al hermano y rechaza un curso que no es hermano', function () {
    $f = fixtureCopiaActividades() ?? $this->markTestSkipped('Sin curso con titular y actividades.');
    $ids = actividadesDelCurso($f['origen'])->pluck('id_actividad')->take(2)->all();
    $url = "/docente/cursos/{$f['origen']->id_curso}/actividades/copiar";

    $this->actingAs($f['usuario'])
        ->getJson("{$url}/opciones")
        ->assertOk()
        ->assertJsonPath('destinos.0.id_curso', $f['hermano']->id_curso);

    $antes = actividadesDelCurso($f['hermano'])->count();
    $this->actingAs($f['usuario'])
        ->from("/docente/cursos/{$f['origen']->id_curso}/actividades")
        ->post($url, ['actividades' => $ids, 'destinos' => [$f['hermano']->id_curso], 'copiar_rubrica' => true, 'copiar_enunciado' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect(actividadesDelCurso($f['hermano'])->count())->toBe($antes + count($ids));

    $otro = Curso::where('id_curso', '!=', $f['origen']->id_curso)
        ->where('id_curso', '!=', $f['hermano']->id_curso)
        ->value('id_curso');
    if ($otro) {
        $this->actingAs($f['usuario'])
            ->post($url, ['actividades' => $ids, 'destinos' => [$otro]])
            ->assertSessionHasErrors('destinos');
    }
});
