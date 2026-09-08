<script lang="ts">
  /**
   * Estado vacío honesto: el docente tiene cursos, pero ninguno tiene todavía
   * fechas límite, sesiones de asistencia registradas ni hitos de syllabus, así
   * que no hay absolutamente nada que pintar en ninguna vista.
   *
   * No se inventa el calendario académico (inicio/fin de semestre): el esquema
   * no tiene una tabla de períodos, sólo las fechas de cada curso.
   */
  import { Link } from '@inertiajs/svelte';
  import { ArrowUpRight, BookOpen, CalendarOff } from 'lucide-svelte';
  import type { CalendarCurso, CursoAccent } from '../types';
  import { BTN_PRIMARY, ETIQUETA } from '../utils/estilos';

  interface Props {
    cursos: CalendarCurso[];
    accentPorCurso: Record<number, CursoAccent>;
  }

  let { cursos, accentPorCurso }: Props = $props();
</script>

<div
  class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_1px_3px_rgba(0,0,0,.06)]"
>
  <div class="flex flex-col items-center gap-3.5 bg-[#FCFCFB] px-8 py-11 text-center">
    <span
      class="flex h-[46px] w-[46px] items-center justify-center rounded-xl border border-[#E5E7EB] bg-[#F5F1EA]"
    >
      <CalendarOff size={20} color="#5A5E6E" />
    </span>

    <div class="flex max-w-[560px] flex-col gap-1.5">
      <h3 class="m-0 text-[17px] font-semibold text-[#1A1A24]">
        {cursos.length === 0 ? 'No tienes cursos asignados' : 'No hay nada en el calendario'}
      </h3>
      <p class="m-0 text-[13.5px] leading-[1.5] text-[#4A4E5C]">
        {#if cursos.length === 0}
          Cuando se te asigne un curso —como titular o como docente de un componente— sus fechas
          aparecerán aquí.
        {:else}
          Tus {cursos.length}
          {cursos.length === 1 ? 'curso ya existe' : 'cursos ya existen'}, pero todavía no tienen
          actividades con fecha límite, sesiones de asistencia registradas ni hitos de syllabus:
          hasta que las tengan, aquí no aparece nada.
        {/if}
      </p>
    </div>

    <Link href="/docente/cursos" class="{BTN_PRIMARY} mt-0.5 no-underline">
      <BookOpen size={15} color="#FFFFFF" />
      Ver mis cursos
    </Link>

    {#if cursos.length > 0}
      <div
        class="mt-2 w-full max-w-[600px] overflow-hidden rounded-[10px] border border-[#E5E7EB] bg-white text-left"
      >
        <div class="border-b border-[#E5E7EB] px-3 py-2.5">
          <span class={ETIQUETA}>Tus cursos</span>
        </div>
        {#each cursos as curso (curso.id_curso)}
          {@const accent = accentPorCurso[curso.id_curso]}
          <Link
            href="/docente/cursos/{curso.id_curso}"
            class="flex items-center gap-2.5 border-b border-[#F1F1F2] px-3 py-2.5 no-underline transition-colors last:border-b-0 hover:bg-[#F5F1EA]"
          >
            <span
              class="h-2.5 w-2.5 shrink-0 rounded-[3px]"
              style="background:{accent.base}"
            ></span>
            <span class="truncate text-[12.5px] font-semibold text-[#1A1A24]">
              {curso.cod_curso ? `${curso.cod_curso} · ` : ''}{curso.asignatura}
              {curso.letra_grupo ? ` · ${curso.letra_grupo}` : ''}
            </span>
            <span class="ml-auto shrink-0 text-[11.5px] text-[#5A5E6E]">
              {curso.total_actividades}
              {curso.total_actividades === 1 ? 'actividad' : 'actividades'}
            </span>
            <ArrowUpRight size={13} class="shrink-0" color="#8A8E9C" />
          </Link>
        {/each}
      </div>
      <span class="text-[11.5px] text-[#5A5E6E]">
        Los colores se conservan: el mismo curso mantiene su tono entre períodos.
      </span>
    {/if}
  </div>
</div>
