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
   *
   * Estilo del mockup «Detalle del curso · Mi Grupo» (auditoría 2026-10-19):
   * 15 px, borde de control #7B8AA0, activo en navy claro. La corona de
   * «titular del componente» se quitó del chip; el dato queda en `title` y
   * para lectores de pantalla.
   */

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

  const BASE =
    'inline-flex h-11 items-center gap-2 rounded-full border px-4 text-[15px] transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uta-blue focus-visible:ring-offset-2 sm:h-[38px]';
  const ACTIVO = 'bg-uta-blue-light border-uta-blue text-uta-blue font-semibold';
  const INACTIVO = 'bg-white border-[#7B8AA0] text-[#1A1A24] font-medium hover:bg-slate-50';

  function titulo(c: ComponentePill): string | undefined {
    return c.es_titular ? 'Eres titular de este componente' : undefined;
  }
</script>

{#snippet contenido(comp: ComponentePill)}
  {comp.tipo_componente}
  {#if comp.es_titular}<span class="sr-only">(eres titular)</span>{/if}
  {#if mostrarContador}
    <span class="font-semibold tabular-nums">{comp.total_estudiantes}</span>
  {/if}
{/snippet}

{#if componentes.length > 1}
  <div class="flex flex-wrap gap-2">
    {#each componentes as comp (comp.id_componente)}
      {@const activo = componenteActivo === comp.id_componente}
      <button
        type="button"
        onclick={() => onSelect(comp.id_componente)}
        aria-pressed={activo}
        title={titulo(comp)}
        class="{BASE} {activo ? ACTIVO : INACTIVO}"
      >
        {@render contenido(comp)}
      </button>
    {/each}
  </div>
{:else if componentes.length === 1 && mostrarSingle}
  <span class="{BASE} {ACTIVO}" title={titulo(componentes[0])}>
    {@render contenido(componentes[0])}
  </span>
{/if}
