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

  interface Props {
    item: CalendarItem;
    curso?: CalendarCurso;
    accent: CursoAccent;
  }

  let { item, curso, accent }: Props = $props();

  /** "MED-2101 · A" o el nombre de la asignatura si no hay código. */
  const nombreCurso = $derived.by(() => {
    if (!curso) return 'Curso';
    const base = curso.cod_curso ? `${curso.cod_curso} · ${curso.asignatura}` : curso.asignatura;
    return curso.letra_grupo ? `${base} · ${curso.letra_grupo}` : base;
  });

  /** Contorno de la ficha según familia, tipo y visibilidad. */
  const contorno = $derived.by(() => {
    if (item.familia === 'ENTREGA') {
      if (!item.entrega.visible) {
        return `background:#F4F4F5;border:1px dashed #A1A1AA;border-left:3px solid ${accent.base}`;
      }
      if (item.entrega.tipo_actividad === 'FORMATIVA') {
        return `background:#FFFFFF;border:1px dashed ${accent.base};border-left:3px solid ${accent.base}`;
      }
      return `background:#FFFFFF;border:1px solid #E5E7EB;border-left:3px solid ${accent.base}`;
    }

    if (item.familia === 'SESION') {
      return `background:#FFFFFF;border:1px solid #E5E7EB;border-left:3px solid ${accent.base}`;
    }

    if (item.hito.tipo === 'RECHAZO') {
      return 'background:#FFFFFF;border:1px solid #FECACA';
    }
    if (item.hito.tipo === 'APROBACION') {
      return 'background:#FFFFFF;border:1px solid #BBE0CB';
    }
    return 'background:#FFFFFF;border:1px solid #E5E7EB';
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
        ? `Clase · ${item.sesion.hora_inicio} – ${item.sesion.hora_fin}`
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
        <EyeOff size={15} color="#71717A" />
      {:else if item.entrega.tipo_actividad === 'SUMATIVA'}
        <ClipboardCheck size={15} color={accent.base} />
      {:else}
        <PencilLine size={15} color={accent.base} />
      {/if}
    {:else if item.familia === 'SESION'}
      <Clock size={15} color="#5A5E6E" />
    {:else if item.hito.tipo === 'RECHAZO'}
      <CircleX size={15} color="#B91C1C" />
    {:else if item.hito.tipo === 'APROBACION'}
      <CircleCheck size={15} color="#1F6F45" />
    {:else}
      <ClipboardList size={15} color="#5A5E6E" />
    {/if}
  </span>

  <div class="flex min-w-0 flex-1 flex-col gap-px">
    <span class="flex items-center gap-1.5">
      <span
        class="truncate text-[13.5px] font-semibold leading-[1.25]"
        style="color:{item.familia === 'ENTREGA' && !item.entrega.visible ? '#3F3F46' : '#1A1A24'}"
      >
        {titulo}
      </span>
      {#if item.familia === 'ENTREGA' && !item.entrega.visible}
        <span
          class="shrink-0 rounded-full bg-[#E4E4E7] px-1.5 text-[9.5px] font-bold tracking-[0.06em] text-[#52525B]"
        >
          OCULTA
        </span>
      {/if}
      {#if item.familia === 'ENTREGA' && item.entrega.es_grupal}
        <Users size={12} class="shrink-0" color="#5A5E6E" />
      {/if}
    </span>

    <span class="truncate text-[11.5px] text-[#5A5E6E]">{meta}</span>

    {#if item.familia === 'SESION'}
      <span class="text-[11.5px] font-semibold text-[#1F6F45]">
        {item.sesion.presentes} de {item.sesion.total} presentes
      </span>
    {/if}

    {#if item.familia === 'ENTREGA' && item.entrega.grupos_total > 0}
      <span class="text-[11.5px] text-[#5A5E6E]">
        {item.entrega.grupos_sin_nota} de {item.entrega.grupos_total}
        {item.entrega.grupos_total === 1 ? 'grupo' : 'grupos'} sin nota
      </span>
    {/if}

    {#if item.familia === 'HITO' && item.hito.detalle}
      <span class="text-[11.5px] leading-[1.4] text-[#7F1D1D]">{item.hito.detalle}</span>
    {/if}
  </div>

  <Link
    href={salida.href}
    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#D6D9E0] bg-white px-2.5 py-2 text-[11.5px] font-semibold text-[#1A1A24] no-underline transition-colors hover:bg-[#F5F1EA]"
  >
    {salida.texto}
    <ArrowUpRight size={12} color="#5A5E6E" />
  </Link>
</div>
