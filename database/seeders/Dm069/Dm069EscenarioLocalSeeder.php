<?php

namespace Database\Seeders\Dm069;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\CursoService;
use App\Services\EstudianteService;
use App\Services\InscripcionCursoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * SÓLO LOCAL. Crea en desarrollo lo que en producción crearon el wizard del admin
 * y la sincronización con Intranet para DM069 (Taller de Multimedia II):
 *
 *  - el docente titular Rodrigo Tapia Santis, con su perfil usuario.docente;
 *  - los cursos A (cod_curso 10000) y B (11100) del período 2026-2, cada uno con
 *    sus componentes Taller y Laboratorio —que en DM069 SÍ corresponden: la malla
 *    declara c=0, t=2, l=2— y el titular asignado a los dos;
 *  - los 26 alumnos de A y los 23 de B, inscritos en el curso y en ambos
 *    componentes, como hace IntranetService::inscribirAutomaticamente.
 *
 * Los alumnos salen de la hoja Inscripciones de estado_produccion_2026-09-21.xlsx,
 * que es la única fuente con el RUT completo: los PDF del EVEA imprimen el cuerpo
 * sin dígito verificador y con la ñ rota.
 *
 * NO carga contenido: unidades, programa, bibliografía, actividades y rúbricas son
 * trabajo de Dm069ContenidoSeeder, que es el que sí corre en producción. Este
 * seeder existe para que aquel tenga dónde aterrizar cuando se prueba en local.
 *
 * Se niega a correr fuera de local/testing. Es idempotente: si el curso ya existe
 * no lo vuelve a crear.
 *
 * Uso:
 *   php artisan db:seed --class="Database\Seeders\Dm069\Dm069EscenarioLocalSeeder"
 *   php artisan db:seed --class="Database\Seeders\Dm069\Dm069ContenidoSeeder"
 */
class Dm069EscenarioLocalSeeder extends Seeder
{
    private const DOCENTE = [
        'username'  => 'rtapias',
        'rut'       => '15695395-4',
        'nombre1'   => 'Rodrigo',
        'nombre2'   => 'Andrés',
        'apellido1' => 'Tapia',
        'apellido2' => 'Santis',
        'email'     => 'rtapias@gestion.uta.cl',
        'grado'     => 'Magíster en Dirección y Gestión de Empresas MBA',
        'titulo'    => 'Ingeniero en Computación e Informática',
    ];

    /** Clave del docente en local. No viene de ninguna fuente: es nuestra, para poder entrar. */
    private const PASSWORD_DOCENTE_LOCAL = 'rtapia1234';

    private Dm069Datos $datos;

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command->error('Dm069EscenarioLocalSeeder es SÓLO para local/testing: en producción los cursos los crean el wizard e Intranet. Abortado.');

