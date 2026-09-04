<script lang="ts">
  /**
   * Celda compuesta "Modificar secciones I a IX": nueve permisos
   * (cursos/programas/modificar:modulo_1..9) resumidos en una etiqueta, con
   * un popover para el detalle fino. Evita que el bloque "Programas" abra
   * nueve columnas de 34px sólo para esto.
   */
  import { ChevronDown } from 'lucide-svelte';
  import PermisoSwitch from './PermisoSwitch.svelte';

  type Estado = 'reposo' | 'guardando' | 'confirmado' | 'error';

  interface Modulo {
    slug: string;
    numero: number;
    activo: boolean;
    estado: Estado;
  }

  interface Props {
    modulos: Modulo[];
    nombrePersona: string;
    onToggle: (slug: string, next: boolean) => void;
  }

  let { modulos, nombrePersona, onToggle }: Props = $props();

  const ROMANOS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];

  let abierto = $state(false);
  let contenedorRef = $state<HTMLDivElement | null>(null);

  const activos = $derived(modulos.filter((m) => m.activo));

  const etiqueta = $derived.by(() => {
    if (activos.length === 0) return 'ninguna';
    if (activos.length === modulos.length) return `todas (${modulos.length})`;
    return `${activos.map((m) => ROMANOS[m.numero - 1]).join(', ')} de ${ROMANOS[modulos.length - 1]}`;
  });

  function manejarClickFuera(event: MouseEvent) {
    if (abierto && contenedorRef && !contenedorRef.contains(event.target as Node)) {
      abierto = false;
    }
  }
</script>

<svelte:window onclick={manejarClickFuera} onkeydown={(e) => e.key === 'Escape' && (abierto = false)} />

<div class="relative inline-block" bind:this={contenedorRef}>
  <button
    type="button"
    class="inline-flex items-center gap-1 rounded-lg border border-transparent px-1.5 py-1 text-xs font-semibold text-[#002F6C] transition-colors hover:bg-[#F8FAFC]"
    onclick={() => (abierto = !abierto)}
  >
    {etiqueta}
    <ChevronDown class="h-3 w-3" />
  </button>

  {#if abierto}
    <div
      class="absolute right-0 top-full z-10 mt-1 w-56 rounded-lg border border-[#E5E7EB] bg-white p-2.5 shadow-lg"
    >
      <span class="mb-1.5 block px-1 text-[11px] font-semibold uppercase tracking-wide text-[#5A5E6E]">
        Módulos de {nombrePersona}
      </span>
      <div class="flex flex-col gap-1.5">
        {#each modulos as modulo (modulo.slug)}
          <div class="flex items-center justify-between gap-2 px-1">
            <span class="text-[12.5px] text-[#1A1A24]">Módulo {ROMANOS[modulo.numero - 1]}</span>
            <PermisoSwitch
              checked={modulo.activo}
              estado={modulo.estado}
              onToggle={(next) => onToggle(modulo.slug, next)}
              label={`Módulo ${ROMANOS[modulo.numero - 1]} — ${nombrePersona}`}
            />
          </div>
        {/each}
      </div>
    </div>
  {/if}
</div>
