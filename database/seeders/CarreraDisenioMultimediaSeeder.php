<?php

namespace Database\Seeders;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeder para la carrera de Diseño Multimedia.
 * 
 * Crea o encuentra:
 * - Facultad de Administración y Economía
 * - Escuela de Diseño e Innovación Tecnológica
 * - Carrera Diseño Multimedia
 * - Plan de estudio 2026 v1
 * - Catálogo completo de asignaturas y sus asignaciones al plan.
 */
class CarreraDisenioMultimediaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seedeando Carrera: Diseño Multimedia...');

        // 1. Facultad (firstOrCreate para evitar duplicados si ya existe)
        $facultad = Facultad::firstOrCreate(
            ['nombre' => 'Facultad de Administración y Economía']
        );

        // 2. Departamento
        $departamento = Departamento::firstOrCreate(
            [
                'nombre' => 'Escuela de Diseño e Innovación Tecnológica',
                'id_facultad' => $facultad->id_facultad,
            ]
        );

        // 3. Carrera
        $carrera = Carrera::firstOrCreate(
            [
                'nombre' => 'Diseño Multimedia',
                'id_departamento' => $departamento->id_departamento,
            ]
        );

        // 4. Plan de Estudios (2026 v1)
        $plan = Plan::firstOrCreate(
            [
                'id_carrera' => $carrera->id_carrera,
                'agno_plan' => 2026,
                'version_plan' => 1,
            ],
            [
                'creditos_sct_totales' => 290,
            ]
        );

        // 5. Catálogo de asignaturas y su planificación
        $asignaturas = [
            // Año 1, Semestre 1 — 29 créditos
            ['cod' => 'DM050', 'nombre' => 'Taller de Diseño',                           'creditos' => 6, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 1, 'sem' => 1],
            ['cod' => 'DM051', 'nombre' => 'Percepción y Expresión Plástica I',          'creditos' => 6, 'c' => 4, 't' => 0, 'l' => 2, 'tipo' => 'FE', 'agno' => 1, 'sem' => 1],
            ['cod' => 'DM052', 'nombre' => 'Taller de Fotografía Digital',               'creditos' => 5, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 1, 'sem' => 1],
            ['cod' => 'DM053', 'nombre' => 'Lectura en la Disciplina',                   'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'NB', 'agno' => 1, 'sem' => 1],
            ['cod' => 'DM054', 'nombre' => 'Taller de Habilidades Interpersonales',      'creditos' => 4, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'AT', 'agno' => 1, 'sem' => 1],
            ['cod' => 'DI097', 'nombre' => 'Inglés I',                                   'creditos' => 3, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'AT', 'agno' => 1, 'sem' => 1],

            // Año 1, Semestre 2 — 31 créditos
            ['cod' => 'DM055', 'nombre' => 'Taller de Diseño Gráfico',                   'creditos' => 6, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 1, 'sem' => 2],
            ['cod' => 'DM056', 'nombre' => 'Percepción y Expresión Plástica II',         'creditos' => 5, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 1, 'sem' => 2],
            ['cod' => 'DM057', 'nombre' => 'Taller de Recursos Gráficos I',              'creditos' => 6, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 1, 'sem' => 2],
            ['cod' => 'DM058', 'nombre' => 'Taller de Fotografía Publicitaria',          'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 1, 'sem' => 2],
            ['cod' => 'DM059', 'nombre' => 'Taller de Comunicación Efectiva',            'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 1, 'sem' => 2],
            ['cod' => 'DI098', 'nombre' => 'Inglés II',                                  'creditos' => 4, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'AT', 'agno' => 1, 'sem' => 2],

            // Año 2, Semestre 1 — 30 créditos
            ['cod' => 'DM060', 'nombre' => 'Taller de Diagramación y Edición',           'creditos' => 4, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 2, 'sem' => 1],
            ['cod' => 'DM061', 'nombre' => 'Taller de Recursos Gráficos II',             'creditos' => 5, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 2, 'sem' => 1],
            ['cod' => 'DM062', 'nombre' => 'Taller de Video Digital',                    'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 2, 'sem' => 1],
            ['cod' => 'DM063', 'nombre' => 'Taller de Edición de Textos',                'creditos' => 3, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 2, 'sem' => 1],
            ['cod' => 'DM064', 'nombre' => 'Taller de Multimedia I',                     'creditos' => 6, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 2, 'sem' => 1],
            ['cod' => 'DI099', 'nombre' => 'Inglés III',                                 'creditos' => 4, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'AT', 'agno' => 2, 'sem' => 1],
            ['cod' => 'DM065', 'nombre' => 'Práctica Laboral I',                         'creditos' => 3, 'c' => 0, 't' => 0, 'l' => 4, 'tipo' => 'FE', 'agno' => 2, 'sem' => 1],

            // Año 2, Semestre 2 — 30 créditos
            ['cod' => 'DM066', 'nombre' => 'Taller de Diseño Gráfico y Maquetación',     'creditos' => 4, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 2, 'sem' => 2],
            ['cod' => 'DM067', 'nombre' => 'Semiótica de la Imagen',                     'creditos' => 6, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 2, 'sem' => 2],
            ['cod' => 'DM068', 'nombre' => 'Taller de Guiones Multimedia',               'creditos' => 4, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 2, 'sem' => 2],
            ['cod' => 'DM069', 'nombre' => 'Taller de Multimedia II',                    'creditos' => 4, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 2, 'sem' => 2],
            ['cod' => 'DM070', 'nombre' => 'Taller de Recursos Gráficos III',            'creditos' => 4, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 2, 'sem' => 2],
            ['cod' => 'DI176', 'nombre' => 'Inglés IV',                                  'creditos' => 5, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'AT', 'agno' => 2, 'sem' => 2],
            ['cod' => 'DM071', 'nombre' => 'Práctica Laboral II',                        'creditos' => 3, 'c' => 0, 't' => 0, 'l' => 4, 'tipo' => 'FE', 'agno' => 2, 'sem' => 2],

            // Año 3, Semestre 1 — 28 créditos
            ['cod' => 'DM072', 'nombre' => 'Taller de Medios de Comunicación',           'creditos' => 4, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 1],
            ['cod' => 'DM073', 'nombre' => 'Gestión de Contenidos Multimedia',           'creditos' => 4, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 1],
            ['cod' => 'DM074', 'nombre' => 'Animación 2D',                               'creditos' => 6, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 1],
            ['cod' => 'DM075', 'nombre' => 'Taller de Música y Sonido',                  'creditos' => 3, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 3, 'sem' => 1],
            ['cod' => 'DM076', 'nombre' => 'Laboratorio de Video Digital',               'creditos' => 6, 'c' => 0, 't' => 0, 'l' => 4, 'tipo' => 'FE', 'agno' => 3, 'sem' => 1],
            ['cod' => 'DM077', 'nombre' => 'Taller Profesional I',                       'creditos' => 5, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 1],

            // Año 3, Semestre 2 — 32 créditos
            ['cod' => 'DM078', 'nombre' => 'Seminario de la Investigación',              'creditos' => 6, 'c' => 2, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 2],
            ['cod' => 'DM079', 'nombre' => 'Taller de Producción Multimedia I',          'creditos' => 5, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 2],
            ['cod' => 'DM080', 'nombre' => 'Tecnologías aplicadas I',                    'creditos' => 5, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 3, 'sem' => 2],
            ['cod' => 'DM081', 'nombre' => 'Modelado 3D',                                'creditos' => 7, 'c' => 3, 't' => 3, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 2],
            ['cod' => 'IN188', 'nombre' => 'Habilidades Emprendedoras',                  'creditos' => 4, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'AT', 'agno' => 3, 'sem' => 2],
            ['cod' => 'DM082', 'nombre' => 'Taller Profesional II',                      'creditos' => 5, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 3, 'sem' => 2],

            // Año 4, Semestre 1 — 32 créditos
            ['cod' => 'DM083', 'nombre' => 'Seminario de Proyecto Multimedia',           'creditos' => 6, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 4, 'sem' => 1],
            ['cod' => 'DM084', 'nombre' => 'Taller de Producción Multimedia II',         'creditos' => 5, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 4, 'sem' => 1],
            ['cod' => 'DM085', 'nombre' => 'Tecnologías aplicadas II',                   'creditos' => 4, 'c' => 0, 't' => 2, 'l' => 2, 'tipo' => 'FE', 'agno' => 4, 'sem' => 1],
            ['cod' => 'DM086', 'nombre' => 'Animación 3D',                               'creditos' => 6, 'c' => 3, 't' => 3, 'l' => 0, 'tipo' => 'FE', 'agno' => 4, 'sem' => 1],
            ['cod' => 'DM087', 'nombre' => 'Diseño de Interfaces',                       'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 4, 'sem' => 1],
            ['cod' => 'DM088', 'nombre' => 'Taller Profesional III',                     'creditos' => 6, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 4, 'sem' => 1],

            // Año 4, Semestre 2 — Práctica Profesional (12 créditos)
            ['cod' => 'DM089', 'nombre' => 'Práctica Profesional',                       'creditos' => 12, 'c' => 0, 't' => 0, 'l' => 40, 'tipo' => 'FE', 'agno' => 4, 'sem' => 2],

            // Año 5, Semestre 1 — 28 créditos
            ['cod' => 'DM090', 'nombre' => 'Evaluación Educacional',                     'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 1],
            ['cod' => 'DM091', 'nombre' => 'Diseño de Medios Didácticos',                'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 1],
            ['cod' => 'DM092', 'nombre' => 'Diseño Curricular Mediado Tecnológicamente', 'creditos' => 4, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 1],
            ['cod' => 'DM093', 'nombre' => 'Seminario de Título',                        'creditos' => 6, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 1],
            ['cod' => 'DM094', 'nombre' => 'Ética',                                      'creditos' => 2, 'c' => 2, 't' => 1, 'l' => 0, 'tipo' => 'FH', 'agno' => 5, 'sem' => 1],
            ['cod' => 'DM095', 'nombre' => 'Taller Profesional IV',                      'creditos' => 6, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 1],

            // Año 5, Semestre 2 — 28 créditos
            ['cod' => 'DM096', 'nombre' => 'Estrategias de Marketing',                   'creditos' => 4, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 2],
            ['cod' => 'DM097', 'nombre' => 'Taller de Estrategias Publicitarias',        'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 2],
            ['cod' => 'DM098', 'nombre' => 'Taller de Identidad Corporativa',            'creditos' => 5, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 2],

            // Repetición en el plan (misma asignatura en segundo semestre de título)
            ['cod' => 'DM093', 'nombre' => 'Seminario de Título',                        'creditos' => 6, 'c' => 2, 't' => 2, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 2],
            ['cod' => 'DM094', 'nombre' => 'Ética',                                      'creditos' => 2, 'c' => 2, 't' => 1, 'l' => 0, 'tipo' => 'FH', 'agno' => 5, 'sem' => 2],
            ['cod' => 'DM095', 'nombre' => 'Taller Profesional IV',                      'creditos' => 6, 'c' => 0, 't' => 4, 'l' => 0, 'tipo' => 'FE', 'agno' => 5, 'sem' => 2],
        ];

        $asignaturasCreadas = 0;
        $asignacionesCreadas = 0;

        foreach ($asignaturas as $data) {
            $asignatura = Asignatura::firstOrCreate(
                ['cod_asignatura' => $data['cod']],
                [
                    'nombre'            => $data['nombre'],
                    'descripcion'       => 'Tipo: ' . ($data['tipo'] ?? 'FE'),
                    'creditos_sct'      => $data['creditos'] ?? 0,
                    'horas_catedra'     => $data['c'] ?? 0,
                    'horas_taller'      => $data['t'] ?? 0,
                    'horas_laboratorio' => $data['l'] ?? 0,
                    'horas_dirigidas'   => 0,
                    'horas_autonomas'   => 0,
                ]
            );

            if ($asignatura->wasRecentlyCreated) {
                $asignaturasCreadas++;
            }

            $asignacion = AsignacionPlan::firstOrCreate([
                'id_asignatura'        => $asignatura->id_asignatura,
                'id_plan'              => $plan->id_plan,
                'agno_planificado'     => $data['agno'],
                'semestre_planificado' => $data['sem'],
            ]);

            if ($asignacion->wasRecentlyCreated) {
                $asignacionesCreadas++;
            }
        }

        $this->command?->info("✓ Diseño Multimedia: {$asignaturasCreadas} asignaturas nuevas, {$asignacionesCreadas} asignaciones al plan.");
    }
}
