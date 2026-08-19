<script lang="ts">
  /**
   * actividadCard — Tarjeta de solo lectura de una actividad (vista
   * estudiante, pages/student/Activities/ActividadesView.svelte).
   *
   * El tipo de la prop es local porque el payload del controlador de
   * estudiante difiere del tipo Actividad del docente (usa flags booleanos
   * es_sumativa/con_entrega en vez de los enums tipo_actividad/tipo_entrega).
   *
   * Mantiene la forma de las tarjetas del portal (redondeadas, borde gris,
   * elevación al pasar el mouse). El granate UTA sólo aparece donde significa
   * algo: actividades sumativas y plazos que están por vencer.
   */
  import { CalendarDays, FileCheck2, FileMinus2, User, Users } from 'lucide-svelte';
  import { formatFechaCorta, parseFechaSoloDia } from '@/utils/formatters';

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
    };
  }

  let { actividad }: Props = $props();

  const plazo = $derived.by(() => {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const limite = parseFechaSoloDia(actividad.fecha_limite);
    const dias = Math.round((limite.getTime() - hoy.getTime()) / 86_400_000);

    if (dias < 0) return { etiqueta: 'Cerró el', urgente: false };
    if (dias === 0) return { etiqueta: 'Vence hoy', urgente: true };
    if (dias <= 3) return { etiqueta: 'Cierra pronto', urgente: true };
    return { etiqueta: 'Fecha límite', urgente: false };
  });
</script>

<article
  class="rounded-3xl border border-gray-200 bg-white p-6 transition-all duration-300 hover:border-uta-blue/30 hover:shadow-lg hover:-translate-y-0.5"
>
  <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 mb-4">
    <h3 class="text-lg font-bold text-gray-900 leading-tight flex-1 min-w-0">
      {actividad.nombre}
    </h3>
    <span
      class="shrink-0 text-xs font-bold px-2.5 py-1 rounded-lg {actividad.es_sumativa
        ? 'bg-uta-red-light text-uta-red'
        : 'bg-uta-blue-light text-uta-blue'}"
    >
      {actividad.es_sumativa ? 'Sumativa' : 'Formativa'}
    </span>
  </div>

  <div class="flex flex-wrap items-center gap-2">
    <span
      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-gray-600 bg-gray-50 border border-gray-100"
    >
      {#if actividad.es_grupal}
        <Users class="w-3.5 h-3.5" />
        Grupal · hasta {actividad.max_integrantes}
      {:else}
        <User class="w-3.5 h-3.5" />
        Individual
      {/if}
    </span>
    <span
      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-gray-600 bg-gray-50 border border-gray-100"
    >
      {#if actividad.con_entrega}
        <FileCheck2 class="w-3.5 h-3.5" />
        Con entrega
      {:else}
        <FileMinus2 class="w-3.5 h-3.5" />
        Sin entrega
      {/if}
    </span>
    <span
      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium sm:ml-auto {plazo.urgente
        ? 'bg-uta-red-light text-uta-red border border-uta-red/20'
        : 'bg-gray-50 text-gray-600 border border-gray-100'}"
    >
      <CalendarDays class="w-3.5 h-3.5" />
      {plazo.etiqueta}
      <span class="font-semibold">{formatFechaCorta(actividad.fecha_limite)}</span>
    </span>
  </div>
</article>
