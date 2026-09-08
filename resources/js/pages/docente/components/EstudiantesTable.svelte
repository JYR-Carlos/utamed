<script lang="ts">
  /**
   * Tabla de estudiantes inscritos en un componente.
   * Hallazgo D-06: extraída de las ramas TITULAR y COLEGIADO de CursoDetalle.svelte,
   * donde estaba duplicada casi idéntica.
   *
   * Reglas del lenguaje visual (lámina «Detalle de curso»):
   * - La nota es la del COMPONENTE activo, no la del curso: por eso la cabecera
   *   de la columna lleva el nombre del componente.
   * - Escala 1,0–7,0 con coma; el 0 no existe. «Sin nota» se dibuja como pastilla
   *   punteada sin dígitos — vacío no es cero.
   * - La columna/botón "Ficha" sólo se renderiza si `mostrarDetalle` es true.
   *
   * El pie de la tabla (totales, promedio) lo aporta quien la usa mediante el
   * snippet `pie`, para que quede dentro del mismo marco redondeado.
   */
  import type { Snippet } from 'svelte';
  import { Minus } from 'lucide-svelte';
  import { formatNota } from '@/utils/formatters';

  interface EstudianteItem {
    id_inscripcion_componente: number;
    nota_componente: number | null;
    estudiante: {
      nombre: string;
      username: string;
    };
    /** Sesiones presentes / registradas en el componente. */
    asistencia?: { presentes: number; total: number };
  }

  interface Props {
    estudiantes: EstudianteItem[];
    /** Tipo de componente: encabeza la columna de nota y el <caption>. */
    tipoComponente?: string;
    /** Renderiza la columna "Ficha" con botón por fila. */
    mostrarDetalle?: boolean;
    onDetalle?: (item: EstudianteItem) => void;
    /** Pie de tabla opcional (totales, promedio del componente). */
    pie?: Snippet;
  }

  let {
    estudiantes,
    tipoComponente = 'Componente',
    mostrarDetalle = false,
    onDetalle,
    pie,
  }: Props = $props();

  const TH =
    'px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.05em] text-[#5A5E6E]';
</script>

<div class="border border-[#E5E7EB] rounded-xl overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
      <caption class="sr-only">Estudiantes inscritos — {tipoComponente}</caption>
      <thead class="bg-[#F5F1EA]">
        <tr>
          <th class="{TH} w-11">#</th>
          <th class={TH}>Estudiante</th>
          <th class="{TH} hidden sm:table-cell">Usuario</th>
          <th class="{TH} hidden md:table-cell">Asistencia</th>
          <th class={TH}>Nota {tipoComponente}</th>
          {#if mostrarDetalle}
            <th class="px-4 py-2.5"><span class="sr-only">Acciones</span></th>
          {/if}
        </tr>
      </thead>
      <tbody>
        {#each estudiantes as item, i (item.id_inscripcion_componente)}
          {@const nota = item.nota_componente}
          {@const reprobada = nota !== null && nota < 4}
          <tr
            class="border-t border-[#E5E7EB] transition-colors duration-150 hover:bg-[#F5F1EA] {i %
              2 ===
            1
              ? 'bg-[#FCFBF9]'
              : ''}"
          >
            <td class="px-4 py-2.5 font-mono text-[12.5px] tabular-nums text-[#5A5E6E]">
              {String(i + 1).padStart(2, '0')}
            </td>
            <td class="px-4 py-2.5 font-medium text-[#1A1A24]">{item.estudiante.nombre}</td>
            <td class="px-4 py-2.5 hidden sm:table-cell">
              <span class="font-mono text-[13px] text-[#5A5E6E]">{item.estudiante.username}</span>
            </td>
            <td class="px-4 py-2.5 hidden md:table-cell text-[#5A5E6E] tabular-nums">
              {#if item.asistencia && item.asistencia.total > 0}
                {item.asistencia.presentes} de {item.asistencia.total}
              {:else}
                <span class="text-[#98A0AE]">Sin sesiones</span>
              {/if}
            </td>
            <td class="px-4 py-2.5">
              {#if nota === null}
                <span
                  class="inline-flex items-center gap-1.5 border border-dashed border-[#D6D9E0] bg-white rounded-full py-[3px] pl-[9px] pr-2.5"
                >
                  <Minus size={13} class="text-[#98A0AE]" />
                  <span class="text-[11.5px] text-[#98A0AE]">sin nota</span>
                </span>
              {:else}
                <span
                  class="font-mono text-[15px] font-semibold tabular-nums {reprobada
                    ? 'text-[#B91C1C]'
                    : 'text-[#1A1A24]'}"
                >
                  {formatNota(nota)}
                </span>
                <span class="sr-only">{reprobada ? '— Reprobado' : '— Aprobado'}</span>
                {#if nota <= 1}
                  <span class="text-[11.5px] text-[#5A5E6E] ml-2">nota mínima</span>
                {/if}
              {/if}
            </td>
            {#if mostrarDetalle}
              <td class="px-4 py-2.5 text-right">
                <button
                  onclick={() => onDetalle?.(item)}
                  title="Ver evaluaciones, mensajes y asistencia"
                  class="rounded-lg px-2.5 py-1.5 text-[13px] font-medium text-[#002F6C] transition-colors hover:bg-[#E6ECF5]"
                >
                  Ficha
                </button>
              </td>
            {/if}
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
  {#if pie}
    <div class="border-t border-[#E5E7EB] px-4 py-2.5">
      {@render pie()}
    </div>
  {/if}
</div>
