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
      NO_INICIADO: { bg: 'bg-slate-100', border: 'border-slate-200', color: 'text-slate-600', dot: 'bg-slate-500', label: 'Sin programa aún' },
      BORRADOR: { bg: 'bg-amber-50', border: 'border-amber-200', color: 'text-amber-700', dot: 'bg-amber-600', label: 'Borrador' },
      RECHAZADO: { bg: 'bg-red-50', border: 'border-red-200', color: 'text-red-700', dot: 'bg-red-600', label: 'Rechazado' },
      EN_REVISION: { bg: 'bg-uta-blue-light', border: 'border-slate-300', color: 'text-navy', dot: 'bg-navy', label: 'En revisión' },
      APROBADO: { bg: 'bg-emerald-50', border: 'border-emerald-200', color: 'text-emerald-700', dot: 'bg-emerald-600', label: 'Aprobado' },
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

<article class="flex flex-col gap-3.5 rounded-xl border border-gray-200 bg-white p-[18px] shadow-[0_1px_3px_rgba(0,0,0,.08)]">
  <div class="flex items-start gap-2.5">
    <div class="flex min-w-0 flex-col gap-[3px]">
      <span class="font-mono text-[11.5px] tracking-wide text-ink-2">{cod_curso}</span>
      <span class="text-[16px] font-semibold leading-[1.3] text-ink">{nombre}</span>
      {#if periodo}
        <span class="text-[12.5px] text-ink-2">{periodo}</span>
      {/if}
    </div>
    {#if letra_grupo}
      <div class="ml-auto flex shrink-0 flex-col items-center gap-0.5">
        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-uta-blue-light text-[15px] font-bold text-navy">
          {letra_grupo}
        </span>
        <span class="text-[10px] uppercase tracking-wide text-ink-2">Grupo</span>
      </div>
    {/if}
  </div>

  <div class="flex flex-col gap-1.5 border-t border-gray-200 pt-3">
    <div class="flex items-center gap-2">
      <span class="text-[11px] uppercase tracking-wide text-ink-2">Syllabus</span>
      <span class="inline-flex items-center gap-[5px] rounded-full {badge.bg} border {badge.border} {badge.color} px-[9px] py-[2px] text-[11.5px] font-semibold">
        <span class="h-[6px] w-[6px] rounded-full {badge.dot}"></span>
        {badge.label}
      </span>
    </div>
    <span class="text-[12.5px] leading-snug text-ink-2">{descripcion}</span>
  </div>

  <Link
    href={accion.href}
    class="mt-auto flex w-fit items-center gap-[7px] rounded-lg px-3.5 py-2 text-[13.5px] font-semibold no-underline transition-colors {accion.style === 'filled'
      ? 'bg-navy text-white hover:bg-navy-hover'
      : 'border border-line bg-white font-medium text-ink hover:bg-slate-50'}"
  >
    <accion.Icon class="h-[15px] w-[15px] {accion.style === 'filled' ? 'text-white' : 'text-ink-2'}" />
    {accion.label}
  </Link>
</article>
