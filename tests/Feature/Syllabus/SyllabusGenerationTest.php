<?php

use App\Models\Curso\Bibliografia;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;
use App\Services\ProgramaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['database.connections.pgsql.search_path' => 'usuario, agenda, administrativo, curso, public, auditoria, operaciones']);
    DB::purge('pgsql');

    // Asegurar que la restricción actualizada esté activa en la conexión de pruebas
    DB::statement('ALTER TABLE curso.bibliografia DROP CONSTRAINT IF EXISTS chk_url_notrq_when_no_es_bibuta');
    DB::statement('ALTER TABLE curso.bibliografia ADD CONSTRAINT chk_url_notrq_when_no_es_bibuta CHECK (es_bibliografia_uta = TRUE OR url IS NOT NULL OR uuid_archivo IS NOT NULL)');
    DB::statement('ALTER TABLE curso.bibliografia DROP CONSTRAINT IF EXISTS uq_bibliografia');

    $this->usuario = Usuario::factory()->create(['esta_activo' => true]);
    $this->docente = Docente::create([
        'id_usuario' => $this->usuario->id_usuario,
        'grado' => 'Doctor',
        'titulo' => 'Profesor',
        'cargo' => 'Jornada Completa',
    ]);
    $this->curso = Curso::first();
    if (!$this->curso) {
        $this->curso = Curso::create([
            'cod_curso' => 'CURSO-' . uniqid(),
            'nombre' => 'Curso de Prueba',
            'indice_grupo' => 1,
            'fecha_inicio' => now()->startOfYear(),
            'fecha_fin' => now()->endOfYear(),
            'agno_real' => 2026,
            'semestre_real' => 1,
            'estado_interno' => 'ABIERTO',
            'estado_acta' => 'NO_ENVIADO',
            'id_asignacion_plan' => 1,
        ]);
    }

    $this->actingAs($this->usuario);
});

