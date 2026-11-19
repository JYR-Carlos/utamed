<script lang="ts">
  /**
   * Píldora compacta de una marca del calendario dentro de una celda de día.
   *
   * El relleno y el borde izquierdo llevan SIEMPRE el color del curso; la forma
   * es la que distingue la familia:
   * - Fecha límite sumativa → relleno suave + borde izquierdo macizo.
   * - Fecha límite formativa → fondo blanco + contorno punteado.
   * - Actividad oculta (visible = false) → gris punteado, rotulada OCULTA.
   * - Sesión de asistencia → fondo blanco, borde sólido y contador de presentes.
   * - Hito de syllabus → pastilla redonda verde (aprobado) o roja (rechazado).
   */
  import {
    ClipboardCheck,
    ClipboardList,
    CircleCheck,
    CircleX,
    Clock,
    EyeOff,
    PencilLine,
    Users,
  } from 'lucide-svelte';
  import type { CalendarItem, CursoAccent } from '../types';

  interface Props {
    item: CalendarItem;
    accent: CursoAccent;
    /** Código del curso, para desambiguar la sesión ("CÁT · 28/32"). */
    etiquetaCurso?: string;
    onClick?: (item: CalendarItem) => void;
  }

  let { item, accent, etiquetaCurso, onClick }: Props = $props();

  const BASE =
    'flex w-full items-center gap-1 overflow-hidden rounded-md px-[5px] py-[3px] text-left text-[11px] font-semibold leading-tight transition-[filter] hover:brightness-[.97]';
  const BASE_HITO =
    'flex w-full items-center gap-1 overflow-hidden rounded-full px-[7px] py-[2px] text-left text-[10.5px] font-semibold leading-tight transition-[filter] hover:brightness-[.97]';

  /** Clase y estilo inline según familia, tipo y visibilidad. */
  const marca = $derived.by(() => {
    if (item.familia === 'ENTREGA') {
      const e = item.entrega;

      if (!e.visible) {
        return {
          clase: BASE,
          estilo: `background:var(--color-inst-off-s);border:1px dashed var(--color-inst-control);border-left:3px solid ${accent.base};color:var(--color-inst-ink-2)`,
          texto: `OCULTA · ${e.titulo}`,
          titulo: `${e.titulo} — no visible para el estudiante`,
        };
      }

      if (e.tipo_actividad === 'SUMATIVA') {
        return {
          clase: BASE,
          estilo: `background:${accent.soft};border-left:3px solid ${accent.base};color:${accent.text}`,
          texto: e.titulo,
          titulo: `${e.titulo} — sumativa`,
        };
      }

      return {
        clase: BASE,
        estilo: `background:var(--color-inst-tarjeta);border:1px dashed ${accent.base};border-left:3px solid ${accent.base};color:${accent.text}`,
        texto: e.titulo,
        titulo: `${e.titulo} — formativa`,
      };
    }

    if (item.familia === 'SESION') {
      const s = item.sesion;
      const prefijo = etiquetaCurso ? `${etiquetaCurso} · ` : '';
      const detalleAsunto = s.asunto ? `${s.asunto} · ` : '';
      return {
        clase: BASE,
        estilo: `background:var(--color-inst-tarjeta);border:1px solid var(--color-inst-linea);border-left:3px solid ${accent.base};color:var(--color-inst-ink)`,
        texto: `${prefijo}${s.presentes}/${s.total}`,
        titulo: `${detalleAsunto}${s.componente} · ${s.hora_inicio}–${s.hora_fin} · ${s.presentes} de ${s.total} presentes`,
      };
    }

    const h = item.hito;

    if (h.tipo === 'RECHAZO') {
      return {
        clase: BASE_HITO,
        estilo: 'background:var(--color-inst-error-s);border:1px solid var(--color-inst-error);color:var(--color-inst-error)',
        texto: h.titulo,
        titulo: h.detalle ? `Razón: ${h.detalle}` : h.titulo,
      };
    }

    if (h.tipo === 'APROBACION') {
      return {
        clase: BASE_HITO,
        estilo: 'background:var(--color-inst-ok-s);border:1px solid var(--color-inst-ok);color:var(--color-inst-ok)',
        texto: h.titulo,
        titulo: h.titulo,
      };
    }

    return {
      clase: BASE_HITO,
      estilo: 'background:var(--color-inst-sutil);border:1px solid var(--color-inst-linea);color:var(--color-inst-ink-2)',
      texto: h.titulo,
      titulo: h.titulo,
    };
  });
</script>

<button
  type="button"
  onclick={(e) => {
    e.stopPropagation();
    onClick?.(item);
  }}
  class={marca.clase}
  style={marca.estilo}
  title={marca.titulo}
>
  {#if item.familia === 'ENTREGA'}
    {#if !item.entrega.visible}
      <EyeOff size={11} class="text-inst-ink-3 shrink-0" />
    {:else if item.entrega.tipo_actividad === 'SUMATIVA'}
      <ClipboardCheck size={11} class="shrink-0" style="color:{accent.base}" />
    {:else}
      <PencilLine size={11} class="shrink-0" style="color:{accent.base}" />
    {/if}
  {:else if item.familia === 'SESION'}
    <Clock size={11} class="text-inst-ink-2 shrink-0" />
  {:else if item.hito.tipo === 'RECHAZO'}
    <CircleX size={11} class="text-inst-error shrink-0" />
  {:else if item.hito.tipo === 'APROBACION'}
    <CircleCheck size={11} class="text-inst-ok shrink-0" />
  {:else}
    <ClipboardList size={11} class="text-inst-ink-2 shrink-0" />
  {/if}

  <span class="min-w-0 flex-1 truncate">{marca.texto}</span>

  {#if item.familia === 'ENTREGA' && item.entrega.es_grupal}
    <Users size={11} class="shrink-0 opacity-70" />
  {/if}
</button>
