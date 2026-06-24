<script lang="ts">
  /**
   * Tabla de estudiantes inscritos (avatar inicial + nombre + usuario + nota).
   * Hallazgo D-06: extraída de las ramas TITULAR y COLEGIADO de CursoDetalle.svelte,
   * donde estaba duplicada casi idéntica.
   *
   * La columna/botón "Detalle" sólo se renderiza si `mostrarDetalle` es true
   * (rama titular). El contador "{n} estudiantes" queda fuera de este componente.
   */
  import { BookOpenCheck } from 'lucide-svelte';

  interface EstudianteItem {
    id_inscripcion_componente: number;
    nota_componente: number | null;
    estudiante: {
      nombre: string;
      username: string;
    };
  }

  interface Props {
    estudiantes: EstudianteItem[];
    /** Tipo de componente, sólo para el <caption> accesible. */
    tipoComponente?: string;
    /** Renderiza la columna "Detalle" con botón por fila. */
    mostrarDetalle?: boolean;
    onDetalle?: (item: EstudianteItem) => void;
  }

  let {
    estudiantes,
    tipoComponente = 'Componente',
    mostrarDetalle = false,
    onDetalle,
  }: Props = $props();
</script>

<div class="border border-[#E8E4DC] rounded-xl overflow-hidden">
  <table class="w-full text-sm">
    <caption class="sr-only">Estudiantes inscritos — {tipoComponente}</caption>
    <thead class="bg-[#F5F1EA] border-b border-[#E8E4DC]">
      <tr>
        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-[0.06em] text-[#8A8E9C]"
          >#</th
        >
        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-[0.06em] text-[#8A8E9C]"
          >Estudiante</th
        >
        <th
          class="px-4 py-3 text-left text-xs font-medium uppercase tracking-[0.06em] hidden sm:table-cell text-[#8A8E9C]"
          >Usuario</th
        >
        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-[0.06em] text-[#8A8E9C]"
          >Nota</th
        >
        {#if mostrarDetalle}
          <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-[0.06em] text-[#8A8E9C]"
            >Detalle</th
          >
        {/if}
      </tr>
    </thead>
    <tbody>
      {#each estudiantes as item, i}
        <tr
          class="group transition-colors duration-150 hover:bg-[#F5F1EA] {i < estudiantes.length - 1 ? 'border-b border-[#E8E4DC]' : ''}"
        >
          <td class="px-4 py-3.5 tabular-nums text-xs text-[#8A8E9C]">{i + 1}</td>
          <td class="px-4 py-3.5">
            <div class="flex items-center gap-3">
              <div
                class="flex items-center justify-center h-8 w-8 rounded-full text-xs font-semibold text-white shrink-0 bg-[#002F6C]"
              >
                {item.estudiante.nombre.charAt(0).toUpperCase()}
              </div>
              <span class="font-medium text-sm text-[#1A1A24]">{item.estudiante.nombre}</span>
            </div>
          </td>
          <td class="px-4 py-3.5 hidden sm:table-cell">
            <span class="font-mono text-[12px] text-[#5A5E6E]">{item.estudiante.username}</span>
          </td>
          <td class="px-4 py-3.5 text-right">
            {#if item.nota_componente !== null}
              <span
                class="inline-flex items-center justify-center h-7 min-w-[2.5rem] rounded-lg text-xs font-bold {item.nota_componente >= 4 ? 'bg-[#E0F5EA] text-[#0E7C4A] outline outline-1 outline-[rgba(14,124,74,0.25)]' : 'bg-[#FEE2E2] text-[#DC2626] outline outline-1 outline-[rgba(220,38,38,0.25)]'}"
              >
                {item.nota_componente}
                <span class="sr-only"
                  >{item.nota_componente >= 4 ? '— Aprobado' : '— Reprobado'}</span
                >
              </span>
            {:else}
              <span class="text-[#D0CBC1]">—</span>
            {/if}
          </td>
          {#if mostrarDetalle}
            <td class="px-4 py-3.5 text-right">
              <button
                onclick={() => onDetalle?.(item)}
                title="Ver evaluaciones, mensajes y asistencia"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-all opacity-0 group-hover:opacity-100 bg-[#E6ECF5] text-[#002F6C] border-[rgba(0,47,108,0.18)]"
              >
                <BookOpenCheck size={12} />
                Detalle
              </button>
            </td>
          {/if}
        </tr>
      {/each}
    </tbody>
  </table>
</div>
