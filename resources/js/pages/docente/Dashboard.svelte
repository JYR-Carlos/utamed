<script lang="ts">
  /**
   * Dashboard del docente — /docente/dashboard.
   *
   * Responde "¿qué me está pidiendo atención hoy?" en el primer vistazo:
   * alertas de syllabus arriba, cursos como titular y como componente en el
   * mismo nivel jerárquico, y las dos mensajerías del sistema (curso.mensaje
   * y agenda.agenda) como bloques separados — ver [[mensajeria-dos-niveles]].
   *
   * "Grupos por evaluar" transversal a todos los cursos con permiso delegado
   * incluido no tiene todavía una consulta que lo arme (requiere cruzar
   * calificaciones, delegación de permisos y grupos sin nota); se muestra
   * como PropuestaCard en vez de inventar datos.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import { BookOpen, ListChecks, Archive, CalendarOff } from 'lucide-svelte';
  import AlertasSyllabus from '@/components/docente/AlertasSyllabus.svelte';
  import CursoTitularCard from '@/components/docente/CursoTitularCard.svelte';
  import ComponenteCard from '@/components/docente/ComponenteCard.svelte';
  import MensajeriaCursoCard from '@/components/docente/MensajeriaCursoCard.svelte';
  import AgendaPendienteCard from '@/components/docente/AgendaPendienteCard.svelte';
  import ProximasFechasCard from '@/components/docente/ProximasFechasCard.svelte';
  import JefaturaBridgeCard from '@/components/docente/JefaturaBridgeCard.svelte';
  import PropuestaCard from '@/components/docente/PropuestaCard.svelte';

  interface CursoTitular {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    estado_syllabus: 'NO_INICIADO' | 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION' | 'APROBADO';
    rechazo?: { razon: string; por: string | null; fecha: string | null } | null;
  }

  interface AlertaSyllabus {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    estado_syllabus: 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION';
    rechazo?: { razon: string; por: string | null; fecha: string | null } | null;
  }

  interface ComponenteCurso {
    id_curso: number;
    id_componente: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    tipo_componente: string;
    titular_nombre?: string | null;
  }

  interface Props {
    docente: { id_docente: number; grado?: string; titulo?: string; cargo?: string; id_usuario: number } | null;
    stats: { nombre_completo: string; total_cursos: number; total_titular?: number; total_componente?: number };
    periodo?: { ano: number; sem: number; fecha_inicio: string | null } | null;
    cursosTitular: CursoTitular[];
    componentes: ComponenteCurso[];
    alertasSyllabus: AlertaSyllabus[];
    mensajeria: { no_leidos: number; cursos: Array<{ id_curso: number; nombre: string; cod_curso: string; no_leidos: number }> };
    agendaPendiente: Array<{ quien: string; actividad_nombre: string; id_curso: number | null; cod_curso: string | null; fecha_envio: string | null }>;
    proximasFechasLimite: Array<{ id_actividad: number; nombre: string; id_curso: number | null; cod_curso: string | null; fecha_limite: string | null }>;
    jefatura: { has_access: boolean; id_contexto?: number | null; carrera?: { id_carrera: number; nombre: string } | null; pendientes_revision?: number };
    entreSemestres?: { ultimo_semestre: number | null; ultimo_agno: number | null; ultima_fecha_fin: string | null } | null;
  }

  let {
    docente,
    stats,
    periodo,
    cursosTitular,
    componentes,
    alertasSyllabus,
    mensajeria,
    agendaPendiente,
    proximasFechasLimite,
    jefatura,
    entreSemestres = null,
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/docente/dashboard' }];

  const primerNombre = $derived(stats.nombre_completo.split(' ')[0] || stats.nombre_completo);

  function formatFecha(fecha: string | null | undefined): string | null {
    if (!fecha) return null;
    const d = new Date(fecha);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }
</script>

<DocenteLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-[1400px] flex-col gap-5 p-4 md:p-6 2xl:p-8">
    {#if entreSemestres}
      <header class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold tracking-tight text-[#1A1A24] md:text-[28px]">Hola, {primerNombre}</h1>
        <span class="text-sm text-[#5A5E6E]">
          Receso académico
          {#if entreSemestres.ultimo_semestre && entreSemestres.ultima_fecha_fin}
            · el {entreSemestres.ultimo_semestre}º semestre {entreSemestres.ultimo_agno} cerró el {formatFecha(entreSemestres.ultima_fecha_fin)}
          {/if}
        </span>
      </header>

      <section class="flex items-start gap-6 rounded-xl border border-[#E5E7EB] bg-white p-8 shadow-sm">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[11px] bg-[#F1F5F9]">
          <CalendarOff class="h-[22px] w-[22px] text-[#5A5E6E]" />
        </div>
        <div class="flex max-w-[620px] flex-col gap-2.5">
          <h3 class="text-lg font-semibold text-[#1A1A24]">No hay cursos activos en este momento</h3>
          <p class="text-sm leading-relaxed text-[#4A4E5C]">
            No tienes cursos como titular ni como componente en el período vigente. Cuando la jefatura de carrera te
            asigne los cursos del próximo período, aparecerán aquí junto con sus syllabus por redactar.
          </p>
          <div class="flex gap-2 pt-1">
            <Link
              href="/docente/cursos"
              class="flex items-center gap-1.5 rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2 text-[13.5px] font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F8FAFC]"
            >
              <Archive class="h-[15px] w-[15px] text-[#5A5E6E]" />
              Ver mis cursos históricos
            </Link>
          </div>
        </div>
      </section>
    {:else}
      <header class="flex items-start justify-between gap-6">
        <div class="flex min-w-0 flex-col gap-1">
          <h1 class="text-2xl font-semibold tracking-tight text-[#1A1A24] md:text-[28px]">Hola, {primerNombre}</h1>
          <span class="text-sm text-[#5A5E6E]">
            {#if periodo}
              {periodo.sem}º semestre {periodo.ano}
              {#if periodo.fecha_inicio} · período vigente desde {formatFecha(periodo.fecha_inicio)}{/if} ·
            {/if}
            {stats.total_cursos} {stats.total_cursos === 1 ? 'curso' : 'cursos'}
          </span>
        </div>
        <Link
          href="/docente/cursos"
          class="flex shrink-0 items-center gap-1.5 rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2 text-sm font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F8FAFC]"
        >
          <BookOpen class="h-[15px] w-[15px] text-[#5A5E6E]" />
          Ver todos mis cursos
        </Link>
      </header>

      {#if jefatura.has_access && jefatura.carrera}
        <JefaturaBridgeCard carreraNombre={jefatura.carrera.nombre} pendientesRevision={jefatura.pendientes_revision ?? 0} />
      {/if}

      {#if alertasSyllabus.length > 0}
        <AlertasSyllabus alertas={alertasSyllabus} />
      {/if}

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-start">
        <div class="flex flex-col gap-4 lg:col-span-8">
          {#if cursosTitular.length > 0}
            <section class="flex flex-col gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
              <div class="flex items-center gap-2">
                <BookOpen class="h-4 w-4 text-[#5A5E6E]" />
                <h3 class="text-base font-semibold text-[#1A1A24]">Cursos que dirijo</h3>
                <span class="text-xs text-[#5A5E6E]">soy titular</span>
                <span class="ml-auto font-mono text-xs text-[#5A5E6E]">{cursosTitular.length}</span>
              </div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {#each cursosTitular as curso (curso.id_curso)}
                  <CursoTitularCard {...curso} />
                {/each}
              </div>
            </section>
          {/if}

          {#if componentes.length > 0}
            <section class="flex flex-col gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
              <div class="flex items-center gap-2">
                <BookOpen class="h-4 w-4 text-[#5A5E6E]" />
                <h3 class="text-base font-semibold text-[#1A1A24]">Cursos donde imparto un componente</h3>
                <span class="ml-auto font-mono text-xs text-[#5A5E6E]">{componentes.length}</span>
              </div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {#each componentes as comp (comp.id_componente)}
                  <ComponenteCard {...comp} />
                {/each}
              </div>
            </section>
          {/if}

          <PropuestaCard
            icon={ListChecks}
            title="Grupos por evaluar"
            emptyTitle="Aún no hay una lista unificada"
            emptyDescription="Reunirá los grupos sin nota de todos tus cursos, incluyendo los que evalúas por permiso delegado. Mientras tanto, evalúa desde Calificaciones."
          />
        </div>

        <div class="flex flex-col gap-4 lg:col-span-4">
          <MensajeriaCursoCard total={mensajeria.no_leidos} cursos={mensajeria.cursos} />
          <AgendaPendienteCard items={agendaPendiente} />
          <ProximasFechasCard items={proximasFechasLimite} />
        </div>
      </div>
    {/if}
  </div>
</DocenteLayout>
