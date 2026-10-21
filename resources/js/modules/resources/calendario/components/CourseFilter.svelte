<script lang="ts">
  /**
   * Filtro por curso, que es a la vez la leyenda de colores: el color pertenece
   * al curso y se conserva entre períodos, así que esta lista es la clave de
   * lectura de las tres vistas.
   *
   * Ocultar un curso lo hace DESAPARECER del calendario (no se atenúa), pero no
   * silencia la detección de sobrecarga: esa se calcula siempre sobre todas las
   * entregas y declara cuántas quedaron fuera del filtro.
   */
  import { Check, X } from 'lucide-svelte';
  import type { CalendarCurso, CursoAccent } from '../types';
  import { ETIQUETA } from '../utils/estilos';

  interface Props {
    cursos: CalendarCurso[];
    accentPorCurso: Record<number, CursoAccent>;
    /** Set de id_curso actualmente visibles. */
    activos: Set<number>;
    /** Marcas por curso dentro del período mostrado. */
    conteoPorCurso: Record<number, number>;
    onToggle?: (idCurso: number) => void;
    onSoloEste?: (idCurso: number) => void;
    onTodos?: () => void;
  }

  let {
    cursos,
    accentPorCurso,
    activos,
    conteoPorCurso,
    onToggle,
    onSoloEste,
    onTodos,
  }: Props = $props();

  const todosActivos = $derived(cursos.every((c) => activos.has(c.id_curso)));
  const seleccionados = $derived(cursos.filter((c) => activos.has(c.id_curso)));

  function nombreCorto(curso: CalendarCurso): string {
    return curso.cod_curso ? String(curso.cod_curso) : curso.asignatura;
  }
</script>

<div class="flex flex-col">
  <!-- Filtro activo: qué cursos quedan y cómo volver atrás -->
  {#if !todosActivos}
    <div class="flex flex-col gap-2 border-b border-gray-200 px-3.5 py-3">
      <div class="flex flex-wrap gap-1.5">
        {#each seleccionados as curso (curso.id_curso)}
          {@const accent = accentPorCurso[curso.id_curso]}
          <span
            class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-uta-blue-light py-1 pl-2.5 pr-1.5"
          >
            <span
              class="h-[9px] w-[9px] shrink-0 rounded-[3px]"
              style="background:{accent.base}"
            ></span>
            <span class="text-[12px] font-semibold text-navy">{nombreCorto(curso)}</span>
            <button
              type="button"
              onclick={() => onToggle?.(curso.id_curso)}
              class="flex h-4 w-4 items-center justify-center rounded-full transition-colors hover:bg-white"
              aria-label="Quitar {nombreCorto(curso)} del filtro"
            >
              <X size={12} class="text-navy" />
            </button>
          </span>
        {/each}
        {#if seleccionados.length === 0}
          <span class="text-[11.5px] text-ink-2">Ningún curso visible</span>
        {/if}
      </div>
      <button
        type="button"
        onclick={() => onTodos?.()}
        class="self-start text-[11.5px] font-semibold text-navy transition-colors hover:text-navy-2"
      >
        Restablecer los {cursos.length}
        {cursos.length === 1 ? 'curso' : 'cursos'}
      </button>
    </div>
  {/if}

  <div class="flex flex-col gap-2.5 px-3.5 py-3.5">
    <span class={ETIQUETA}>Por curso</span>

    {#if cursos.length === 0}
      <p class="m-0 py-2 text-[12.5px] text-ink-2">Sin cursos asignados</p>
    {:else}
      <ul class="flex flex-col gap-2.5">
        {#each cursos as curso (curso.id_curso)}
          {@const accent = accentPorCurso[curso.id_curso]}
          {@const activo = activos.has(curso.id_curso)}
          <li class="group flex items-start gap-2">
            <button
              type="button"
              onclick={() => onToggle?.(curso.id_curso)}
              class="flex min-w-0 flex-1 items-start gap-2 text-left"
              aria-pressed={activo}
            >
              <span
                class="mt-px flex h-[15px] w-[15px] shrink-0 items-center justify-center rounded-[4px] border transition-colors"
                style={activo
                  ? `background:${accent.base};border-color:${accent.base}`
                  : 'background:var(--background-color-white, #FFFFFF);border-color:var(--color-slate-300, #C9CDD6);border-width:1.5px'}
              >
                {#if activo}<Check size={11} class="text-white" />{/if}
              </span>
              {#if !activo}
                <span
                  class="mt-[3px] h-[9px] w-[9px] shrink-0 rounded-[3px] opacity-35"
                  style="background:{accent.base}"
                ></span>
              {/if}
              <span class="flex min-w-0 flex-col">
                <span
                  class="truncate text-[12.5px] font-semibold leading-[1.3] {activo
                    ? 'text-ink'
                    : 'text-ink-3'}"
                >
                  {nombreCorto(curso)} · {curso.asignatura}
                </span>
                <span class="truncate text-[11px] text-ink-2">
                  {curso.letra_grupo ? `Grupo ${curso.letra_grupo} · ` : ''}{conteoPorCurso[
                    curso.id_curso
                  ] ?? 0}
                  {(conteoPorCurso[curso.id_curso] ?? 0) === 1 ? 'marca' : 'marcas'}
                </span>
              </span>
            </button>
            <button
              type="button"
              onclick={() => onSoloEste?.(curso.id_curso)}
              class="shrink-0 rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold text-ink-3 opacity-0 transition-opacity hover:bg-sand hover:text-navy focus-visible:opacity-100 group-hover:opacity-100"
              title="Ver sólo este curso"
            >
              Solo uno
            </button>
          </li>
        {/each}
      </ul>
      <p
        class="m-0 border-t border-dashed border-gray-200 pt-2 text-[11px] leading-[1.4] text-ink-2"
      >
        Color asignado desde un pool estable por
        <span class="font-mono">id_curso</span>: el mismo curso conserva su tono siempre.
      </p>
    {/if}
  </div>
</div>
