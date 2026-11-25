<script lang="ts">
  /**
   * Celda compuesta "Modificar secciones I a IX": nueve permisos
   * (cursos/programas/modificar:modulo_1..9) resumidos en una etiqueta, con
   * un popover flotante (`position: fixed`) para no quedar recortado por
   * `overflow-hidden` / `overflow-x-auto` ni activar scrollbars en la tabla.
   */
  import { ChevronDown } from 'lucide-svelte';
  import PermisoTriState, {
    type ValorPermiso,
    type EstadoTransitorio,
    type TipoBloqueo,
  } from './PermisoTriState.svelte';

  interface Modulo {
    slug: string;
    numero: number;
    valor: ValorPermiso;
    activoPorRol?: boolean;
    bloqueo?: TipoBloqueo;
    motivoBloqueo?: string | null;
    estado: EstadoTransitorio;
  }

  interface Props {
    modulos: Modulo[];
    nombrePersona: string;
    onChange: (slug: string, next: ValorPermiso) => void;
  }

  let { modulos, nombrePersona, onChange }: Props = $props();

  const ROMANOS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];

  let abierto = $state(false);
  let botonRef = $state<HTMLButtonElement | null>(null);
  let panelRef = $state<HTMLDivElement | null>(null);
  let panelPos = $state<{ top: number; left: number }>({ top: 0, left: 0 });

  const activos = $derived(modulos.filter((m) => m.valor === 'asignado'));
  const revocados = $derived(modulos.filter((m) => m.valor === 'revocado'));

  const etiqueta = $derived.by(() => {
    if (activos.length === 0 && revocados.length > 0) return `${revocados.length} rev.`;
    if (activos.length === 0) return 'ninguna';
    if (activos.length === modulos.length) return `todas (${modulos.length})`;
    return `${activos.map((m) => ROMANOS[m.numero - 1]).join(', ')} de ${ROMANOS[modulos.length - 1]}`;
  });

  function calcularPosicion() {
    if (!botonRef) return;
    const rect = botonRef.getBoundingClientRect();
    const panelWidth = 264;
    const estimatedHeight = 310;
    const left = Math.max(8, Math.min(rect.right - panelWidth, window.innerWidth - panelWidth - 8));
    const spaceBelow = window.innerHeight - rect.bottom;
    const top =
      spaceBelow >= estimatedHeight || spaceBelow >= rect.top
        ? rect.bottom + 6
        : Math.max(8, rect.top - estimatedHeight - 6);
    panelPos = { top, left };
  }

  function alternarPopover() {
    if (!abierto) {
      calcularPosicion();
      abierto = true;
    } else {
      abierto = false;
    }
  }

  function manejarClickFuera(event: MouseEvent) {
    if (!abierto) return;
    const target = event.target as Node;
    if (botonRef?.contains(target) || panelRef?.contains(target)) return;
    abierto = false;
  }
</script>

<svelte:window
  onclick={manejarClickFuera}
  onkeydown={(e) => e.key === 'Escape' && (abierto = false)}
  onscroll={() => abierto && (abierto = false)}
  onresize={() => abierto && calcularPosicion()}
/>

<div class="inline-block">
  <button
    type="button"
    bind:this={botonRef}
    class="inline-flex items-center gap-1 rounded-lg border border-transparent px-1.5 py-1 text-xs font-semibold transition-colors hover:bg-slate-50 {activos.length === 0 && revocados.length > 0
      ? 'text-red-700'
      : 'text-navy'}"
    onclick={alternarPopover}
  >
    {etiqueta}
    <ChevronDown class="h-3 w-3" />
  </button>

  {#if abierto}
    <div
      bind:this={panelRef}
      class="fixed z-50 w-[264px] rounded-lg border border-gray-200 bg-white p-2.5 shadow-xl"
      style="top:{panelPos.top}px; left:{panelPos.left}px;"
    >
      <span class="mb-1.5 block px-1 text-left text-[11px] font-semibold uppercase tracking-wide text-ink-2">
        Módulos de {nombrePersona}
      </span>
      <div class="flex flex-col gap-1.5">
        {#each modulos as modulo (modulo.slug)}
          <div class="flex items-center justify-between gap-2 px-1">
            <span class="text-[12.5px] text-ink">Módulo {ROMANOS[modulo.numero - 1]}</span>
            <PermisoTriState
              valor={modulo.valor}
              activoPorRol={modulo.activoPorRol ?? false}
              bloqueo={modulo.bloqueo ?? null}
              motivoBloqueo={modulo.motivoBloqueo ?? null}
              estado={modulo.estado}
              compact
              onChange={(next) => onChange(modulo.slug, next)}
              label={`Módulo ${ROMANOS[modulo.numero - 1]} — ${nombrePersona}`}
            />
          </div>
        {/each}
      </div>
    </div>
  {/if}
</div>
