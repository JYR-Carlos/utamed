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
   * `mostrarSingle` sea false, p.ej. en la pestaña de Asistencia). Con un solo
   * componente el conmutador no desaparece: queda como etiqueta del alcance.
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

  const ACTIVO = 'bg-[#E6ECF5] border-[#002F6C] text-[#002F6C] font-semibold';
  const INACTIVO =
    'bg-white border-[#D6D9E0] text-[#1A1A24] font-medium hover:bg-[#F5F1EA] hover:border-[#B9BEC9]';
</script>

{#if componentes.length > 1}
  <div class="flex gap-2 flex-wrap">
    {#each componentes as comp (comp.id_componente)}
      {@const activo = componenteActivo === comp.id_componente}
      <button
        onclick={() => onSelect(comp.id_componente)}
        aria-pressed={activo}
        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[13px] transition-colors duration-150 {activo
          ? ACTIVO
          : INACTIVO}"
      >
        {comp.tipo_componente}
        {#if comp.es_titular}
          <Crown size={11} class={activo ? 'text-[#8A5F00]' : 'text-[#8A8E9C]'} />
        {/if}
        {#if mostrarContador}
          <span
            class="font-mono text-[11px] font-semibold tabular-nums {activo
              ? 'text-[#002F6C]'
              : 'text-[#5A5E6E]'}">{comp.total_estudiantes}</span
          >
        {/if}
      </button>
    {/each}
  </div>
{:else if componentes.length === 1 && mostrarSingle}
  <span
    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[13px] {ACTIVO}"
  >
    {componentes[0].tipo_componente}
    {#if componentes[0].es_titular}
      <Crown size={11} class="text-[#8A5F00]" />
    {/if}
    {#if mostrarContador}
      <span class="font-mono text-[11px] font-semibold tabular-nums text-[#002F6C]"
        >{componentes[0].total_estudiantes}</span
      >
    {/if}
  </span>
{/if}
