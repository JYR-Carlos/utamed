<?php

use App\Models\Curso\Curso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\ProgramaPdfService;
use App\Services\ProgramaService;
use App\Traits\ParsesSyllabus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function pdfCursoConInscrito(): object
{
    return DB::selectOne("
        SELECT ic.id_curso, e.id_usuario, a.cod_asignatura
        FROM curso.inscripcion_curso ic
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        JOIN curso.curso c ON c.id_curso = ic.id_curso
        JOIN administrativo.asignacion_plan ap ON ap.id_asignacion_plan = c.id_asignacion_plan
        JOIN administrativo.asignatura a ON a.id_asignatura = ap.id_asignatura
        WHERE ic.estado_inscripcion = 'INSCRITO' AND c.fecha_eliminacion IS NULL
        ORDER BY ic.id_curso LIMIT 1
    ") ?? test()->markTestSkipped('Sin estudiante inscrito en curso.');
}

function pdfLoginAs(int $idUsuario): Usuario
{
    $usuario = Usuario::findOrFail($idUsuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();
    test()->actingAs($usuario);

    return $usuario;
}

function pdfSyllabusCompletoPayload(): array
{
    return [
        'I' => ['contenido' => [
            'nombre_asignatura' => 'Taller Integrado',
            'codigo' => '999999',
            'creditos_sct' => 5,
            'horas' => ['catedra' => 2, 'taller' => 2, 'laboratorio' => 0],
            'categoria' => 'Obligatorio',
        ]],
        'II' => ['contenido' => ['texto' => "Presentación del curso.\n\nSegundo párrafo."]],
        'III' => ['contenido' => ['texto' => 'Estándares de la profesión.']],
        'IV' => ['contenido' => [
            'competencias_genericas' => [['titulo' => 'Trabajo en equipo']],
            'competencias_especificas' => [['titulo' => 'Diseño de interfaces']],
            'subcompetencias' => [],
        ]],
        'V' => ['contenido' => [
            'items' => [['titulo' => 'Diagnóstico inicial', 'descripcion' => 'Prueba diagnóstica']],
        ]],
        'VI' => ['contenido' => [
            'unidades' => [[
                'numero' => 1,
                'titulo' => 'Fundamentos',
                'contenidos_items' => [['item' => 'Arquitectura web'], ['item' => 'HTTP']],
                'resultados_aprendizaje' => [['resultado' => 'Comprende el protocolo HTTP']],
            ]],
        ]],
        'VII' => ['contenido' => [
            'resultados_aprendizaje' => ['titulo' => 'RA', 'items' => [['resultado' => 'Construye aplicaciones']]],
            'metodologia' => ['titulo' => 'Met', 'tipo_estrategia' => 'Aprendizaje basado en proyectos'],
            'evaluacion' => ['titulo' => 'Ev', 'tipo_evaluacion' => 'Rúbricas'],
        ]],
        'VIII' => ['contenido' => [
            'bibliografias' => [
                [
                    'titulo' => 'Clean Code',
                    'autor' => 'Martin, R.',
                    'anio' => 2008,
                    'editorial' => 'Prentice Hall',
                    'es_bibliografia_uta' => true,
                    'url' => 'https://example.com/clean-code',
                    'uuid_archivo' => '11111111-1111-1111-1111-111111111111',
                    'id_unidad' => 1,
                ],
                [
                    'titulo' => 'Sandoval Yáñez, C. (2025). Documento de Ruta',
                    'autor' => 'Sandoval Yáñez, C.',
                    'anio' => 2025,
                    'es_bibliografia_uta' => false,
                ],
            ],
        ]],
        'IX' => ['contenido' => [
            'descripcion' => 'Normativa de evaluación del curso.',
            'ponderacion_optativa' => ['porcentaje' => 30],
            'tabla_componentes' => [[
                'componente' => 'Cátedra',
                'porcentaje' => 100,
                'genera_acta' => true,
                'aprobacion_obligatoria' => true,
                'asistencia_obligatoria' => 75,
            ]],
        ]],
    ];
}

test('parseSeccionesEstructuradas mapea DTOs en orden canonico I a IX sin romper parseSecciones', function () {
    $lector = new class
    {
        use ParsesSyllabus;

        public function estructuradas(array $data): array
        {
            return $this->parseSeccionesEstructuradas($data);
        }

        public function planas(array $data): array
        {
            return $this->parseSecciones($data);
        }
    };

    $data = [
        'metadata' => [
            'tipo_syllabus' => 'COMPLETO',
            'asignatura' => ['codigo' => 'DM101', 'horas' => ['autonomas' => 4]],
        ],
        'secciones' => pdfSyllabusCompletoPayload(),
    ];

    $estructuradas = $lector->estructuradas($data);
    $planas = $lector->planas($data);

    expect(array_column($estructuradas, 'numeral_romano'))
        ->toBe(['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'])
        ->and($estructuradas[0]['campos'][1]['valor'])->toBe('DM101')
        ->and($estructuradas[5]['tipo_seccion'])->toBe('unidades')
        ->and($estructuradas[5]['unidades'][0]['titulo'])->toBe('Fundamentos')
        ->and($estructuradas[7]['tipo_seccion'])->toBe('bibliografias')
        ->and($estructuradas[7]['grupos'][0]['items'][0]['referencia'])->toBe('Sandoval Yáñez, C. (2025). Documento de Ruta')
        ->and($estructuradas[7]['grupos'][1]['items'][0]['archivo_url'])->toContain('/api/bibliografias/11111111-1111-1111-1111-111111111111/archivo')
        ->and($estructuradas[8]['componentes'][0]['componente'])->toBe('Cátedra')
        ->and($planas[0]['contenidos'][0]['texto_contenido'])->toContain('Código: DM101');
});

test('parseSeccionesEstructuradas soporta formato indexado legado y ordena I a IX', function () {
    $lector = new class
    {
        use ParsesSyllabus;

        public function estructuradas(array $data): array
        {
            return $this->parseSeccionesEstructuradas($data);
        }
    };

    $legado = [
        [
            'numeral_romano' => 'VI',
            'nombre_seccion' => 'Unidades',
            'orden' => 2,
            'contenidos' => [['texto_contenido' => "Unidad 1: Intro\n• Tema A\n  • Subtema A1"]],
        ],
        [
            'numeral_romano' => 'I',
            'nombre_seccion' => 'Identificación',
            'orden' => 1,
            'contenidos' => [['texto_contenido' => "Asignatura: Prueba\nCódigo: DM001"]],
        ],
    ];

    $resultado = $lector->estructuradas($legado);

    expect(array_column($resultado, 'numeral_romano'))->toBe(['I', 'VI'])
        ->and($resultado[0]['tipo_seccion'])->toBe('bloques')
        ->and($resultado[0]['bloques'][0])->toBe(['tipo' => 'campos', 'filas' => [
            ['etiqueta' => 'Asignatura', 'valor' => 'Prueba'],
            ['etiqueta' => 'Código', 'valor' => 'DM001'],
        ]])
        ->and($resultado[1]['bloques'][0])->toMatchArray(['tipo' => 'subtitulo', 'texto' => 'Unidad 1: Intro'])
        ->and($resultado[1]['bloques'][1]['tipo'])->toBe('lista')
        ->and($resultado[1]['bloques'][1]['items'][1]['anidado'])->toBeTrue();
});

test('el PDF compone las horas y los textos libres igual que la pantalla', function () {
    $lector = new class
    {
        use ParsesSyllabus;

        public function estructuradas(array $data): array
        {
            return array_column($this->parseSeccionesEstructuradas($data), null, 'numeral_romano');
        }
    };

    $secciones = pdfSyllabusCompletoPayload();
    $secciones['I']['contenido']['horas']['autonomas'] = 104;
    $secciones['VII']['contenido']['metodologia']['tipo_estrategia'] = "Unidad 1: Fundamentos\n- Clase guiada: el docente explica.\n- Proyecto simple.";
    $pdf = $lector->estructuradas(['metadata' => ['tipo_syllabus' => 'COMPLETO'], 'secciones' => $secciones]);

    $campos = array_column($pdf['I']['campos'], 'valor', 'etiqueta');
    expect(array_slice(array_keys($campos), 3, 5))->toBe(['Horas presenciales', 'Cátedra', 'Taller', 'Laboratorio', 'Horas dedicación'])
        ->and($campos['Horas presenciales'])->toBe('4 horas semanales')
        ->and($campos['Horas dedicación'])->toBe('104 horas semestrales de trabajo autónomo')
        ->and($pdf['VII']['metodologia'][0])->toBe(['tipo' => 'subtitulo', 'texto' => 'Unidad 1: Fundamentos'])
        ->and(array_column($pdf['VII']['metodologia'][1]['items'], 'texto'))->toBe(['Clase guiada: el docente explica.', 'Proyecto simple.'])
        ->and($pdf['IX']['descripcion'])->toBe([['tipo' => 'parrafo', 'texto' => 'Normativa de evaluación del curso.']]);
});

test('ProgramaPdfService genera PDF valido con estado borrador y sincroniza asistencia', function () {
    $f = pdfCursoConInscrito();
    $curso = Curso::findOrFail($f->id_curso);
    $admin = Usuario::where('username', 'superadmin')->first() ?? test()->markTestSkipped('Sin superadmin.');
    pdfLoginAs($admin->id_usuario);

    $programa = ProgramaService::generateProgramaWithSyllabus($curso, $admin, [
        'tipo_syllabus' => 'COMPLETO',
        'estado' => 'BORRADOR',
        'secciones' => pdfSyllabusCompletoPayload(),
    ]);

    $resultado = app(ProgramaPdfService::class)->generar($curso, $programa, 'docente');
    $bytes = $resultado['pdf']->output();

    expect($bytes)->toStartWith('%PDF-')
        ->and($resultado['filename'])->toEndWith('.pdf');
});

test('admin y docente asignado pueden descargar PDF en cualquier estado; docente ajeno recibe 403', function () {
    $f = pdfCursoConInscrito();
    $curso = Curso::findOrFail($f->id_curso);
    $admin = Usuario::where('username', 'superadmin')->first() ?? test()->markTestSkipped('Sin superadmin.');

    ProgramaService::generateProgramaWithSyllabus($curso, $admin, [
        'tipo_syllabus' => 'COMPLETO',
        'estado' => 'BORRADOR',
        'secciones' => pdfSyllabusCompletoPayload(),
    ]);

    pdfLoginAs($admin->id_usuario);
    $resAdmin = $this->get("/admin/cursos/{$curso->id_curso}/programa/pdf");
    $resAdmin->assertOk();
    expect($resAdmin->headers->get('content-type'))->toContain('application/pdf')
        ->and($resAdmin->getContent())->toStartWith('%PDF-');

    if ($curso->id_docente_titular) {
        $docenteRow = DB::table('usuario.docente')->where('id_docente', $curso->id_docente_titular)->first();
        if ($docenteRow) {
            pdfLoginAs((int) $docenteRow->id_usuario);
            $resDocente = $this->get("/docente/cursos/{$curso->id_curso}/programa/pdf");
            $resDocente->assertOk();
            expect($resDocente->headers->get('content-type'))->toContain('application/pdf');
        }
    }

    $otroDocente = DB::table('usuario.docente as d')
        ->join('usuario.usuario_rol_asignacion as ura', 'ura.id_usuario', '=', 'd.id_usuario')
        ->join('usuario.rol as r', 'r.id_rol', '=', 'ura.id_rol')
        ->whereIn('r.nombre', ['Docente Titular', 'Docente Titular Restringido', 'Docente Componente'])
        ->where('ura.esta_activo', true)
        ->where('ura.fue_eliminado', false)
        ->where('d.id_docente', '!=', $curso->id_docente_titular ?? 0)
        ->whereNotExists(function ($q) use ($curso) {
            $q->select(DB::raw(1))
                ->from('curso.docente_componente as dc')
                ->join('curso.componente as c', 'c.id_componente', '=', 'dc.id_componente')
                ->whereColumn('dc.id_docente', 'd.id_docente')
                ->where('c.id_curso', $curso->id_curso);
        })
        ->first(['d.id_usuario']);

    if ($otroDocente) {
        pdfLoginAs((int) $otroDocente->id_usuario);
        $this->get("/docente/cursos/{$curso->id_curso}/programa/pdf")->assertForbidden();
    }
});

test('estudiante inscrito solo descarga PDF cuando esta APROBADO o BASICO_COMPLETO', function () {
    $f = pdfCursoConInscrito();
    $curso = Curso::findOrFail($f->id_curso);
    $admin = Usuario::where('username', 'superadmin')->first() ?? test()->markTestSkipped('Sin superadmin.');

    $programa = ProgramaService::generateProgramaWithSyllabus($curso, $admin, [
        'tipo_syllabus' => 'COMPLETO',
        'estado' => 'BORRADOR',
        'secciones' => pdfSyllabusCompletoPayload(),
    ]);

    pdfLoginAs((int) $f->id_usuario);
    $this->get("/estudiante/cursos/{$curso->id_curso}/programa/pdf")->assertNotFound();

    $programa->update(['estado' => 'APROBADO']);
    $res = $this->get("/estudiante/cursos/{$curso->id_curso}/programa/pdf");
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf')
        ->and($res->getContent())->toStartWith('%PDF-');

    $otroEstudiante = DB::table('usuario.estudiante as e')
        ->join('usuario.usuario_rol_asignacion as ura', 'ura.id_usuario', '=', 'e.id_usuario')
        ->join('usuario.rol as r', 'r.id_rol', '=', 'ura.id_rol')
        ->where('r.nombre', 'Estudiante')
        ->where('ura.esta_activo', true)
        ->where('ura.fue_eliminado', false)
        ->whereNotExists(function ($q) use ($curso) {
            $q->select(DB::raw(1))
                ->from('curso.inscripcion_curso as ic')
                ->whereColumn('ic.id_estudiante', 'e.id_estudiante')
                ->where('ic.id_curso', $curso->id_curso)
                ->where('ic.estado_inscripcion', 'INSCRITO');
        })
        ->first(['e.id_usuario']);

    if ($otroEstudiante) {
        pdfLoginAs((int) $otroEstudiante->id_usuario);
        $this->get("/estudiante/cursos/{$curso->id_curso}/programa/pdf")->assertForbidden();
    }
});

test('ayudante sin asignacion al curso recibe 403 al solicitar PDF', function () {
    $f = pdfCursoConInscrito();
    $curso = Curso::findOrFail($f->id_curso);
    $admin = Usuario::where('username', 'superadmin')->first() ?? test()->markTestSkipped('Sin superadmin.');

    ProgramaService::generateProgramaWithSyllabus($curso, $admin, [
        'tipo_syllabus' => 'COMPLETO',
        'estado' => 'BORRADOR',
        'secciones' => pdfSyllabusCompletoPayload(),
    ]);

    $rolAyudante = Rol::firstOrCreate(['nombre' => 'Ayudante'], ['creado_por' => $admin->id_usuario]);
    $otroContexto = DB::table('usuario.contexto')
        ->where('id_contexto', '!=', $curso->id_contexto ?? 0)
        ->value('id_contexto') ?? 1;

    $ayudante = Usuario::factory()->create();
    UsuarioRolAsignacion::create([
        'id_usuario' => $ayudante->id_usuario,
        'id_rol' => $rolAyudante->id_rol,
        'id_contexto' => $otroContexto,
        'asignado_por' => $admin->id_usuario,
        'creado_por' => $admin->id_usuario,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada' => now()->addMonth(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    pdfLoginAs((int) $ayudante->id_usuario);
    $this->get("/ayudante/cursos/{$curso->id_curso}/programa/pdf")->assertForbidden();
});
