<script lang="ts">
  /**
   * Centro de Calificaciones del docente (transversal a sus cursos).
   *
   * Flujo de 3 pasos:
   *   1. Seleccione el curso a calificar.
   *   2. Seleccione la componente.
   *   3. Elija la actividad → abre la pantalla de evaluación (evaluar grupo).
   *
   * Reglas: el titular del curso ve todas las componentes; el resto sólo las que
   * imparte. La evaluación (rúbrica + nota) la realiza el titular en la pantalla
   * de la actividad.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import CentroPasos, { type ComponenteCentro, type CursoCentro } from '@/components/docente/CentroPasos.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import { formatFechaCorta } from '@/utils/formatters';
  import { ChevronRight, Users, CalendarClock, AlertTriangle } from 'lucide-svelte';

  interface ActividadCalif {
    id_actividad: number;
    nombre: string;
    tipo_actividad: string;
    es_grupal: boolean;
    fecha_limite: string;
    tiene_grupos: boolean;
  }

  interface ComponenteCalif extends ComponenteCentro {
    actividades: ActividadCalif[];
  }

  interface Props {
    cursos: CursoCentro<ComponenteCalif>[];
  }

  let { cursos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Calificaciones', href: '/docente/calificaciones' },
  ];
</script>

{#snippet datosActividad(act: ActividadCalif)}
  <span class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-slate-600">
    <span class="font-semibold {act.tipo_actividad === 'SUMATIVA' ? 'text-ocre' : 'text-emerald-700'}">
      {act.tipo_actividad === 'SUMATIVA' ? 'Sumativa' : 'Formativa'}
    </span>
    <span class="inline-flex items-center gap-1">
      <Users size={12} aria-hidden="true" />
      {act.es_grupal ? 'Grupal' : 'Individual'}
    </span>
    <span class="inline-flex items-center gap-1">
      <CalendarClock size={12} aria-hidden="true" />
      {formatFechaCorta(act.fecha_limite)}
    </span>
  </span>
{/snippet}

<DocenteLayout {breadcrumbs}>
  <div class="pb-16">
    <div class="mx-auto flex max-w-[1120px] flex-col gap-5 px-4 md:px-0">
      <header class="flex flex-col gap-1">
        <h1 class="m-0 text-2xl font-semibold leading-[30px] text-ink sm:text-[28px] sm:leading-[34px]">Calificaciones</h1>
        <p class="m-0 text-sm font-medium text-slate-600">Evalúa las actividades de tus cursos con rúbrica y nota.</p>
      </header>

      {#if cursos.length === 0}
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center">
          <p class="m-0 mb-1 text-base font-semibold text-ink">No tienes actividades para calificar.</p>
          <p class="m-0 text-sm text-slate-600">
            Las calificaciones se hacen sobre las actividades de los componentes que impartes; aún no hay ninguna.
          </p>
        </div>
      {:else}
        <CentroPasos {cursos} indicacionCursos="Elige el curso que quieres calificar." etiquetaMigas="Selección de calificación">
          {#snippet datosComponente(comp)}
            {comp.actividades.length}
            {comp.actividades.length === 1 ? 'actividad' : 'actividades'}
          {/snippet}

          {#snippet contenido(curso, comp)}
            <p class="m-0 text-sm font-medium text-slate-600">Elige la actividad que quieres evaluar.</p>
            <div class="flex flex-col gap-3">
              {#each comp.actividades as act (act.id_actividad)}
                {#if act.tiene_grupos}
                  <Link
                    href={`/docente/cursos/${curso.id_curso}/actividades/${act.id_actividad}/evaluacion`}
                    class="flex min-h-11 items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 no-underline transition-colors hover:border-uta-blue hover:bg-slate-50"
                  >
                    <span class="flex min-w-0 flex-col gap-1">
                      <span class="line-clamp-2 text-sm font-semibold leading-snug text-ink">{act.nombre}</span>
                      {@render datosActividad(act)}
                    </span>
                    <ChevronRight size={18} class="shrink-0 text-slate-500" aria-hidden="true" />
                  </Link>
                {:else}
                  <div class="flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="flex min-w-0 flex-col gap-1">
                      <span class="line-clamp-2 text-sm font-semibold leading-snug text-ink">{act.nombre}</span>
                      {@render datosActividad(act)}
                    </span>
                    <span class="flex shrink-0 items-center gap-1.5 text-xs font-semibold text-ocre">
                      <AlertTriangle size={15} aria-hidden="true" />
                      Sin grupos asignados
                    </span>
                  </div>
                {/if}
              {/each}
            </div>
          {/snippet}
        </CentroPasos>
      {/if}
    </div>
  </div>
</DocenteLayout>
