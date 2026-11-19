<script lang="ts">
  /**
   * Ficha ancha de una marca del calendario, para la vista agenda y el detalle
   * del día. Repite el código de color del curso en el borde izquierdo y lleva
   * la salida al lugar donde el docente actúa (la vista es de solo lectura:
   * desde aquí sólo se navega).
   */
  import { Link } from '@inertiajs/svelte';
  import {
    ArrowUpRight,
    ClipboardCheck,
    ClipboardList,
    CircleCheck,
    CircleX,
    Clock,
    EyeOff,
    PencilLine,
    Users,
  } from 'lucide-svelte';
  import type { CalendarCurso, CalendarItem, CursoAccent } from '../types';
  import { codigoCurso } from '../utils/calendar';

  interface Props {
    item: CalendarItem;
    curso?: CalendarCurso;
    accent: CursoAccent;
  }

  let { item, curso, accent }: Props = $props();

  /** «DM069 · A · Taller de Multimedia II». */
  const nombreCurso = $derived(curso ? `${codigoCurso(curso)} · ${curso.asignatura}` : 'Curso');

  /** Contorno de la ficha según familia, tipo y visibilidad. */
  const contorno = $derived.by(() => {
    if (item.familia === 'ENTREGA') {
      if (!item.entrega.visible) {
        return `background:var(--color-inst-off-s);border:1px dashed var(--color-inst-control);border-left:3px solid ${accent.base}`;
      }
      if (item.entrega.tipo_actividad === 'FORMATIVA') {
        return `background:var(--color-inst-tarjeta);border:1px dashed ${accent.base};border-left:3px solid ${accent.base}`;
      }
      return `background:var(--color-inst-tarjeta);border:1px solid var(--color-inst-linea);border-left:3px solid ${accent.base}`;
    }

    if (item.familia === 'SESION') {
      return `background:var(--color-inst-tarjeta);border:1px solid var(--color-inst-linea);border-left:3px solid ${accent.base}`;
    }

    if (item.hito.tipo === 'RECHAZO') {
      return 'background:var(--color-inst-tarjeta);border:1px solid var(--color-inst-error)';
    }
    if (item.hito.tipo === 'APROBACION') {
      return 'background:var(--color-inst-tarjeta);border:1px solid var(--color-inst-ok)';
    }
    return 'background:var(--color-inst-tarjeta);border:1px solid var(--color-inst-linea)';
  });

  /** Segunda línea: de qué curso es y qué es exactamente. */
  const meta = $derived.by(() => {
    if (item.familia === 'ENTREGA') {
      const e = item.entrega;
      const partes = [
        nombreCurso,
        e.tipo_actividad === 'SUMATIVA' ? 'sumativa' : 'formativa',
        e.componente,
      ];
      if (!e.visible) partes.push('no visible al alumno');
      return partes.join(' · ');
    }

    if (item.familia === 'SESION') {
      return `${nombreCurso} · ${item.sesion.componente}`;
    }

    return nombreCurso;
  });

  /** Título de la ficha. */
  const titulo = $derived(
    item.familia === 'ENTREGA'
      ? item.entrega.titulo
      : item.familia === 'SESION'
        ? (item.sesion.asunto
            ? `${item.sesion.asunto} · ${item.sesion.hora_inicio} – ${item.sesion.hora_fin}`
            : `Clase · ${item.sesion.hora_inicio} – ${item.sesion.hora_fin}`)
        : item.hito.titulo,
  );

  /** Destino y rótulo del enlace de salida. */
  const salida = $derived.by(() => {
    if (item.familia === 'ENTREGA') {
      return {
        href: `/docente/cursos/${item.id_curso}/actividades/${item.entrega.id_actividad}/evaluacion`,
        texto: item.entrega.tipo_actividad === 'SUMATIVA' ? 'Evaluar' : 'Ver',
      };
    }
    if (item.familia === 'SESION') {
      return { href: `/docente/cursos/${item.id_curso}?tab=asistencia`, texto: 'Asistencia' };
    }
    return { href: `/docente/cursos/${item.id_curso}`, texto: 'Ver curso' };
  });
</script>

<div class="flex items-center gap-2.5 rounded-[10px] p-2.5" style={contorno}>
  <span class="flex shrink-0 items-center">
    {#if item.familia === 'ENTREGA'}
      {#if !item.entrega.visible}
        <EyeOff size={15} class="text-inst-ink-3" />
      {:else if item.entrega.tipo_actividad === 'SUMATIVA'}
        <ClipboardCheck size={15} style="color:{accent.base}" />
      {:else}
        <PencilLine size={15} style="color:{accent.base}" />
      {/if}
    {:else if item.familia === 'SESION'}
      <Clock size={15} class="text-inst-ink-2" />
    {:else if item.hito.tipo === 'RECHAZO'}
      <CircleX size={15} class="text-inst-error" />
    {:else if item.hito.tipo === 'APROBACION'}
      <CircleCheck size={15} class="text-inst-ok" />
    {:else}
      <ClipboardList size={15} class="text-inst-ink-2" />
    {/if}
  </span>

  <div class="flex min-w-0 flex-1 flex-col gap-px">
    <span class="flex items-center gap-1.5">
      <span
        class="truncate text-[13.5px] font-semibold leading-[1.25]"
        style="color:{item.familia === 'ENTREGA' && !item.entrega.visible ? 'var(--color-inst-ink-2)' : 'var(--color-inst-ink)'}"
      >
        {titulo}
      </span>
      {#if item.familia === 'ENTREGA' && !item.entrega.visible}
        <span
          class="shrink-0 rounded-full bg-inst-linea px-1.5 text-[9.5px] font-bold tracking-[0.06em] text-inst-ink-2"
        >
          OCULTA
        </span>
      {/if}
      {#if item.familia === 'ENTREGA' && item.entrega.es_grupal}
        <Users size={12} class="text-inst-ink-2 shrink-0" />
      {/if}
    </span>

    <span class="truncate text-[11.5px] text-inst-ink-2">{meta}</span>

    {#if item.familia === 'SESION'}
      <span class="text-[11.5px] font-semibold text-inst-ok">
        {item.sesion.presentes} de {item.sesion.total} presentes
      </span>
    {/if}

    {#if item.familia === 'ENTREGA' && item.entrega.grupos_total > 0}
      <span class="text-[11.5px] text-inst-ink-2">
        {item.entrega.grupos_sin_nota} de {item.entrega.grupos_total}
        {item.entrega.grupos_total === 1 ? 'grupo' : 'grupos'} sin nota
      </span>
    {/if}

    {#if item.familia === 'HITO' && item.hito.detalle}
      <span class="text-[11.5px] leading-[1.4] text-inst-error">{item.hito.detalle}</span>
    {/if}
  </div>

  <Link
    href={salida.href}
    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-inst-linea bg-inst-tarjeta px-2.5 py-2 text-[11.5px] font-semibold text-inst-ink no-underline transition-colors hover:bg-inst-sutil"
  >
    {salida.texto}
    <ArrowUpRight size={12} class="text-inst-ink-2" />
  </Link>
</div>
