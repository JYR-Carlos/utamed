<script lang="ts">
  /**
   * Rail de alertas de la jefatura de carrera.
   *
   * Dos reglas de la lámina mandan aquí:
   *  - La única acción rellena de toda la pantalla es la de la alerta crítica.
   *    La cabecera de la página cede deliberadamente la acción primaria al rail.
   *  - Sin alertas el rail NO queda vacío ni se rellena con una ilustración:
   *    conserva su altura con la confirmación en verde y las comprobaciones del
   *    período, cada una con su razón (N/N). Sólo se listan las comprobaciones
   *    que el controlador puede calcular de verdad.
   *
   * Severidad, título, contador y acción son DATO del servidor
   * (`JefeCarreraController::construirAlertas`): la vista no inventa reglas.
   */
  import { Link } from '@inertiajs/svelte';
  import {
    AlertTriangle,
    ArrowUpRight,
    Bell,
    BookOpen,
    CheckCircle2,
    Circle,
    ClipboardList,
    FilePenLine,
    UserX,
  } from 'lucide-svelte';
  import { formatFechaHora } from '@/utils/formatters';

  interface Alerta {
    id: number;
    tipo: 'critica' | 'advertencia' | 'info';
    icono?: string | null;
    titulo: string;
    count: number;
    accion_label: string;
    accion_url: string;
  }

  interface Comprobacion {
    label: string;
    hecho: number;
    total: number;
  }

  interface Props {
    alertas: Alerta[];
    /** Sólo comprobaciones calculables; una lista vacía simplemente no se dibuja. */
    comprobaciones?: Comprobacion[];
    /** Frase de confirmación, ya compuesta con los números reales del período. */
    confirmacion?: string;
    /** Instante en que el servidor consultó los datos (ISO). */
    generado_en?: string | null;
  }

  let { alertas, comprobaciones = [], confirmacion = '', generado_en = null }: Props = $props();

  const ICONOS: Record<string, typeof Bell> = {
    'alert-triangle': AlertTriangle,
    'book-open': BookOpen,
    'file-pen-line': FilePenLine,
    'user-x': UserX,
    'clipboard-list': ClipboardList,
  };

  const TONOS = {
    critica: {
      accent: 'border-l-red-600',
      iconBg: 'bg-red-50',
      iconColor: 'text-red-600',
      kicker: 'Crítica',
      kickerColor: 'text-red-700',
      pill: 'border-red-200 bg-red-50 text-red-700',
      Icon: AlertTriangle,
    },
    advertencia: {
      accent: 'border-l-amber-600',
      iconBg: 'bg-amber-50',
      iconColor: 'text-amber-700',
      kicker: 'Advertencia',
      kickerColor: 'text-amber-700',
      pill: 'border-amber-200 bg-amber-50 text-amber-700',
      Icon: BookOpen,
    },
    info: {
      accent: 'border-l-navy',
      iconBg: 'bg-uta-blue-light',
      iconColor: 'text-navy',
      kicker: 'Información',
      kickerColor: 'text-navy',
      pill: 'border-slate-300 bg-uta-blue-light text-navy',
      Icon: UserX,
    },
  } as const;

  const tono = (a: Alerta) => TONOS[a.tipo] ?? TONOS.info;
  const iconoDe = (a: Alerta) => (a.icono && ICONOS[a.icono]) || tono(a).Icon;

  /** La acción rellena es una sola: la de la primera alerta crítica. */
  const idCritica = $derived(alertas.find((a) => a.tipo === 'critica')?.id ?? null);

  const actualizado = $derived(generado_en ? formatFechaHora(generado_en) : '');

  const BTN_PRIMARY =
    'inline-flex items-center gap-1.5 self-start rounded-lg bg-navy px-3 py-1.5 text-[13px] font-semibold text-white no-underline transition-colors hover:bg-navy-2';
  const BTN_OUTLINE =
    'inline-flex items-center gap-1.5 self-start rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-medium text-ink no-underline transition-colors hover:bg-sand';
