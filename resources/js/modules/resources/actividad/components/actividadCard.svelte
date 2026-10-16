<script lang="ts">
  /**
   * actividadCard — Tarjeta de solo lectura de una actividad (vista
   * estudiante, pages/student/Activities/ActividadesView.svelte).
   *
   * El tipo de la prop es local porque el payload del controlador de
   * estudiante difiere del tipo Actividad del docente (usa flags booleanos
   * es_sumativa/con_entrega en vez de los enums tipo_actividad/tipo_entrega).
   *
   * Orden de lectura: etiquetas de estado, nombre y, abajo, el plazo. Una
   * actividad vencida sin entrega lleva borde rojo, la etiqueta «Vencida» y
   * el plazo en rojo, para verla sin abrirla.
   */
  import { CalendarDays } from 'lucide-svelte';
  import ChipActividad from '@/components/student/ChipActividad.svelte';
  import { diasHasta, etiquetaPlazo, formatFechaCorta } from '@/utils/formatters';

  interface Props {
    actividad: {
      id_actividad: number;
      nombre: string;
      es_grupal: boolean;
      es_sumativa: boolean;
      con_entrega: boolean;
      max_integrantes: number;
      fecha_limite: string;
      visible: boolean;
      estado?: string;
      ya_entregada?: boolean;
      fecha_entrega?: string | null;
      ya_evaluada?: boolean;
    };
  }

  let { actividad }: Props = $props();

  const plazo = $derived.by(() => {
    const dias = diasHasta(actividad.fecha_limite);
    const vencida = dias < 0;
    const urgente = !vencida && dias <= 3;
    // Vencida sin entregar: el único caso en que el plazo se vuelve alerta.
    const sinEntrega = vencida && actividad.con_entrega && !actividad.ya_entregada;

    // Con la actividad entregada el plazo ya no corre: lo dice la etiqueta
    // «Entregada», no «Vence en N días».
    const texto = actividad.ya_entregada
      ? null
      : sinEntrega
        ? `${etiquetaPlazo(dias)} · sin entrega`
        : etiquetaPlazo(dias);

    return { urgente, sinEntrega, texto };
  });
</script>

<article
  class="flex flex-col gap-3 rounded-xl border bg-white p-5 transition-colors hover:shadow-sm {plazo.sinEntrega
    ? 'border-red-300 hover:border-red-400'
    : 'border-slate-200 hover:border-uta-blue/30'}"
>
  <div class="flex flex-wrap items-center gap-2">
    <ChipActividad tipo={actividad.es_sumativa ? 'sumativa' : 'formativa'} />
    <ChipActividad
      tipo="metadato"
      texto={actividad.es_grupal ? `Grupal · hasta ${actividad.max_integrantes}` : 'Individual'}
    />
    <ChipActividad tipo="metadato" texto={actividad.con_entrega ? 'Con entrega' : 'Sin entrega'} />
    {#if actividad.ya_entregada}
      <ChipActividad tipo="entregada" />
    {:else if plazo.sinEntrega}
      <ChipActividad tipo="vencida" />
    {/if}
  </div>

  <h3 class="text-base font-semibold leading-snug text-slate-900">
    {actividad.nombre}
  </h3>

  <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2">
    <span
      class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm {plazo.sinEntrega
        ? 'bg-red-50 text-red-800'
        : plazo.urgente
          ? 'bg-amber-50 text-amber-800 border border-amber-200'
          : 'bg-gray-100 text-slate-600'}"
    >
      <CalendarDays class="h-4 w-4" aria-hidden="true" />
      <span class="font-medium">Fecha límite</span>
      <span class="font-semibold {plazo.sinEntrega ? 'text-red-800' : 'text-slate-900'}"
        >{formatFechaCorta(actividad.fecha_limite)}</span
      >
    </span>
    {#if actividad.ya_entregada && actividad.fecha_entrega}
      <span class="text-sm font-semibold text-emerald-800">
        Entregada el {formatFechaCorta(actividad.fecha_entrega)}
      </span>
    {:else if plazo.texto}
      <span
        class="text-sm font-bold {plazo.sinEntrega
          ? 'text-red-800'
          : plazo.urgente
            ? 'text-amber-800'
            : 'text-uta-blue'}"
      >
        {plazo.texto}
      </span>
    {/if}
  </div>
</article>
