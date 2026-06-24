<script lang="ts">
  /**
   * Selector de componente como "pills" (chips). Hallazgo D-06.
   *
   * Reemplaza 3 repeticiones casi idénticas en CursoDetalle.svelte:
   * - Mi Grupo (titular): con contador de estudiantes.
   * - Asistencia (titular): sin contador, sin pill cuando hay 1 componente.
   * - Mi Componente (colegiado): sin contador.
   *
   * Cuando hay más de un componente se renderizan botones seleccionables.
   * Cuando hay exactamente uno se muestra un pill estático (a menos que
   * `mostrarSingle` sea false, p.ej. en la pestaña de Asistencia).
   */
  import { Crown } from 'lucide-svelte';

  interface ComponentePill {
    id_componente: number;
    tipo_componente: string;
    es_titular: boolean;
    total_estudiantes: number;
  }

  interface Props {
    componentes: ComponentePill[];
    componenteActivo: number | null;
    onSelect: (id: number) => void;
    /** Muestra el badge con el total de estudiantes en cada pill. */
    mostrarContador?: boolean;
    /** Renderiza el pill estático cuando sólo hay un componente. */
    mostrarSingle?: boolean;
  }

  let {
    componentes,
    componenteActivo,
    onSelect,
    mostrarContador = false,
    mostrarSingle = true,
  }: Props = $props();
</script>

{#if componentes.length > 1}
  <div class="flex gap-2 flex-wrap">
    {#each componentes as comp}
      <button
        onclick={() => onSelect(comp.id_componente)}
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium transition-all {componenteActivo === comp.id_componente ? 'bg-[#002F6C] text-white' : 'bg-[#F5F1EA] text-[#5A5E6E]'}"
      >
        {comp.tipo_componente}
        {#if comp.es_titular}
          <Crown
            size={11}
            class={componenteActivo === comp.id_componente ? 'text-[#FFB81C]' : 'text-[#8A5F00]'}
          />
        {/if}
        {#if mostrarContador}
          <span
            class="inline-flex items-center justify-center h-4 min-w-[1rem] rounded-full px-1 text-[11px] font-bold {componenteActivo === comp.id_componente ? 'bg-[rgba(255,255,255,0.25)] text-white' : 'bg-[#D0CBC1] text-[#5A5E6E]'}"
            >{comp.total_estudiantes}</span
          >
        {/if}
      </button>
    {/each}
  </div>
{:else if componentes.length === 1 && mostrarSingle}
  <span
    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold text-white bg-[#002F6C]"
  >
    {componentes[0].tipo_componente}
    {#if componentes[0].es_titular}
      <Crown size={11} class="text-[#FFB81C]" />
    {/if}
  </span>
{/if}
