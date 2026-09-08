<script lang="ts">
  /**
   * Dashboard del ayudante — /ayudante/dashboard.
   *
   * Importado desde claude.ai/design (proyecto UTAMED,
   * templates/ayudante-dashboard/AyudanteDashboard.dc.html): saludo con
   * carrera, tarjetas de ayudantía con estado real de syllabus y su acción,
   * mensajes sin leer por curso, salto al área de estudiante y estado vacío.
   * El chrome del mockup (sidebar/header aislados) no se porta — lo cubren
   * AyudanteLayout + RoleSidebar reales, igual que en docente/Dashboard.svelte.
   *
   * El "cuarto año" del subtítulo de la lámina no se implementa: usuario.estudiante
   * sólo guarda agno_ingreso, y deducir el año de carrera a partir de él sería
   * falso para quien repitió o congeló. El subtítulo se queda con la carrera.
   */
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { BookOpen, Calendar } from 'lucide-svelte';
  import { formatFechaCorta } from '@/utils/formatters';
  import AyudantiaCard from '@/components/ayudante/AyudantiaCard.svelte';
  import MensajesSinLeerCard from '@/components/ayudante/MensajesSinLeerCard.svelte';
  import TambienEresEstudianteCard from '@/components/ayudante/TambienEresEstudianteCard.svelte';

  interface CursoAyudantia {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    agno_real?: number | null;
    semestre_real?: number | null;
    estado_syllabus: 'NO_INICIADO' | 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION' | 'APROBADO';
    detalle?: { fecha: string | null; por: string | null } | null;
    rechazo?: { razon: string; por: string | null; fecha: string | null } | null;
  }

  interface Props {
    stats: { nombre_completo: string; total_cursos: number };
    periodo?: { ano: number; sem: number; fecha_inicio: string | null } | null;
    cursos: CursoAyudantia[];
    mensajeria: {
      no_leidos: number;
      cursos: Array<{
        id_curso: number;
        nombre: string;
        cod_curso: string;
        letra_grupo?: string | null;
        no_leidos: number;
        ultimo_no_leido?: string | null;
      }>;
    };
    esEstudiante: boolean;
    carreraEstudiante?: string | null;
    /** Alta más antigua vigente del rol Ayudante; sólo la usa el estado vacío. */
    nombramiento?: string | null;
  }

  let {
    stats,
    periodo = null,
    cursos,
    mensajeria,
    esEstudiante,
    carreraEstudiante = null,
    nombramiento = null,
  }: Props = $props();

  const fechaNombramiento = $derived(nombramiento ? formatFechaCorta(nombramiento) : '');

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/ayudante/dashboard' }];

  const primerNombre = $derived(stats.nombre_completo.split(' ')[0] || stats.nombre_completo);

  const subtitulo = $derived.by(() => {
    const carrera = carreraEstudiante ? `${carreraEstudiante} — ` : '';
    if (stats.total_cursos === 0) {
      return carreraEstudiante ? `${carreraEstudiante} — Ayudantía sin cursos asignados` : 'Ayudantía sin cursos asignados';
    }
    return `${carrera}Ayudante en ${stats.total_cursos} ${stats.total_cursos === 1 ? 'curso' : 'cursos'}`;
  });
</script>

<svelte:head>
  <title>Dashboard | UTAMED</title>
</svelte:head>

<AyudanteLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-[1400px] flex-col gap-5 p-4 md:p-6 2xl:p-8">
    <header class="flex items-start justify-between gap-6">
      <div class="flex min-w-0 flex-col gap-1">
        <h1 class="text-2xl font-semibold tracking-tight text-[#1A1A24] md:text-[28px]">Hola, {primerNombre}</h1>
        <span class="text-sm text-[#5A5E6E]">{subtitulo}</span>
      </div>
      {#if periodo}
        <div class="flex shrink-0 items-center gap-2 rounded-full border border-[#E5E7EB] bg-white px-3 py-[6px]">
          <Calendar class="h-3.5 w-3.5 text-[#5A5E6E]" />
          <span class="text-[12.5px] text-[#5A5E6E]">{periodo.sem}º semestre {periodo.ano}</span>
        </div>
      {/if}
    </header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-start">
      <div class="flex flex-col gap-3.5 lg:col-span-8">
        <div class="flex items-center gap-2">
          <BookOpen class="h-4 w-4 text-[#5A5E6E]" />
          <h3 class="text-base font-semibold text-[#1A1A24]">Mis ayudantías</h3>
          <span class="ml-auto text-xs text-[#5A5E6E]">{cursos.length} {cursos.length === 1 ? 'curso' : 'cursos'}</span>
        </div>

        {#if cursos.length > 0}
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {#each cursos as curso (curso.id_curso)}
              <AyudantiaCard {...curso} />
            {/each}
          </div>
        {:else}
          <div class="flex flex-col items-center gap-2.5 rounded-xl border border-[#E5E7EB] bg-white p-10 text-center shadow-[0_1px_3px_rgba(0,0,0,.08)]">
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-[#E8EDF5]">
              <BookOpen class="h-[21px] w-[21px] text-[#002F6C]" />
            </div>
            <span class="text-[17px] font-semibold text-[#1A1A24]">Todavía no tienes cursos de ayudantía</span>
            <p class="max-w-[420px] text-[13.5px] text-[#5A5E6E]">
              Tu nombramiento está registrado, pero ningún curso te ha asignado aún. Cuando el docente titular te incorpore a un
              componente, el curso aparecerá aquí con su programa y su bandeja de mensajes.
            </p>
            {#if fechaNombramiento}
              <span class="mt-1 border-t border-[#E5E7EB] pt-3 text-[12.5px] text-[#5A5E6E]">
                Nombramiento registrado el {fechaNombramiento}
              </span>
            {/if}
          </div>
        {/if}
      </div>

      <div class="flex flex-col gap-4 lg:col-span-4">
        <MensajesSinLeerCard total={mensajeria.no_leidos} cursos={mensajeria.cursos} />
        {#if esEstudiante}
          <TambienEresEstudianteCard carrera={carreraEstudiante} primary={cursos.length === 0} />
        {/if}
      </div>
    </div>
  </div>
</AyudanteLayout>
