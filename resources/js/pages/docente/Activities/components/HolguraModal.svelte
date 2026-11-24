<script lang="ts">
  /**
   * Holgura personal por fecha (revisión «Mi UTAmed»): el docente elige en el
   * calendario hasta qué día puede entregar el estudiante (o el grupo) y la
   * pantalla calcula los días. Se guarda lo mismo de siempre:
   * `nro_dias_adicionales_para_bloqueo_personal`, que se suma a la holgura
   * general de la actividad. El plazo vence a las 23:59 del día elegido (la
   * base guarda días, no horas).
   */
  import { untrack } from 'svelte';
  import { ChevronLeft, ChevronRight } from 'lucide-svelte';
  import ModalInst from '@/components/docente/ModalInst.svelte';
  import { btnFantasma, btnPrimario, btnSecundario, chico, etiqueta, tarjeta } from '@/lib/inst';

  interface Props {
    nombre: string;
    subtitulo?: string;
    /** Fecha límite de la actividad (YYYY-MM-DD). */
    fechaLimite: string;
    /** Días de holgura general de la actividad. */
    holguraGeneral?: number;
    /** Días de holgura personal ya dados. */
    holguraPersonal?: number;
    guardando?: boolean;
    onGuardar: (dias: number) => void;
    onCerrar: () => void;
  }

  let {
    nombre,
    subtitulo,
    fechaLimite,
    holguraGeneral = 0,
    holguraPersonal = 0,
    guardando = false,
    onGuardar,
    onCerrar,
  }: Props = $props();

  const DIA = 86_400_000;
  const aUtc = (iso: string) => {
    const [a, m, d] = iso.slice(0, 10).split('-').map(Number);
    return Date.UTC(a, m - 1, d);
  };
  const fmtLargo = new Intl.DateTimeFormat('es-CL', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' });
  const fmtCorto = new Intl.DateTimeFormat('es-CL', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' });
  const fmtMes = new Intl.DateTimeFormat('es-CL', { month: 'long', year: 'numeric', timeZone: 'UTC' });

  /** Cierre de la actividad para todos (fecha límite + holgura general). */
  const cierre = $derived(aUtc(fechaLimite) + holguraGeneral * DIA);
  // Sólo el valor inicial: el modal se monta de nuevo cada vez que se abre.
  const inicial = untrack(() => cierre + holguraPersonal * DIA);
  let elegida = $state(inicial);
  let mesVisto = $state(Date.UTC(new Date(inicial).getUTCFullYear(), new Date(inicial).getUTCMonth(), 1));

  const dias = $derived(Math.max(0, Math.round((elegida - cierre) / DIA)));

  /** Seis semanas que empiezan en lunes y cubren el mes visto. */
  const celdas = $derived.by(() => {
    const primero = new Date(mesVisto);
    const desfase = (primero.getUTCDay() + 6) % 7;
    const inicio = mesVisto - desfase * DIA;
    return Array.from({ length: 42 }, (_, k) => {
      const t = inicio + k * DIA;
      return { t, dia: new Date(t).getUTCDate(), delMes: new Date(t).getUTCMonth() === primero.getUTCMonth() };
    });
  });

  function moverMes(delta: number) {
    const d = new Date(mesVisto);
    mesVisto = Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + delta, 1);
  }

  /**
   * Selección tipo Airbnb: la franja une el cierre con la fecha nueva sin
   * cortes (las celdas no dejan espacio entre sí); en los extremos de cada
   * semana se redondea, y los dos días de borde son círculos sobre la franja.
   */
  function franja(t: number, columna: number): string {
    if (elegida <= cierre || t < cierre || t > elegida) return '';
    const izq = t === cierre || columna === 0;
    const der = t === elegida || columna === 6;
    // Borde de la selección: la franja sale hacia el lado del rango, salvo que
    // ese lado sea el fin de la semana (ahí la franja sigue en la otra fila).
    if (t === cierre) return der ? '' : 'bg-[linear-gradient(to_right,transparent_50%,var(--color-inst-accion-s)_50%)]';
    if (t === elegida) return izq ? '' : 'bg-[linear-gradient(to_left,transparent_50%,var(--color-inst-accion-s)_50%)]';
    return `bg-inst-accion-s ${izq ? 'rounded-l-full' : ''} ${der ? 'rounded-r-full' : ''}`;
  }

  function claseDia(t: number, delMes: boolean): string {
    if (t === elegida && t > cierre) return 'border border-inst-accion bg-inst-accion font-extrabold text-inst-sobre-accion';
    if (t === cierre) return 'border-2 border-inst-accion bg-inst-tarjeta font-extrabold text-inst-accion-t';
    if (t > cierre && t < elegida) return 'border border-transparent font-bold text-inst-accion-t hover:border-inst-accion';
    if (t < cierre) return 'cursor-not-allowed border border-transparent text-inst-ink-3 opacity-50';
    return `border border-transparent hover:border-inst-ink ${delMes ? 'text-inst-ink' : 'text-inst-ink-3 opacity-60'}`;
  }
</script>

<ModalInst titulo="Holgura · {nombre}" {subtitulo} {onCerrar}>
  <div class="{tarjeta} flex flex-wrap items-center gap-2 px-3.5 py-3">
    <span class={etiqueta}>Cierre de la actividad</span>
    <span class="ml-auto font-bold">{fmtCorto.format(cierre)}, 23:59</span>
  </div>

  <div class="flex flex-col gap-2.5">
    <span class="font-bold">Elige hasta qué fecha puede entregar</span>
    <div class="{tarjeta} p-3.5">
      <div class="mb-2.5 flex items-center gap-2">
        <button type="button" class="{btnFantasma} {chico} px-0! size-11 lg:size-9" aria-label="Mes anterior" onclick={() => moverMes(-1)}>
          <ChevronLeft size={18} aria-hidden="true" />
        </button>
        <span class="flex-1 text-center font-bold first-letter:uppercase">{fmtMes.format(mesVisto)}</span>
        <button type="button" class="{btnFantasma} {chico} px-0! size-11 lg:size-9" aria-label="Mes siguiente" onclick={() => moverMes(1)}>
          <ChevronRight size={18} aria-hidden="true" />
        </button>
      </div>
      <div class="grid grid-cols-7 gap-y-1 text-center text-sm" role="grid" aria-label="Calendario">
        {#each ['L', 'M', 'M', 'J', 'V', 'S', 'D'] as d, i (i)}
          <span class="py-1 text-xs font-extrabold text-inst-ink-3" aria-hidden="true">{d}</span>
        {/each}
        {#each celdas as c, k (c.t)}
          <div class="flex h-10 items-center justify-center {franja(c.t, k % 7)}">
            <button
              type="button"
              class="flex size-10 items-center justify-center rounded-full {claseDia(c.t, c.delMes)}"
              disabled={c.t < cierre}
              aria-pressed={c.t === elegida}
              aria-label={fmtLargo.format(c.t)}
              title={c.t === cierre ? 'Cierre de la actividad' : undefined}
              onclick={() => (elegida = c.t)}
            >
              {c.dia}
            </button>
          </div>
        {/each}
      </div>
      <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1.5 text-[13px] text-inst-ink-2">
        <span class="inline-flex items-center gap-1.5">
          <span aria-hidden="true" class="size-3.5 rounded-full border-2 border-inst-accion bg-inst-tarjeta"></span>Cierre
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span aria-hidden="true" class="h-3.5 w-6 rounded-full bg-inst-accion-s"></span>Holgura
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span aria-hidden="true" class="size-3.5 rounded-full bg-inst-accion"></span>Nueva fecha
        </span>
      </div>
    </div>
  </div>

  <div class="flex items-center gap-3 rounded-md border border-l-4 border-inst-linea border-l-inst-info bg-inst-info-s px-4 py-3" role="status">
    <span class="font-inst-titulo text-[30px] leading-8 font-bold text-inst-accion-t">{dias}</span>
    <span class="flex flex-col">
      <span class="font-bold">{dias === 1 ? 'día de holgura' : 'días de holgura'}</span>
      <span class="text-[13px]">
        {#if dias === 0}
          Sin holgura: entrega hasta el cierre de la actividad.
        {:else}
          Podrá entregar hasta el {fmtLargo.format(elegida)} a las 23:59.
        {/if}
      </span>
    </span>
  </div>

  {#snippet pie()}
    {#if holguraPersonal > 0}
      <button type="button" class="{btnFantasma} {chico} text-inst-error hover:text-inst-error" disabled={guardando} onclick={() => onGuardar(0)}>
        Quitar holgura
      </button>
    {/if}
    <button type="button" class="{btnSecundario} ml-auto" disabled={guardando} onclick={onCerrar}>Cancelar</button>
    <button type="button" class={btnPrimario} disabled={guardando || dias === holguraPersonal} onclick={() => onGuardar(dias)}>
      Guardar holgura
    </button>
  {/snippet}
</ModalInst>
