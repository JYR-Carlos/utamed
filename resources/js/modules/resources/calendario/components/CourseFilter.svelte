<script lang="ts">
  /**
   * Leyenda de cursos con su color, que actúa además como filtro: el docente
   * puede mostrar/ocultar las actividades de cada curso en el calendario.
   */
  import type { CalendarCurso, CursoAccent } from '../types';
  import { Check } from 'lucide-svelte';

  interface Props {
    cursos: CalendarCurso[];
    accentPorCurso: Record<number, CursoAccent>;
    /** Set de id_curso actualmente visibles. */
    activos: Set<number>;
    onToggle?: (idCurso: number) => void;
    onSoloEste?: (idCurso: number) => void;
    onTodos?: () => void;
  }

  let { cursos, accentPorCurso, activos, onToggle, onSoloEste, onTodos }: Props = $props();

  const todosActivos = $derived(cursos.every((c) => activos.has(c.id_curso)));
</script>

<div class="rounded-2xl border border-slate-200 bg-white p-4">
  <div class="mb-3 flex items-center justify-between">
    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-slate-400">Mis cursos</h3>
    {#if !todosActivos}
      <button
        type="button"
        onclick={() => onTodos?.()}
        class="text-xs font-semibold text-indigo-600 hover:text-indigo-700"
      >
        Ver todos
      </button>
    {/if}
  </div>

  {#if cursos.length === 0}
    <p class="py-4 text-center text-sm text-slate-400">Sin cursos asignados</p>
  {:else}
    <ul class="flex flex-col gap-0.5">
      {#each cursos as curso (curso.id_curso)}
        {@const accent = accentPorCurso[curso.id_curso]}
        {@const activo = activos.has(curso.id_curso)}
        <li class="group flex items-center gap-2 rounded-lg px-1.5 py-1.5 hover:bg-slate-50">
          <button
            type="button"
            onclick={() => onToggle?.(curso.id_curso)}
            class="flex flex-1 items-center gap-2.5 text-left"
            aria-pressed={activo}
          >
            <span
              class="flex h-4 w-4 shrink-0 items-center justify-center rounded-[5px] border transition-all"
              style={activo
                ? `background:${accent.base};border-color:${accent.base}`
                : `border-color:${accent.border}`}
            >
              {#if activo}<Check size={11} class="text-white" />{/if}
            </span>
            <span class="min-w-0 flex-1">
              <span
                class="block truncate text-sm font-semibold {activo
                  ? 'text-slate-700'
                  : 'text-slate-400'}"
              >
                {curso.asignatura}
              </span>
              <span class="block truncate text-[11px] text-slate-400">
                {curso.agno_real}{curso.semestre_real ? ` · Sem ${curso.semestre_real}` : ''}
                · {curso.total_actividades}
                {curso.total_actividades === 1 ? 'actividad' : 'actividades'}
              </span>
            </span>
          </button>
          <button
            type="button"
            onclick={() => onSoloEste?.(curso.id_curso)}
            class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-semibold text-slate-300 opacity-0 transition-opacity hover:bg-slate-100 hover:text-indigo-600 group-hover:opacity-100"
            title="Ver solo este curso"
          >
            Solo
          </button>
        </li>
      {/each}
    </ul>
  {/if}
</div>
