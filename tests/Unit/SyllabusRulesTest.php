<?php

use App\Http\Requests\Programa\SyllabusRules;
use App\Syllabus\SyllabusSecciones;
use App\Syllabus\SyllabusTipo;
use Illuminate\Support\Facades\Validator;

/**
 * Payload equivalente al que arma buildSecciones() en SyllabusModal.svelte.
 */
function payloadSecciones(string $tipo): array
{
    $base = [
        'I' => ['contenido' => [
            'nombre_asignatura' => 'Anatomía I',
            'codigo'            => 'MED-101',
            'creditos_sct'      => 5,
            'horas'             => ['catedra' => 3, 'taller' => 1, 'laboratorio' => 2],
            'categoria'         => 'Obligatorio',
        ]],
        'II'  => ['contenido' => ['texto' => 'Presentación del curso']],
        'VI'  => ['contenido' => ['unidades' => [[
            'numero'                 => 1,
            'titulo'                 => 'Sistema óseo',
            'contenidos_items'       => [['item' => 'Huesos largos']],
            'resultados_aprendizaje' => [['resultado' => 'Identifica huesos']],
        ]]]],
        'VIII' => ['contenido' => ['recursos' => [[
            'descripcion' => 'Manual de anatomía',
            'tipo'        => 'Libro',
            'ubicacion'   => 'Biblioteca central',
        ]]]],
    ];

    if ($tipo === 'BASICO') {
        $base['VII'] = ['contenido' => ['actividades' => [[
            'id_actividad'  => null,
            'nombre'        => 'Control 1',
            'tipo'          => 'evaluada',
            'nombre_unidad' => 'Sistema óseo',
        ]]]];

        return $base;
    }

    $base['III'] = ['contenido' => ['texto' => 'Estándares del perfil']];
    $base['IV']  = ['contenido' => [
        'competencias_especificas' => [['titulo' => 'Competencia A']],
        'competencias_genericas'   => [['titulo' => 'Competencia B']],
        'subcompetencias'          => [['titulo' => 'Subcompetencia C']],
    ]];
    $base['V']   = ['contenido' => ['items' => [['titulo' => 'Diagnóstico', 'descripcion' => 'Inicial']]]];
    $base['VII'] = ['contenido' => [
        'resultados_aprendizaje' => ['titulo' => 'RA', 'items' => [['resultado' => 'Resuelve casos']]],
        'metodologia'            => ['titulo' => 'Metodología', 'tipo_estrategia' => 'ABP'],
        'evaluacion'             => ['titulo' => 'Evaluación', 'tipo_evaluacion' => 'Sumativa'],
    ]];
    $base['IX']  = ['contenido' => [
        'descripcion'          => 'Normativa del curso',
        'ponderacion_optativa' => ['porcentaje' => 20],
        'tabla_componentes'    => [[
            'componente'             => 'Cátedra',
            'porcentaje'             => 60,
            'genera_acta'            => true,
            'aprobacion_obligatoria' => false,
            'asistencia_obligatoria' => 75,
        ]],
    ]];

    return $base;
}

test('el payload del wizard valida para ambos tipos', function (string $tipo) {
    $validator = Validator::make(
        ['secciones' => payloadSecciones($tipo)],
        SyllabusRules::forTipo($tipo)
    );

    expect($validator->fails())->toBeFalse();
})->with(['BASICO', 'COMPLETO']);

test('las claves ajenas al esquema no sobreviven a validated()', function () {
    $payload = payloadSecciones('BASICO');
    $payload['I']['contenido']['inyectada'] = 'valor arbitrario';
    $payload['I']['basura'] = str_repeat('x', 100);
    $payload['XXX'] = ['contenido' => ['texto' => 'sección inventada']];

    $validated = Validator::make(['secciones' => $payload], SyllabusRules::forTipo('BASICO'))->validate();

    expect($validated['secciones']['I']['contenido'])->not->toHaveKey('inyectada');
    expect($validated['secciones']['I'])->not->toHaveKey('basura');
    expect($validated['secciones'])->not->toHaveKey('XXX');
});

test('validated() conserva todo lo que los DTO consumen (BASICO)', function () {
    $validated = Validator::make(
        ['secciones' => payloadSecciones('BASICO')],
        SyllabusRules::forTipo('BASICO')
    )->validate();

    // El orden lo fija el orden de las reglas, no el del payload: se compara el
    // conjunto de secciones presentes.
    expect(array_keys($validated['secciones']))
        ->toEqualCanonicalizing(['I', 'II', 'VI', 'VII', 'VIII']);

    // nombre_unidad no tenía regla propia: sin ella, quitar la regla padre lo
    // habría borrado del JSONB.
    expect($validated['secciones']['VII']['contenido']['actividades'][0]['nombre_unidad'])
        ->toBe('Sistema óseo');
});

test('validated() conserva las nueve secciones en COMPLETO', function () {
    $validated = Validator::make(
        ['secciones' => payloadSecciones('COMPLETO')],
        SyllabusRules::forTipo('COMPLETO')
    )->validate();

    expect(array_keys($validated['secciones']))
        ->toEqualCanonicalizing(['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX']);
});

test('lo que valida sobrevive intacto al viaje por los DTO del syllabus', function (string $tipo) {
    $validated = Validator::make(
        ['secciones' => payloadSecciones($tipo)],
        SyllabusRules::forTipo($tipo)
    )->validate();

    $secciones = SyllabusSecciones::fromArray(
        $validated['secciones'],
        $tipo === 'BASICO' ? SyllabusTipo::Basico : SyllabusTipo::Completo
    )->toArray();

    expect($secciones['I']['contenido']['nombre_asignatura'])->toBe('Anatomía I');
    expect($secciones['VI']['contenido']['unidades'][0]['titulo'])->toBe('Sistema óseo');
    expect($secciones['VIII']['contenido']['recursos'][0]['descripcion'])->toBe('Manual de anatomía');
})->with(['BASICO', 'COMPLETO']);

test('las colecciones tienen tope de volumen', function () {
    $payload = payloadSecciones('BASICO');
    $payload['VI']['contenido']['unidades'] = array_fill(0, 61, [
        'numero' => 1,
        'titulo' => 'Unidad',
    ]);

    $validator = Validator::make(['secciones' => $payload], SyllabusRules::forTipo('BASICO'));

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->keys())->toContain('secciones.VI.contenido.unidades');
});

test('los textos largos tienen tope de longitud', function () {
    $payload = payloadSecciones('BASICO');
    $payload['II']['contenido']['texto'] = str_repeat('a', 20001);

    $validator = Validator::make(['secciones' => $payload], SyllabusRules::forTipo('BASICO'));

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->keys())->toContain('secciones.II.contenido.texto');
});

test('falta la sección I: la validación falla aunque no exista regla padre', function () {
    $payload = payloadSecciones('BASICO');
    unset($payload['I']);

    $validator = Validator::make(['secciones' => $payload], SyllabusRules::forTipo('BASICO'));

    expect($validator->fails())->toBeTrue();
});
