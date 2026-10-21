<script lang="ts">
  /**
   * Alertas de syllabus: sólo cursos donde el docente es titular (RECHAZADO,
   * BORRADOR o EN_REVISION). APROBADO no genera alerta. El estado y la razón
   * de rechazo vienen de curso.programa + auditoria.programa_historial
   * (Docente\DashboardController::resolverEstadoSyllabus) — nada inventado.
   */
  import { Link } from '@inertiajs/svelte';
  import { AlertTriangle, XCircle, FilePenLine, Clock, ArrowUpRight } from 'lucide-svelte';

  interface Alerta {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    estado_syllabus: 'RECHAZADO' | 'BORRADOR' | 'EN_REVISION';
    rechazo?: { razon: string; por: string | null; fecha: string | null } | null;
  }

  interface Props {
    alertas: Alerta[];
  }

  let { alertas }: Props = $props();

  const criticas = $derived(alertas.filter((a) => a.estado_syllabus === 'RECHAZADO').length);
  const principal = $derived(alertas[0]);
  const resto = $derived(alertas.slice(1));

  function tono(estado: Alerta['estado_syllabus']) {
    if (estado === 'RECHAZADO') {
      return {
        border: 'border-red-200',
        bg: 'bg-zinc-50',
        accent: 'border-l-red-600',
        iconBg: 'bg-red-50',
        iconColor: 'text-red-600',
        label: 'Crítica · rechazado',
        labelColor: 'text-red-700',
        Icon: XCircle,
      };
    }
    if (estado === 'BORRADOR') {
      return {
        border: 'border-amber-200',
        bg: 'bg-white',
        accent: 'border-l-amber-600',
        iconBg: 'bg-amber-50',
        iconColor: 'text-amber-700',
        label: 'Advertencia · borrador',
        labelColor: 'text-amber-700',
        Icon: FilePenLine,
      };
    }
    return {
      border: 'border-gray-200',
      bg: 'bg-white',
      accent: 'border-l-slate-500',
      iconBg: 'bg-slate-100',
      iconColor: 'text-slate-500',
      label: 'Informativo · en revisión',
      labelColor: 'text-ink-2',
      Icon: Clock,
    };
  }
</script>

<section class="flex flex-col gap-3.5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
  <div class="flex items-center gap-2">
    <AlertTriangle class="h-4 w-4 text-ink-2" />
    <h3 class="text-base font-semibold text-ink">Alertas de syllabus</h3>
    {#if criticas > 0}
      <span
        class="ml-auto inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700"
      >
        {criticas} requiere{criticas === 1 ? '' : 'n'} acción
      </span>
    {/if}
  </div>

  {#if principal}
    {@const t = tono(principal.estado_syllabus)}
    <article class="flex items-start gap-3 rounded-[10px] border {t.border} {t.bg} border-l-[3px] {t.accent} p-3.5">
      <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {t.iconBg}">
        <t.Icon class="h-[15px] w-[15px] {t.iconColor}" />
      </div>
      <div class="flex min-w-0 flex-1 flex-col gap-1.5">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-[10.5px] font-semibold uppercase tracking-wide {t.labelColor}">{t.label}</span>
          <span class="font-mono text-[11.5px] text-ink-2">
            {principal.cod_curso}{#if principal.letra_grupo} · Grupo {principal.letra_grupo}{/if}
          </span>
        </div>
        <span class="text-[14.5px] font-semibold leading-snug text-ink">{principal.nombre}</span>
        {#if principal.estado_syllabus === 'RECHAZADO' && principal.rechazo}
          <p class="max-w-[760px] text-[13px] leading-snug text-zinc-600">
            <span class="font-semibold text-ink">Razón:</span> "{principal.rechazo.razon}"
            {#if principal.rechazo.por}— {principal.rechazo.por}{#if principal.rechazo.fecha}, {principal.rechazo.fecha}{/if}{/if}
          </p>
        {:else if principal.estado_syllabus === 'BORRADOR'}
          <span class="text-[12.5px] text-ink-2">Aún no se ha enviado a revisión.</span>
        {:else}
          <span class="text-[12.5px] text-ink-2">En manos de la jefatura de carrera · no requiere acción tuya.</span>
        {/if}
      </div>
      {#if principal.estado_syllabus !== 'EN_REVISION'}
        <!-- Directo al asistente: BORRADOR y RECHAZADO (borrador + razón) se editan ahí -->
        <Link
          href={`/docente/cursos/${principal.id_curso}/programa/editar`}
          class="flex shrink-0 items-center gap-1.5 rounded-lg bg-navy px-3.5 py-2 text-[13px] font-semibold text-white no-underline transition-colors hover:bg-navy-hover"
        >
          {principal.estado_syllabus === 'RECHAZADO' ? 'Corregir syllabus' : 'Continuar redacción'}
          <ArrowUpRight class="h-3.5 w-3.5" />
        </Link>
      {/if}
    </article>
  {/if}

  {#if resto.length > 0}
    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
      {#each resto as alerta (alerta.id_curso)}
        {@const t = tono(alerta.estado_syllabus)}
        <article class="flex flex-col gap-1.5 rounded-[10px] border {t.border} border-l-[3px] {t.accent} p-3">
          <div class="flex items-start gap-2.5">
            <div class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-lg {t.iconBg}">
              <t.Icon class="h-3.5 w-3.5 {t.iconColor}" />
            </div>
            <div class="flex min-w-0 flex-col gap-0.5">
              <div class="flex items-center gap-2">
                <span class="text-[10.5px] font-semibold uppercase tracking-wide {t.labelColor}">{t.label}</span>
                <span class="font-mono text-[11.5px] text-ink-2">{alerta.cod_curso}</span>
              </div>
              <span class="text-[13.5px] font-semibold leading-snug text-ink">{alerta.nombre}</span>
            </div>
          </div>
        </article>
      {/each}
    </div>
  {/if}
</section>