            return;
        }

        // db:seed corre con Model::unguarded(); los servicios de la app pasan a
        // firstOrCreate atributos que no existen en la tabla y que en la web el
        // $fillable descarta. Con la guarda puesta se comportan igual.
        Model::reguard();

        $this->datos = new Dm069Datos();
        $this->command->info('== Dm069EscenarioLocalSeeder: docente, cursos A y B, e inscripciones ==');

        $docente = $this->asegurarDocente();

        foreach ($this->datos->cursos() as $fila) {
            $curso = $this->asegurarCurso($fila, $docente);
            $this->inscribirEstudiantes($curso, $fila['letra_grupo']);
        }

        $this->command->info("\nEscenario listo. Ahora: php artisan db:seed --class=\"Database\\Seeders\\Dm069\\Dm069ContenidoSeeder\"");
    }

    // ------------------------------------------------------------------ docente

    private function asegurarDocente(): Docente
    {
        $usuario = Usuario::where('username', self::DOCENTE['username'])->first()
            ?? Usuario::where('rut', self::DOCENTE['rut'])->first();

        if (!$usuario) {
            $usuario = Usuario::create([
                'username'                 => self::DOCENTE['username'],
                'email'                    => self::DOCENTE['email'],
                'rut'                      => self::DOCENTE['rut'],
                'nombre1'                  => self::DOCENTE['nombre1'],
                'nombre2'                  => self::DOCENTE['nombre2'],
                'apellido1'                => self::DOCENTE['apellido1'],
                'apellido2'                => self::DOCENTE['apellido2'],
                'passhash'                 => Hash::make(self::PASSWORD_DOCENTE_LOCAL),
                'fecha_verificacion_email' => now(),
                'esta_activo'              => true,
                // Con fecha para que el login no quede interceptado por el cambio
                // obligatorio de contraseña: en local se entra directo.
                'fecha_cambio_passhash'    => now(),
            ]);
            $this->command->line(sprintf(
                '   usuario %s creado (RUT %s, clave local: %s).',
                self::DOCENTE['username'], self::DOCENTE['rut'], self::PASSWORD_DOCENTE_LOCAL
            ));
        } else {
            $this->command->line("   usuario {$usuario->username} ya existía (RUT {$usuario->rut}).");
        }

        $docente = Docente::firstOrCreate(
            ['id_usuario' => $usuario->id_usuario],
            ['grado' => self::DOCENTE['grado'], 'titulo' => self::DOCENTE['titulo'], 'cargo' => null]
        );

        return $docente->load('usuario');
    }

    // ------------------------------------------------------------------ cursos

    private function asegurarCurso(array $fila, Docente $docente): Curso
    {
        $existente = Curso::where('cod_curso', $fila['cod_curso_prod'])->first();
        if ($existente) {
            $this->command->line("   curso {$fila['letra_grupo']} (cod_curso {$fila['cod_curso_prod']}) ya existía: id {$existente->id_curso}.");

            return $existente;
        }

        $asignacion = AsignacionPlan::whereHas('asignatura', fn ($q) => $q->where('cod_asignatura', $fila['cod_asignatura']))
            ->whereNull('fecha_eliminacion')
            ->firstOrFail();

        $taller = TipoComponente::where('tipo', 'Taller')->firstOrFail();
        $laboratorio = TipoComponente::where('tipo', 'Laboratorio')->firstOrFail();

        // En producción los dos componentes de A y de B tienen a rtapias.
        $docentesPorComponente = [
            $taller->id_tipo_componente      => $docente->id_docente,
            $laboratorio->id_tipo_componente => $docente->id_docente,
        ];

        $curso = app(CursoService::class)->create([
            'id_asignatura'                => $asignacion->id_asignatura,
            'id_plan'                      => $asignacion->id_plan,
            'cod_curso'                    => $fila['cod_curso_prod'],
            'nombre'                       => $asignacion->asignatura?->nombre ?? 'Taller de Multimedia II',
            'fecha_inicio'                 => $fila['fecha_inicio'],
            'agno_real'                    => $fila['agno_real'],
            'semestre_real'                => $fila['semestre_real'],
            'indice_grupo'                 => $fila['indice_grupo'],
            'id_docente_sugerido'          => $docente->id_docente,
            'id_tipo_componente_principal' => $taller->id_tipo_componente,
            'tipos_componente_ids'         => [$taller->id_tipo_componente, $laboratorio->id_tipo_componente],
            'docentes_por_componente'      => $docentesPorComponente,
        ]);

        // id_contexto no es fillable: lo pone el trigger tr_curso_pre_insert, así que
        // el modelo que devuelve el servicio no lo trae hasta releerlo.
        $curso->refresh();

        $comps = Componente::with('tipoComponente')->where('id_curso', $curso->id_curso)->get()
            ->map(fn ($c) => $c->tipoComponente->tipo . '#' . $c->id_componente)->implode(', ');
        $this->command->line("   curso {$fila['letra_grupo']} creado: id {$curso->id_curso}, cod_curso {$curso->cod_curso}, {$curso->fecha_inicio->format('Y-m-d')} → {$curso->fecha_fin->format('Y-m-d')}, componentes {$comps}.");

        return $curso;
    }

    // ------------------------------------------------------------------ estudiantes

    private function inscribirEstudiantes(Curso $curso, string $letra): void
    {
        $carrera = Carrera::where('nombre', 'Diseño Multimedia')->firstOrFail();
        $estudianteService = app(EstudianteService::class);
        $inscripcionService = app(InscripcionCursoService::class);
        $creados = $inscritos = 0;

        foreach ($this->datos->inscripcionesProduccion($letra) as $e) {
            $estudiante = $estudianteService->buscarPorRut($e['rut']);
            if (!$estudiante) {
                // Mismo camino que Intranet: username = cuerpo del RUT, nombres en mayúsculas.
                $estudiante = Estudiante::createFromIntranet([
                    'rut'       => $e['rut'],
                    'dv'        => $e['dv'],
                    'nombre'    => $e['nombres'],
                    'apellido1' => $e['apellido1'],
                    'apellido2' => $e['apellido2'],
                ], $carrera);
                $creados++;
            }

            $yaInscrito = InscripcionCurso::where('id_curso', $curso->id_curso)
                ->where('id_estudiante', $estudiante->id_estudiante)
                ->exists();
            if (!$yaInscrito) {
                $inscripcionService->create(['id_curso' => $curso->id_curso, 'id_estudiante' => $estudiante->id_estudiante]);
                $inscritos++;
            }
            $estudianteService->asignarRolEnContexto($estudiante, $curso->id_contexto);

            foreach (Componente::where('id_curso', $curso->id_curso)->get() as $componente) {
                InscripcionComponente::firstOrCreate([
                    'id_estudiante' => $estudiante->id_estudiante,
                    'id_componente' => $componente->id_componente,
                ]);
                $estudianteService->asignarRolEnContexto($estudiante, $componente->id_contexto);
            }
        }

        $this->command->line("   paralelo {$letra}: {$creados} estudiantes creados, {$inscritos} inscripciones nuevas (curso + componentes + roles).");
    }
}
