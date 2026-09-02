<script lang="ts">
  import { CalendarClock, AlertTriangle, Lock } from 'lucide-svelte';
  import { formatFechaCorta, parseFechaSoloDia } from '@/utils/formatters';

  interface Props {
    fecha_limite: string;
    dias_holgura: number;
    dias_holgura_personal: number;
    /** Estado calculado por el backend (calcularEstadoGrupo/calcularEstadoBase): 'ACTIVA' | 'CERRADA'. */
    estado?: string | null;
  }

  let { fecha_limite, dias_holgura, dias_holgura_personal, estado }: Props = $props();

  function addDays(date: Date, days: number): Date {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
  }

  const fechaBase = $derived.by(() => parseFechaSoloDia(fecha_limite));
  const fechaEfectiva = $derived.by(() =>
    addDays(fechaBase, (dias_holgura || 0) + (dias_holgura_personal || 0)),
  );

  const vencioBase = $derived(new Date() > new Date(fechaBase.getFullYear(), fechaBase.getMonth(), fechaBase.getDate(), 23, 59, 59));

  // El backend ya calcula 'CERRADA' considerando ambas holguras (ver
  // ActividadAsignadaGrupo::calcularEstadoGrupo / Actividad::calcularEstadoBase);
  // aquí sólo se distingue, dentro de 'ACTIVA', si ya se pasó la fecha nominal
  // del curso (estado visual "fuera de plazo" pero todavía dentro de la holgura).
  const estadoVisual = $derived.by((): 'en_plazo' | 'fuera_de_plazo' | 'cerrada' => {
    if (estado === 'CERRADA') return 'cerrada';
    return vencioBase ? 'fuera_de_plazo' : 'en_plazo';
  });

  const diasRestantes = $derived.by(() => {
    const ms = fechaEfectiva.getTime() - new Date().getTime();
    return Math.max(0, Math.ceil(ms / 86_400_000));
  });

  const hayHolguraPersonal = $derived((dias_holgura_personal || 0) > 0);

  const estilos = {
    en_plazo: {
      section: 'border-emerald-200 border-l-4 border-l-emerald-600',
      badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      dot: 'bg-emerald-600',
      icon: 'text-emerald-700',
      label: 'En plazo',
    },
    fuera_de_plazo: {
      section: 'border-amber-200 border-l-4 border-l-amber-600',
      badge: 'bg-amber-50 text-amber-700 border-amber-200',
      dot: 'bg-amber-600',
      icon: 'text-amber-700',
      label: 'Fuera de plazo',
    },
    cerrada: {
      section: 'border-slate-300 border-l-4 border-l-slate-500',
      badge: 'bg-slate-100 text-slate-600 border-slate-300',
      dot: 'bg-slate-500',
      icon: 'text-slate-600',
      label: 'Cerrada',
    },
  } as const;

  const s = $derived(estilos[estadoVisual]);
</script>

<section class="flex flex-col gap-3.5 rounded-xl border bg-white p-5 shadow-sm {s.section}">
  <div class="flex items-center gap-2.5">
    {#if estadoVisual === 'cerrada'}
      <Lock class="h-4 w-4 {s.icon}" />
    {:else if estadoVisual === 'fuera_de_plazo'}
      <AlertTriangle class="h-4 w-4 {s.icon}" />
    {:else}
      <CalendarClock class="h-4 w-4 {s.icon}" />
    {/if}
    <h3 class="text-[15px] font-semibold text-[#1A1A24]">Tu plazo</h3>
    <span
      class="ml-auto inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {s.badge}"
    >
      <span class="h-1.5 w-1.5 rounded-full {s.dot}"></span>
      {s.label}
    </span>
  </div>

  {#if estadoVisual === 'cerrada'}
    <span class="text-[17px] font-semibold tracking-tight text-[#475569]">
      Cerrada el {formatFechaCorta(fechaEfectiva.toISOString())}
    </span>
    <span class="text-[12.5px] text-[#5A5E6E]">
      Ya no admite entregas. Puedes consultar el enunciado, la rúbrica y la agenda.
    </span>
  {:else if estadoVisual === 'fuera_de_plazo'}
    <span class="text-[17px] font-semibold tracking-tight text-[#1A1A24]">
      Venció el {formatFechaCorta(fecha_limite)}
    </span>
    <span class="text-[12.5px] text-[#1A1A24]">
      Todavía puedes entregar: se acepta hasta el <strong>{formatFechaCorta(fechaEfectiva.toISOString())}</strong>.
    </span>
  {:else}
    <div class="flex flex-wrap items-end gap-5">
      <div class="flex flex-col">
        <span class="text-xs text-[#5A5E6E]">Tu fecha efectiva de entrega</span>
        <span class="text-[22px] font-semibold leading-tight tracking-tight text-[#1A1A24]">
          {formatFechaCorta(fechaEfectiva.toISOString())}
        </span>
      </div>
      <div class="ml-auto flex flex-col">
        <span class="text-xs text-[#5A5E6E]">Tiempo restante</span>
        <span class="text-base font-semibold text-emerald-700">
          {diasRestantes} {diasRestantes === 1 ? 'día' : 'días'}
        </span>
      </div>
    </div>
  {/if}

  {#if hayHolguraPersonal && estadoVisual !== 'cerrada'}
    <span class="text-[11.5px] text-[#5A5E6E]">
      Incluye {dias_holgura_personal} {dias_holgura_personal === 1 ? 'día' : 'días'} de holgura personal concedida para esta actividad.
    </span>
  {/if}
</section>
