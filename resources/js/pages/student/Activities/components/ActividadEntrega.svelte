<script lang="ts">
  /**
   * Entrega de la actividad: el plazo, el archivo vigente y lo que se puede
   * hacer con él. El estudiante ve siempre la fecha límite original; la
   * holgura (general o personal) es del docente y no se nombra (decisión
   * 07-10). Con holgura vigente el backend sigue diciendo ACTIVA y la píldora
   * dice «Atrasada · aún se recibe».
   */
  import { Link } from '@inertiajs/svelte';
  import { CircleAlert, CircleCheck, Clock, Download, Eye, FileText, Lock, RefreshCw, Trash2, Upload, Users } from 'lucide-svelte';
  import { btnPrimario, btnSecundario, pildora, tarjeta, tono } from '@/lib/dm';
  import { claveDia, formatBytes, formatDiaCorto } from '@/utils/formatters';
  import { textoCierre } from '@/utils/plazo';

  interface Props {
    fechaLimite: string;
    estado?: string | null;
    esGrupal: boolean;
    sinGrupo: boolean;
    yaEvaluada: boolean;
    puedeSubir: boolean;
    entrega?: {
      fecha_emision: string;
      archivo?: {
        nombre_original: string | null;
        peso_bytes: number | null;
        mime_type?: string | null;
        visualizable?: boolean;
      } | null;
    } | null;
    urlDescarga?: string | null;
    /** Mensajería del curso, para el «avísale» de quien no tiene grupo. */
    hrefMensajeria?: string | null;
    onSubir: () => void;
    onBorrar: () => void;
  }

  let {
    fechaLimite,
    estado,
    esGrupal,
    sinGrupo,
    yaEvaluada,
    puedeSubir,
    entrega = null,
    urlDescarga = null,
    hrefMensajeria = null,
    onSubir,
    onBorrar,
  }: Props = $props();

  const hoy = claveDia();
  const dia = $derived(fechaLimite?.slice(0, 10) ?? '');
  const cierre = $derived(dia ? textoCierre(dia) : null);

  // La píldora del plazo. Calificada gana: ya no hay nada que reemplazar.
  const plazo = $derived.by((): { texto: string; corto?: string; tono: keyof typeof tono; icono: typeof Clock } => {
    if (estado === 'CERRADA') {
      return { texto: dia ? `Plazo cerrado el ${formatDiaCorto(dia)}` : 'Plazo cerrado', tono: 'off', icono: Lock };
    }
    if (yaEvaluada) return { texto: 'Calificada · ya no se reemplaza', tono: 'off', icono: Lock };
    if (!dia) return { texto: 'Sin fecha límite', tono: 'off', icono: Clock };
    if (dia < hoy) return { texto: 'Atrasada · aún se recibe', tono: 'warn', icono: Clock };
    if (dia === hoy) {
      return { texto: 'Vence hoy a las 23:59 (hora de Chile)', corto: 'Vence hoy a las 23:59', tono: 'warn', icono: Clock };
    }
    return { texto: `Vence el ${formatDiaCorto(dia)} a las 23:59 (hora de Chile)`, corto: `Vence el ${formatDiaCorto(dia)} a las 23:59`, tono: 'off', icono: Clock };
  });

  const archivo = $derived(entrega?.archivo ?? null);
  const extension = $derived(archivo?.nombre_original?.split('.').pop()?.toUpperCase() ?? '');

  // «PDF · 8,4 MB · entregado el dom 11 oct a las 22:47».
  const detalleArchivo = $derived.by(() => {
    if (!entrega) return '';
    const d = new Date(entrega.fecha_emision);
    const hora = d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    return [extension, archivo?.peso_bytes ? formatBytes(archivo.peso_bytes) : '', `entregado el ${formatDiaCorto(d)} a las ${hora}`]
      .filter(Boolean)
      .join(' · ');
  });

  const visualizable = $derived(
    archivo?.visualizable ??
      (archivo?.mime_type === 'application/pdf' || (archivo?.mime_type ?? '').startsWith('image/')),
  );

  const quien = $derived(esGrupal ? 'tu grupo' : 'tú');
</script>