</script>

<section
  class="flex h-full flex-col gap-3.5 rounded-xl border border-gray-200 bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,.08)]"
>
  <div class="flex items-center gap-2">
    <Bell class="h-4 w-4 text-ink-2" aria-hidden="true" />
    <h3 class="m-0 text-base font-semibold text-ink">Alertas</h3>
    {#if alertas.length > 0}
      <span
        class="ml-auto inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700"
      >
        {alertas.length} activa{alertas.length === 1 ? '' : 's'}
      </span>
    {:else}
      <span
        class="ml-auto inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
      >
        Sin alertas
      </span>
    {/if}
  </div>

  {#if alertas.length > 0}
    <div class="flex flex-col gap-2.5">
      {#each alertas as alerta (alerta.id)}
        {@const t = tono(alerta)}
        {@const Icono = iconoDe(alerta)}
        <article
          class="flex flex-col gap-2.5 rounded-[10px] border border-gray-200 border-l-[3px] p-3 {t.accent}"
        >
          <div class="flex items-start gap-2.5">
            <div
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {t.iconBg}"
              aria-hidden="true"
            >
              <Icono class="h-[15px] w-[15px] {t.iconColor}" />
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-1">
              <span
                class="text-[10.5px] font-semibold uppercase tracking-[0.08em] {t.kickerColor}"
              >
                {t.kicker}
              </span>
              <span class="text-[13.5px] font-semibold leading-[1.35] text-ink">
                {alerta.titulo}
              </span>
            </div>
            <span
              class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold tabular-nums {t.pill}"
            >
              {alerta.count}
            </span>
          </div>

          <Link
            href={alerta.accion_url}
            class={alerta.id === idCritica ? BTN_PRIMARY : BTN_OUTLINE}
          >
            {alerta.accion_label}
            <ArrowUpRight class="h-3.5 w-3.5" aria-hidden="true" />
          </Link>
        </article>
      {/each}
    </div>
  {:else}
    <!-- Sin alertas: confirmación con su razón, no un hueco. -->
    <div
      class="flex flex-col items-center gap-2 rounded-[10px] border border-emerald-200 bg-teal-50 p-[18px] text-center"
    >
      <div
        class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50"
        aria-hidden="true"
      >
        <CheckCircle2 class="h-5 w-5 text-emerald-600" />
      </div>
      <span class="text-base font-semibold text-ink">Todo al día</span>
      {#if confirmacion}
        <p class="m-0 max-w-[280px] text-[13px] leading-snug text-ink-2">{confirmacion}</p>
      {/if}
    </div>

    {#if comprobaciones.length > 0}
      <div class="flex flex-col gap-1.5">
        <span class="text-[10.5px] font-semibold uppercase tracking-[0.08em] text-ink-2">
          Comprobaciones del período
        </span>
        <ul class="m-0 list-none p-0">
          {#each comprobaciones as c, i (c.label)}
            {@const completa = c.hecho >= c.total}
            <li
              class="flex items-center gap-2 py-2 {i < comprobaciones.length - 1
                ? 'border-b border-gray-200'
                : ''}"
            >
              {#if completa}
                <CheckCircle2 class="h-[15px] w-[15px] shrink-0 text-emerald-600" aria-hidden="true" />
              {:else}
                <Circle class="h-[15px] w-[15px] shrink-0 text-gray-400" aria-hidden="true" />
              {/if}
              <span class="flex-1 text-[13px] text-ink">{c.label}</span>
              <span class="font-mono text-[12px] tabular-nums text-ink-2">
                {c.hecho}/{c.total}
              </span>
            </li>
          {/each}
        </ul>
      </div>
    {/if}
  {/if}

  {#if actualizado}
    <div class="mt-auto border-t border-gray-200 pt-3 text-[12px] text-ink-2">
      Actualizado {actualizado}
    </div>
  {/if}
</section>
