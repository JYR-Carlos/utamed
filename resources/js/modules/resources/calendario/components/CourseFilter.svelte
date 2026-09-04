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
    <div class="flex flex-col gap-2 border-b border-[#E5E7EB] px-3.5 py-3">
      <div class="flex flex-wrap gap-1.5">
        {#each seleccionados as curso (curso.id_curso)}
          {@const accent = accentPorCurso[curso.id_curso]}
          <span
            class="inline-flex items-center gap-1.5 rounded-full border border-[#C9D6E6] bg-[#E8EDF5] py-1 pl-2.5 pr-1.5"
          >
            <span
              class="h-[9px] w-[9px] shrink-0 rounded-[3px]"
              style="background:{accent.base}"
            ></span>
            <span class="text-[12px] font-semibold text-[#002F6C]">{nombreCorto(curso)}</span>
            <button
              type="button"
              onclick={() => onToggle?.(curso.id_curso)}
              class="flex h-4 w-4 items-center justify-center rounded-full transition-colors hover:bg-white"
              aria-label="Quitar {nombreCorto(curso)} del filtro"
            >
              <X size={12} color="#002F6C" />
            </button>
          </span>
        {/each}
        {#if seleccionados.length === 0}
          <span class="text-[11.5px] text-[#5A5E6E]">Ningún curso visible</span>
        {/if}
      </div>
      <button
        type="button"
        onclick={() => onTodos?.()}
        class="self-start text-[11.5px] font-semibold text-[#002F6C] transition-colors hover:text-[#1B4789]"
      >
        Restablecer los {cursos.length}
        {cursos.length === 1 ? 'curso' : 'cursos'}
      </button>
    </div>
  {/if}

  <div class="flex flex-col gap-2.5 px-3.5 py-3.5">
    <span class={ETIQUETA}>Por curso</span>

    {#if cursos.length === 0}
      <p class="m-0 py-2 text-[12.5px] text-[#5A5E6E]">Sin cursos asignados</p>
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
                  : 'background:#FFFFFF;border-color:#C9CDD6;border-width:1.5px'}
              >
                {#if activo}<Check size={11} color="#FFFFFF" />{/if}
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
                    ? 'text-[#1A1A24]'
                    : 'text-[#8A8E9C]'}"
                >
                  {nombreCorto(curso)} · {curso.asignatura}
                </span>
                <span class="truncate text-[11px] text-[#5A5E6E]">
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
              class="shrink-0 rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold text-[#8A8E9C] opacity-0 transition-opacity hover:bg-[#F5F1EA] hover:text-[#002F6C] focus-visible:opacity-100 group-hover:opacity-100"
              title="Ver sólo este curso"
            >
              Solo uno
            </button>
          </li>
        {/each}
      </ul>
      <p
        class="m-0 border-t border-dashed border-[#E5E7EB] pt-2 text-[11px] leading-[1.4] text-[#5A5E6E]"
      >
        Color asignado desde un pool estable por
        <span class="font-mono">id_curso</span>: el mismo curso conserva su tono siempre.
      </p>
    {/if}
  </div>
</div>