describe('Syllabus - Generación y Sincronización de Bibliografías', function () {
    test('permite crear bibliografía UTA sin URL ni archivo', function () {
        $curso = $this->curso;
        $programa = ProgramaService::generateProgramaWithSyllabus(
            $curso,
            $this->usuario,
            [
                'secciones' => [],
                'tipo_syllabus' => 'BASICO',
                'estado' => 'BASICO_COMPLETO',
                'syllabus_creation_type' => 'simplified',
            ]
        );

        $bibData = [
            [
                'titulo' => 'Manual de Medicina Interna de Harrison',
                'autor' => 'Fauci et al.',
                'anio' => 2024,
                'es_bibliografia_uta' => true,
                'url' => null,
                'uuid_archivo' => null,
            ],
        ];

        // Usar reflexión para probar la sincronización de bibliografías
        $controller = new \App\Http\Controllers\Admin\ProgramaController();
        $reflector = new ReflectionMethod($controller, 'createBibliografiasFromSyllabus');
        $reflector->setAccessible(true);

        $reflector->invoke($controller, $programa, $bibData);

        $bib = Bibliografia::where('id_programa', $programa->id_programa)->first();
        expect($bib)->not->toBeNull();
        expect($bib->titulo)->toBe('Manual de Medicina Interna de Harrison');
        expect($bib->es_bibliografia_uta)->toBeTrue();
        expect($bib->url)->toBeNull();
        expect($bib->uuid_archivo)->toBeNull();
    });

    test('permite crear bibliografía propia (UTAMED) con URL externa', function () {
        $curso = $this->curso;
        $programa = ProgramaService::generateProgramaWithSyllabus(
            $curso,
            $this->usuario,
            [
                'secciones' => [],
                'tipo_syllabus' => 'BASICO',
                'estado' => 'BASICO_COMPLETO',
                'syllabus_creation_type' => 'simplified',
            ]
        );

        $bibData = [
            [
                'titulo' => 'Guía Clínica de Insuficiencia Cardíaca',
                'autor' => 'Sociedad Chilena de Cardiología',
                'anio' => 2025,
                'es_bibliografia_uta' => false,
                'url' => 'https://sochicar.cl/guias/insuficiencia_cardiaca_2025.pdf',
                'uuid_archivo' => null,
            ],
        ];

        $controller = new \App\Http\Controllers\Admin\ProgramaController();
        $reflector = new ReflectionMethod($controller, 'createBibliografiasFromSyllabus');
        $reflector->setAccessible(true);

        $reflector->invoke($controller, $programa, $bibData);

        $bib = Bibliografia::where('id_programa', $programa->id_programa)->first();
        expect($bib)->not->toBeNull();
        expect($bib->es_bibliografia_uta)->toBeFalse();
        expect($bib->url)->toBe('https://sochicar.cl/guias/insuficiencia_cardiaca_2025.pdf');
        expect($bib->uuid_archivo)->toBeNull();
    });

    test('permite crear bibliografía propia (UTAMED) con archivo físico y sin URL sin violar chk_url_notrq_when_no_es_bibuta', function () {
        // Crear registro en la tabla operaciones.archivo
        $uuidArchivo = (string) Str::uuid7();
        $archivo = Archivo::create([
            'uuid_archivo' => $uuidArchivo,
            'ruta_fisica' => 'archivos/syllabus/2026-s1/patologia/bibliografia/libro.pdf',
            'nombre_original' => 'apuntes_catedra.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'peso_bytes' => 10240,
            'pendiente_de_borrado' => false,
        ]);

        $curso = $this->curso;
        $programa = ProgramaService::generateProgramaWithSyllabus(
            $curso,
            $this->usuario,
            [
                'secciones' => [],
                'tipo_syllabus' => 'BASICO',
                'estado' => 'BASICO_COMPLETO',
                'syllabus_creation_type' => 'simplified',
            ]
        );

        // Caso exacto reportado por el usuario:
        // es_bibliografia_uta = false, url = null, uuid_archivo = $uuidArchivo
        $bibData = [
            [
                'titulo' => 'Apuntes de Fisiopatología Avanzada',
                'autor' => 'Dr. González',
                'cita' => 'Capítulo 3, págs 45-60',
                'anio' => 2026,
                'es_bibliografia_uta' => false,
                'url' => null,
                'uuid_archivo' => $archivo->uuid_archivo,
            ],
        ];

        $controller = new \App\Http\Controllers\Admin\ProgramaController();
        $reflector = new ReflectionMethod($controller, 'createBibliografiasFromSyllabus');
        $reflector->setAccessible(true);

        // No debe lanzar Check violation de PostgreSQL
        $reflector->invoke($controller, $programa, $bibData);

        $bib = Bibliografia::where('id_programa', $programa->id_programa)->first();
        expect($bib)->not->toBeNull();
        expect($bib->titulo)->toBe('Apuntes de Fisiopatología Avanzada');
        expect($bib->es_bibliografia_uta)->toBeFalse();
        expect($bib->url)->toBeNull();
        expect($bib->uuid_archivo)->toBe($archivo->uuid_archivo);
        expect($bib->cita)->toBe('Capítulo 3, págs 45-60');
    });

    test('asocia correctamente id_unidad al sincronizar bibliografías', function () {
        $curso = $this->curso;
        $numUnidad = rand(5000, 9999);
        $unidad = Unidad::create([
            'id_curso' => $curso->id_curso,
            'num_unidad' => $numUnidad,
            'nombre' => 'Cardiología ' . $numUnidad,
        ]);

        $programa = ProgramaService::generateProgramaWithSyllabus(
            $curso,
            $this->usuario,
            [
                'secciones' => [],
                'tipo_syllabus' => 'BASICO',
                'estado' => 'BASICO_COMPLETO',
                'syllabus_creation_type' => 'simplified',
            ]
        );

        $bibData = [
            [
                'titulo' => 'Electrocardiografía Clínica',
                'autor' => 'Vélez',
                'anio' => 2024,
                'id_unidad' => $numUnidad,
                'es_bibliografia_uta' => true,
                'url' => null,
                'uuid_archivo' => null,
            ],
        ];

        $controller = new \App\Http\Controllers\Admin\ProgramaController();
        $reflector = new ReflectionMethod($controller, 'createBibliografiasFromSyllabus');
        $reflector->setAccessible(true);

        $reflector->invoke($controller, $programa, $bibData);

        $bib = Bibliografia::where('id_programa', $programa->id_programa)->first();
        expect($bib)->not->toBeNull();
        expect($bib->id_unidad)->toBe($unidad->id_unidad);
    });

    test('marca archivo físico anterior como pendiente_de_borrado al actualizar o remover bibliografía', function () {
        $uuidArchivo1 = (string) Str::uuid7();
        $archivo1 = Archivo::create([
            'uuid_archivo' => $uuidArchivo1,
            'ruta_fisica' => 'archivos/syllabus/2026-s1/doc1.pdf',
            'nombre_original' => 'doc1.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'peso_bytes' => 5000,
            'pendiente_de_borrado' => false,
        ]);

        $uuidArchivo2 = (string) Str::uuid7();
        $archivo2 = Archivo::create([
            'uuid_archivo' => $uuidArchivo2,
            'ruta_fisica' => 'archivos/syllabus/2026-s1/doc2.pdf',
            'nombre_original' => 'doc2.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'peso_bytes' => 8000,
            'pendiente_de_borrado' => false,
        ]);

        $curso = $this->curso;
        $programa = ProgramaService::generateProgramaWithSyllabus(
            $curso,
            $this->usuario,
            [
                'secciones' => [],
                'tipo_syllabus' => 'BASICO',
                'estado' => 'BASICO_COMPLETO',
                'syllabus_creation_type' => 'simplified',
            ]
        );

        $controller = new \App\Http\Controllers\Admin\ProgramaController();
        $reflector = new ReflectionMethod($controller, 'createBibliografiasFromSyllabus');
        $reflector->setAccessible(true);

        // 1. Crear con archivo1
        $reflector->invoke($controller, $programa, [
            [
                'titulo' => 'Material Unidad 1',
                'anio' => 2026,
                'es_bibliografia_uta' => false,
                'uuid_archivo' => $archivo1->uuid_archivo,
            ]
        ]);

        $bib = Bibliografia::where('id_programa', $programa->id_programa)->first();
        expect($bib->uuid_archivo)->toBe($archivo1->uuid_archivo);
        expect($archivo1->fresh()->pendiente_de_borrado)->toBeFalse();

        // 2. Actualizar reemplazando archivo1 por archivo2
        $reflector->invoke($controller, $programa, [
            [
                'uuid_bibliografia' => $bib->uuid_bibliografia,
                'titulo' => 'Material Unidad 1 (Actualizado)',
                'anio' => 2026,
                'es_bibliografia_uta' => false,
                'uuid_archivo' => $archivo2->uuid_archivo,
            ]
        ]);

        // Archivo1 debe marcarse como pendiente de borrado
        expect($archivo1->fresh()->pendiente_de_borrado)->toBeTrue();
        expect($archivo2->fresh()->pendiente_de_borrado)->toBeFalse();

        // 3. Eliminar la bibliografía enviando lista vacía
        $reflector->invoke($controller, $programa, []);

        // Archivo2 ahora también debe marcarse como pendiente de borrado
        expect($archivo2->fresh()->pendiente_de_borrado)->toBeTrue();
        expect(Bibliografia::where('id_programa', $programa->id_programa)->count())->toBe(0);
    });

    test('permite reutilizar el mismo archivo en múltiples bibliografías y versiones de programa sin conflicto de unicidad', function () {
        $uuidArchivo = (string) Str::uuid7();
        $archivo = Archivo::create([
            'uuid_archivo' => $uuidArchivo,
            'ruta_fisica' => 'archivos/syllabus/2026-s1/shared.pdf',
            'nombre_original' => 'shared.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'peso_bytes' => 12000,
            'pendiente_de_borrado' => false,
        ]);

        $curso = $this->curso;

        // Programa versión 1
        $programa1 = ProgramaService::generateProgramaWithSyllabus(
            $curso,
            $this->usuario,
            [
                'secciones' => [],
                'tipo_syllabus' => 'BASICO',
                'estado' => 'BASICO_COMPLETO',
                'syllabus_creation_type' => 'simplified',
            ]
        );

        $controller = new \App\Http\Controllers\Admin\ProgramaController();
        $reflector = new ReflectionMethod($controller, 'createBibliografiasFromSyllabus');
        $reflector->setAccessible(true);

        $reflector->invoke($controller, $programa1, [
            [
                'titulo' => 'Manual Compartido v1',
                'autor' => 'Dr. Smith',
                'anio' => 2026,
                'es_bibliografia_uta' => false,
                'uuid_archivo' => $archivo->uuid_archivo,
            ],
        ]);

        // Programa versión 2 (nueva versión del mismo curso con el mismo archivo)
        $programa2 = ProgramaService::generateProgramaWithSyllabus(
            $curso,
            $this->usuario,
            [
                'secciones' => [],
                'tipo_syllabus' => 'BASICO',
                'estado' => 'BASICO_COMPLETO',
                'syllabus_creation_type' => 'simplified',
            ]
        );

        $reflector->invoke($controller, $programa2, [
            [
                'titulo' => 'Manual Compartido v2',
                'autor' => 'Dr. Smith',
                'anio' => 2026,
                'es_bibliografia_uta' => false,
                'uuid_archivo' => $archivo->uuid_archivo,
            ],
            [
                'titulo' => 'Otra Lectura Reutilizando Archivo',
                'autor' => 'Dr. Jones',
                'anio' => 2026,
                'es_bibliografia_uta' => false,
                'uuid_archivo' => $archivo->uuid_archivo,
            ],
        ]);

        // Deben existir 3 registros de bibliografía apuntando al mismo uuid_archivo sin error de unicidad
        $bibs = Bibliografia::where('uuid_archivo', $archivo->uuid_archivo)->get();
        expect($bibs)->toHaveCount(3);
        expect($archivo->fresh()->pendiente_de_borrado)->toBeFalse();
    });

    test('formatea bibliografias con interlineado compacto, cita en linea y enlace a archivo', function () {
        $controller = new class {
            use \App\Traits\ParsesSyllabus;

            public function testParse(array $data): array
            {
                return $this->parseSecciones($data);
            }
        };

        $uuidArchivo = (string) Str::uuid7();
        $dataSyllabus = [
            'secciones' => [
                'VI' => [
                    'contenido' => [
                        'unidades' => [
                            [
                                'numero' => 1,
                                'titulo' => 'Introducción',
                                'contenidos_items' => [],
                                'resultados_aprendizaje' => [],
                            ],
                            [
                                'numero' => 2,
                                'titulo' => 'Farmacología',
                                'contenidos_items' => [],
                                'resultados_aprendizaje' => [],
                            ],
                        ],
                    ],
                ],
                'VIII' => [
                    'contenido' => [
                        'bibliografias' => [
                            [
                                'id_unidad' => 1,
                                'titulo' => 'Libro 1',
                                'autor' => 'Pérez, Juan',
                                'anio' => 2025,
                                'cita' => 'Páginas 45-60',
                                'url' => 'https://ejemplo.com/libro1',
                                'es_bibliografia_uta' => false,
                                'uuid_archivo' => null,
                            ],
                            [
                                'id_unidad' => 2,
                                'titulo' => 'Libro 2',
                                'autor' => 'Gómez, Ana',
                                'anio' => 2026,
                                'cita' => null,
                                'es_bibliografia_uta' => true,
                                'uuid_archivo' => $uuidArchivo,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $secciones = $controller->testParse($dataSyllabus);
        $secVIII = collect($secciones)->firstWhere('numeral_romano', 'VIII');
        expect($secVIII)->not->toBeNull();

        $texto = $secVIII['contenidos'][0]['texto_contenido'];

        // Debe separar por unidad con títulos en mayúsculas, \n en citas sin em dash
        expect($texto)->toContain("UNIDAD 1: INTRODUCCIÓN\n• Pérez, Juan (2025). Libro 1. [Enlace web: https://ejemplo.com/libro1]\n  «Páginas 45-60»");
        expect($texto)->toContain("UNIDAD 2: FARMACOLOGÍA\n• Gómez, Ana (2026). Libro 2. [Biblioteca UTA · Archivo adjunto: /api/bibliografias/{$uuidArchivo}/archivo]");
        expect($texto)->not->toContain(' — ');
    });
});

