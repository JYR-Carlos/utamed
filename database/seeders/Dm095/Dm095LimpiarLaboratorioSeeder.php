<?php

namespace Database\Seeders\Dm095;

use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Elimina el componente Laboratorio que Intranet/wizard creó por error en los
 * cursos DM095 (la malla sólo declara Taller: c=0, t=4, l=0), dejando el curso
 * sólo con Taller.
 *
 * Es DESTRUCTIVO, por eso exige DM095_BORRAR_LABORATORIO=1 en el entorno y va
 * curso por curso en una transacción. Antes de borrar, mueve al Taller todo lo
 * que cuelga del Laboratorio y que no debe perderse:
 *  - actividades (con sus grupos, rúbricas y entregas; su contexto cuelga del
 *    curso, no del componente, así que no hay que tocarlo);
 *  - mensajes de curso.mensaje.
 * Y elimina lo que sólo tiene sentido con el componente: inscripcion_componente,
 * docente_componente, roles y permisos especiales en su contexto, y el contexto.
 *
 * Si el Laboratorio tuviera notas de componente (inscripcion_componente.nota_componente)
 * se detiene: eso hay que mirarlo a mano.
 */
class Dm095LimpiarLaboratorioSeeder extends Seeder
{
    private const TIPO_A_BORRAR = 'Laboratorio';
    private const TIPO_DESTINO = 'Taller';

    public function run(): void
    {
        if (!filter_var(env('DM095_BORRAR_LABORATORIO', false), FILTER_VALIDATE_BOOL)) {
            $this->command->warn('Dm095LimpiarLaboratorioSeeder borra componentes. Para ejecutarlo: DM095_BORRAR_LABORATORIO=1 php artisan db:seed --class=...');
            return;
        }

        $datos = new Dm095Datos();
        $this->command->info('== Dm095LimpiarLaboratorioSeeder: quitar el Laboratorio y dejar sólo Taller ==');

        foreach ($datos->cursos() as $fila) {
            $curso = $this->localizarCurso($fila);
            if (!$curso) {
                $this->command->warn("  ! Curso {$fila['cod_asignatura']} {$fila['letra_grupo']} no encontrado; se omite.");
                continue;
            }
            DB::transaction(fn () => $this->limpiar($curso, $fila['letra_grupo']));
        }
    }

    private function localizarCurso(array $fila): ?Curso
    {
        $q = Curso::where('es_plantilla', false)->whereNull('fecha_eliminacion');
        if ($fila['cod_curso_prod']) {
            $c = (clone $q)->where('cod_curso', $fila['cod_curso_prod'])->first();
            if ($c) {
                return $c;
            }
        }
        return $q->whereHas('asignacionPlan.asignatura', fn ($a) => $a->where('cod_asignatura', $fila['cod_asignatura']))
            ->where('agno_real', $fila['agno_real'])->where('semestre_real', $fila['semestre_real'])
            ->where('indice_grupo', $fila['indice_grupo'])->first();
    }

    private function limpiar(Curso $curso, string $letra): void
    {
        $porTipo = fn (string $tipo) => Componente::where('id_curso', $curso->id_curso)
            ->whereHas('tipoComponente', fn ($q) => $q->where('tipo', $tipo))->first();

        $lab = $porTipo(self::TIPO_A_BORRAR);
        if (!$lab) {
            $this->command->line("   {$letra} [{$curso->id_curso}]: no tiene " . self::TIPO_A_BORRAR . '; nada que hacer.');
            return;
        }
        $taller = $porTipo(self::TIPO_DESTINO);
        if (!$taller) {
            throw new \RuntimeException("El curso {$curso->id_curso} no tiene " . self::TIPO_DESTINO . '; no hay dónde mover lo que cuelga del ' . self::TIPO_A_BORRAR . '.');
        }

        $conNota = DB::table('curso.inscripcion_componente')->where('id_componente', $lab->id_componente)->whereNotNull('nota_componente')->count();
        if ($conNota > 0) {
            throw new \RuntimeException("Laboratorio#{$lab->id_componente} tiene {$conNota} notas de componente; revisar a mano antes de borrar.");
        }

        // 1. Lo que se conserva se mueve al Taller.
        $movidas = Actividad::where('id_componente', $lab->id_componente)->update(['id_componente' => $taller->id_componente]);
        $mensajes = DB::table('curso.mensaje')->where('id_componente', $lab->id_componente)->update(['id_componente' => $taller->id_componente]);

        // 2. Lo que sólo existe por el componente se borra.
        $insc = DB::table('curso.inscripcion_componente')->where('id_componente', $lab->id_componente)->delete();
        $docs = DB::table('curso.docente_componente')->where('id_componente', $lab->id_componente)->delete();
        $roles = DB::table('usuario.usuario_rol_asignacion')->where('id_contexto', $lab->id_contexto)->delete();
        $permisos = DB::table('usuario.usuario_permiso_especial')->where('id_contexto', $lab->id_contexto)->delete();

        $hijos = DB::table('usuario.contexto')->where('id_contexto_padre', $lab->id_contexto)->count();
        if ($hijos > 0) {
            throw new \RuntimeException("El contexto {$lab->id_contexto} del Laboratorio tiene {$hijos} contextos hijos; revisar a mano.");
        }

        $idContexto = $lab->id_contexto;
        $idLab = $lab->id_componente;
        $lab->delete();
        DB::table('usuario.contexto')->where('id_contexto', $idContexto)->delete();

        $this->command->line(sprintf(
            '   %s [%d]: Laboratorio#%d eliminado (contexto %d). Movidas a Taller#%d: %d actividades, %d mensajes. Borrados: %d inscripciones de componente, %d docente_componente, %d roles, %d permisos.',
            $letra, $curso->id_curso, $idLab, $idContexto, $taller->id_componente, $movidas, $mensajes, $insc, $docs, $roles, $permisos
        ));
    }
}
