<script lang="ts">
  /**
   * Tarjeta de una ayudantía con el estado real de su syllabus. El estado
   * manda sobre la acción: NO_INICIADO, BORRADOR, RECHAZADO y EN_REVISION
   * llevan el botón relleno "Editar programa" (el ayudante puede seguir
   * escribiendo); sólo APROBADO pasa a un botón de sólo lectura — no existe
   * un "Editar" deshabilitado (ver docs_ui_mockups/rol_ayudante.md).
   */
  import { Link } from '@inertiajs/svelte';
  import { PencilLine, FileText } from 'lucide-svelte';

  interface Detalle {
    fecha: string | null;
    por: string | null;
  }

  interface Rechazo {
    razon: string;
    por: string | null;
    fecha: string | null;
  }

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    agno_real?: number | null;
    semestre_real?: number | null;
    estado_syllabus: 'NO_INICIADO' | 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION' | 'APROBADO';
    detalle?: Detalle | null;
    rechazo?: Rechazo | null;
  }

  let {
    id_curso,
    nombre,
    cod_curso,
    letra_grupo,
    agno_real = null,
    semestre_real = null,
    estado_syllabus,
    detalle = null,
    rechazo = null,
  }: Props = $props();

  // Mismo formato de período que el resto del rediseño (DelegacionPermisosCurso,
  // docente/Dashboard). Se omite si el curso no trae el par completo.
  const periodo = $derived(semestre_real && agno_real ? `${semestre_real}º semestre ${agno_real}` : null);

  const badge = $derived(
    {
      NO_INICIADO: { bg: 'bg-[#F1F5F9]', border: 'border-[#E2E8F0]', color: 'text-[#475569]', dot: 'bg-[#64748B]', label: 'Sin programa aún' },
      BORRADOR: { bg: 'bg-[#FFFBEB]', border: 'border-[#FDE68A]', color: 'text-[#B45309]', dot: 'bg-[#D97706]', label: 'Borrador' },
      RECHAZADO: { bg: 'bg-[#FEF2F2]', border: 'border-[#FECACA]', color: 'text-[#B91C1C]', dot: 'bg-[#DC2626]', label: 'Rechazado' },
      EN_REVISION: { bg: 'bg-[#E8EDF5]', border: 'border-[#C9D6E6]', color: 'text-[#002F6C]', dot: 'bg-[#002F6C]', label: 'En revisión' },
      APROBADO: { bg: 'bg-[#ECFDF5]', border: 'border-[#A7F3D0]', color: 'text-[#047857]', dot: 'bg-[#059669]', label: 'Aprobado' },
    }[estado_syllabus],
  );

  const descripcion = $derived.by(() => {
    if (estado_syllabus === 'NO_INICIADO') {
      return 'Nadie ha empezado la redacción de este curso.';
    }
    if (estado_syllabus === 'RECHAZADO' && rechazo) {
      const quien = rechazo.por ? ` — ${rechazo.por}${rechazo.fecha ? `, ${rechazo.fecha}` : ''}` : '';
      return `"${rechazo.razon}"${quien}`;
    }
    if (estado_syllabus === 'EN_REVISION') {
      const fecha = detalle?.fecha ? `Enviado ${detalle.fecha} · ` : '';
      return `${fecha}en manos del docente titular`;
    }
    if (estado_syllabus === 'APROBADO') {
      return detalle?.fecha ? `Aprobado ${detalle.fecha} · versión cerrada` : 'Versión cerrada';
    }
    // BORRADOR
    if (!detalle?.fecha) return 'Aún no se ha enviado a revisión.';
    return detalle.por ? `Última edición ${detalle.fecha} · por ${detalle.por}` : `Última edición ${detalle.fecha}`;
  });

  const accion = $derived(
    estado_syllabus === 'APROBADO'
      ? { label: 'Ver programa', href: `/ayudante/cursos/${id_curso}/programa`, Icon: FileText, style: 'outline' as const }
      : {
          label: 'Editar programa',
          href:
            estado_syllabus === 'NO_INICIADO'
              ? `/ayudante/cursos/${id_curso}/programa/create`
              : `/ayudante/cursos/${id_curso}/programa/editar`,
          Icon: PencilLine,
          style: 'filled' as const,
        },
  );
</script>

<article class="flex flex-col gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-[18px] shadow-[0_1px_3px_rgba(0,0,0,.08)]">
  <div class="flex items-start gap-2.5">
    <div class="flex min-w-0 flex-col gap-[3px]">
      <span class="font-mono text-[11.5px] tracking-wide text-[#5A5E6E]">{cod_curso}</span>
      <span class="text-[16px] font-semibold leading-[1.3] text-[#1A1A24]">{nombre}</span>
      {#if periodo}
        <span class="text-[12.5px] text-[#5A5E6E]">{periodo}</span>
      {/if}
    </div>
    {#if letra_grupo}
      <div class="ml-auto flex shrink-0 flex-col items-center gap-0.5">
        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-[#E8EDF5] text-[15px] font-bold text-[#002F6C]">
          {letra_grupo}
        </span>
        <span class="text-[10px] uppercase tracking-wide text-[#5A5E6E]">Grupo</span>
      </div>
    {/if}
  </div>

  <div class="flex flex-col gap-1.5 border-t border-[#E5E7EB] pt-3">
    <div class="flex items-center gap-2">
      <span class="text-[11px] uppercase tracking-wide text-[#5A5E6E]">Syllabus</span>
      <span class="inline-flex items-center gap-[5px] rounded-full {badge.bg} border {badge.border} {badge.color} px-[9px] py-[2px] text-[11.5px] font-semibold">
        <span class="h-[6px] w-[6px] rounded-full {badge.dot}"></span>
        {badge.label}
      </span>
    </div>
    <span class="text-[12.5px] leading-snug text-[#5A5E6E]">{descripcion}</span>
  </div>

  <Link
    href={accion.href}
    class="mt-auto flex w-fit items-center gap-[7px] rounded-lg px-3.5 py-2 text-[13.5px] font-semibold no-underline transition-colors {accion.style === 'filled'
      ? 'bg-[#002F6C] text-white hover:bg-[#00214d]'
      : 'border border-[#D6D9E0] bg-white font-medium text-[#1A1A24] hover:bg-[#F8FAFC]'}"
  >
    <accion.Icon class="h-[15px] w-[15px] {accion.style === 'filled' ? 'text-white' : 'text-[#5A5E6E]'}" />
    {accion.label}
  </Link>
</article>
