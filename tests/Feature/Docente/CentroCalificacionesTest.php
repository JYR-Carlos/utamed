<?php

/**
 * Centro de Calificaciones: cada actividad trae su avance (grupos por
 * calificar, calificados, por revisar y con feedback) con el mismo criterio
 * que la lista de actividades del curso (AvanceEvaluacionService).
 */

use App\Models\Usuario\Usuario;
use App\Services\Agenda\AvanceEvaluacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

test('cada actividad del centro trae el avance de evaluación', function () {
    $row = DB::selectOne("
        SELECT d.id_usuario
        FROM usuario.docente d
        JOIN curso.curso c ON c.id_docente_titular = d.id_docente
        JOIN curso.componente co ON co.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = co.id_componente
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE c.fecha_eliminacion IS NULL
          AND ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
        ORDER BY d.id_docente
        LIMIT 1
    ");
    if (!$row) {
        $this->markTestSkipped('No hay docentes titulares con actividades.');
    }

    // Sin fecha de cambio de clave el login obliga a cambiarla (redirige).
    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now()->subDay();
    $usuario->save();

    $this->actingAs($usuario)
        ->get('/docente/calificaciones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docente/Calificaciones')
            ->where('cursos', function ($cursos) {
                $actividades = collect($cursos)
                    ->flatMap(fn ($c) => $c['componentes'])
                    ->flatMap(fn ($comp) => $comp['actividades']);
                expect($actividades)->not->toBeEmpty();

                $avance = app(AvanceEvaluacionService::class)->porActividad($actividades->pluck('id_actividad'));
                foreach ($actividades as $a) {
                    $esperado = $avance[$a['id_actividad']] ?? null;
                    foreach (['por_calificar', 'calificados', 'por_revisar', 'revisados'] as $clave) {
                        expect($a[$clave])->toBe($esperado[$clave] ?? 0);
                    }
                }

                return true;
            })
        );
});