<section aria-labelledby="t-entrega" class="{tarjeta} overflow-hidden">
  <div class="flex flex-col items-start gap-3 px-4 pt-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:border-b lg:border-dm-linea lg:px-6 lg:py-4">
    <h2 id="t-entrega" class="text-lg leading-6 font-semibold">{esGrupal ? 'Entrega del grupo' : 'Tu entrega'}</h2>
    {#if !sinGrupo}
      {@const Icono = plazo.icono}
      <span class="{pildora} {tono[plazo.tono]}">
        <Icono size={14} aria-hidden="true" />
        {#if plazo.corto}<span class="lg:hidden">{plazo.corto}</span><span class="max-lg:hidden">{plazo.texto}</span
          >{:else}{plazo.texto}{/if}
      </span>
    {/if}
  </div>

  <div class="flex flex-col gap-3.5 p-4 lg:gap-4 lg:p-6">
    {#if sinGrupo}
      <div class="flex items-start gap-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-dm-sutil text-dm-ink-3">
          <Users size={22} aria-hidden="true" />
        </span>
        <div class="flex flex-col gap-1">
          <span class="text-base leading-[22px] font-semibold">
            {esGrupal ? 'Aún no tienes grupo en esta actividad' : 'Tu registro en esta actividad aún no está listo'}
          </span>
          <span class="text-sm leading-5 text-dm-ink-2">
            {esGrupal
              ? 'Cuando tu docente te asigne a un grupo podrás entregar y escribir en la agenda. Si crees que es un error, avísale.'
              : 'Avisa a tu docente para que lo revise: sin él no puedes entregar ni escribir en la agenda.'}
          </span>
          {#if hrefMensajeria}
            <Link href={hrefMensajeria} class="{btnSecundario} mt-2 self-start">Escribir al equipo docente</Link>
          {/if}
        </div>
      </div>
    {:else if entrega}
      <div class="flex min-h-14 items-center gap-3 rounded-[10px] border border-dm-linea px-3 py-2">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-(--curso-s) text-(--curso-t)">
          <FileText size={20} aria-hidden="true" />
        </span>
        <span class="flex min-w-0 flex-1 flex-col">
          <span class="truncate text-[15px] font-semibold">{archivo?.nombre_original ?? 'Archivo entregado'}</span>
          <span class="text-[13px] leading-[18px] text-dm-ink-3">{detalleArchivo}</span>
        </span>
        {#if urlDescarga}
          <a
            href={visualizable ? `${urlDescarga}?ver=1` : urlDescarga}
            target={visualizable ? '_blank' : undefined}
            rel={visualizable ? 'noopener noreferrer' : undefined}
            class="inline-flex min-h-11 shrink-0 items-center gap-1.5 px-1 text-sm font-semibold text-dm-enlace no-underline hover:text-dm-enlace-hover hover:underline"
          >
            {#if visualizable}
              <Eye size={16} aria-hidden="true" /><span class="max-sm:sr-only">Ver</span><span class="sr-only">{' '}la entrega (se abre en otra pestaña)</span>
            {:else}
              <Download size={16} aria-hidden="true" /><span class="max-sm:sr-only">Descargar</span><span class="sr-only">{' '}la entrega</span>
            {/if}
          </a>
        {/if}
      </div>

      {#if puedeSubir}
        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
          <span class="flex items-center gap-2 text-sm leading-5 text-dm-ink-2">
            <CircleCheck size={16} aria-hidden="true" class="shrink-0 text-dm-ok" />
            Entregada. Puedes reemplazarla mientras el plazo siga abierto.
          </span>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="{btnSecundario} max-lg:flex-1" onclick={onSubir}>
              <RefreshCw size={16} aria-hidden="true" />Reemplazar entrega
            </button>
            <button
              type="button"
              class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-[10px] px-3 text-sm font-semibold text-dm-error hover:bg-dm-error-s"
              onclick={onBorrar}
            >
              <Trash2 size={16} aria-hidden="true" />Eliminar
            </button>
          </div>
        </div>
      {/if}
    {:else if puedeSubir}
      <div class="flex flex-col gap-3.5 lg:flex-row lg:flex-wrap lg:items-center lg:gap-6">
        <div class="flex flex-col gap-1 lg:flex-[1_1_300px] lg:flex-row lg:items-start lg:gap-4">
          <span class="hidden size-12 shrink-0 items-center justify-center rounded-full bg-dm-sutil text-dm-ink-3 lg:flex">
            <Upload size={22} aria-hidden="true" />
          </span>
          <div class="flex flex-col gap-1">
            <span class="text-base leading-[22px] font-semibold">
              {esGrupal ? 'Tu grupo todavía no entrega' : 'Todavía no entregas'}
            </span>
            <span class="text-sm leading-5 text-dm-ink-2">
              Sube un PDF o un archivo comprimido.
              {esGrupal ? 'Cualquier integrante puede hacerlo, y una' : 'Una'} entrega nueva reemplaza a la anterior mientras el plazo siga abierto.
            </span>
          </div>
        </div>
        <button type="button" class="{btnPrimario} min-h-12 px-5 text-base lg:min-h-11 lg:text-[15px]" onclick={onSubir}>
          <Upload size={18} aria-hidden="true" />Subir entrega
        </button>
      </div>
    {:else}
      <div class="flex items-start gap-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-dm-sutil text-dm-ink-3">
          <CircleAlert size={22} aria-hidden="true" />
        </span>
        <div class="flex flex-col gap-1">
          <span class="text-base leading-[22px] font-semibold">Sin entrega registrada</span>
          <span class="text-sm leading-5 text-dm-ink-2">
            {estado === 'CERRADA'
              ? `El plazo cerró sin que ${quien === 'tú' ? 'entregaras' : 'tu grupo entregara'}. Si crees que es un error, escríbele a tu docente en la agenda.`
              : 'Esta actividad ya no recibe entregas.'}
          </span>
        </div>
      </div>
    {/if}

    {#if cierre?.local && !sinGrupo && estado !== 'CERRADA' && !yaEvaluada}
      <span class="text-[13px] leading-[18px] text-dm-ink-3">En tu hora local cierra el {cierre.local}.</span>
    {/if}
  </div>
</section>
