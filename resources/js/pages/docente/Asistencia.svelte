<script lang="ts">
  /**
   * Centro de Asistencia del docente (transversal a sus cursos).
   *
   * Flujo de 2 pasos + panel:
   *   1. Seleccione el curso al cual quiere tomar asistencia.
   *   2. Seleccione la componente (si imparte/supervisa varias).
   *   3. AsistenciaPanel — registro por sesión implícita (dia + horas).
   *
   * Reglas: cualquier docente asignado a una componente puede tomar asistencia
   * (sea o no titular de la componente — sólo el titular califica). El titular del
   * curso puede tomar asistencia de cualquier componente del curso.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import CentroPasos, { type ComponenteCentro, type CursoCentro } from '@/components/docente/CentroPasos.svelte';
  import type { BreadcrumbItem } from '@/types';
  import AsistenciaPanel from './components/AsistenciaPanel.svelte';

  interface ComponenteAsistencia extends ComponenteCentro {
    total_estudiantes: number;
  }

  interface Props {
    cursos: CursoCentro<ComponenteAsistencia>[];
  }

  let { cursos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Asistencia', href: '/docente/asistencia' },
  ];
</script>

<DocenteLayout {breadcrumbs}>
  <div class="pb-16">
    <div class="mx-auto flex max-w-[1120px] flex-col gap-5 px-4 md:px-0">
      <header class="flex flex-col gap-1">
        <h1 class="m-0 text-2xl font-semibold leading-[30px] text-ink sm:text-[28px] sm:leading-[34px]">Asistencia</h1>
        <p class="m-0 text-sm font-medium text-slate-600">Registra la asistencia de tus estudiantes por sesión.</p>
      </header>

      {#if cursos.length === 0}
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center">
          <p class="m-0 mb-1 text-base font-semibold text-ink">No tienes componentes para tomar asistencia.</p>
          <p class="m-0 text-sm text-slate-600">
            La asistencia se toma sobre los componentes que impartes; aún no tienes ninguno asignado.
          </p>
        </div>
      {:else}
        <CentroPasos {cursos} indicacionCursos="Elige el curso al que quieres tomar asistencia." etiquetaMigas="Selección de asistencia">
          {#snippet datosComponente(comp)}
            {#if comp.total_estudiantes === 0}
              Sin estudiantes inscritos
            {:else}
              {comp.total_estudiantes}
              {comp.total_estudiantes === 1 ? 'estudiante' : 'estudiantes'}
            {/if}
          {/snippet}

          {#snippet contenido(curso, comp)}
            <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
              {#key comp.id_componente}
                <AsistenciaPanel idCurso={curso.id_curso} idComponente={comp.id_componente} tipoComponente={comp.tipo_componente} />
              {/key}
            </section>
          {/snippet}
        </CentroPasos>
      {/if}
    </div>
  </div>
</DocenteLayout>
