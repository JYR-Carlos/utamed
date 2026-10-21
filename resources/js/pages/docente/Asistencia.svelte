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
  import type { BreadcrumbItem } from '@/types';
  import {
    ClipboardCheck,
    ChevronRight,
    BookOpen,
    Layers,
    Crown,
    Users,
    GraduationCap,
  } from 'lucide-svelte';
  import AsistenciaPanel from './components/AsistenciaPanel.svelte';

  interface ComponenteAsistencia {
    id_componente: number;
    tipo_componente: string;
    imparte: boolean;
    es_titular_componente: boolean;
    total_estudiantes: number;
  }

  interface CursoAsistencia {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    agno_real: number;
    semestre_real: number;
    es_titular_curso: boolean;
    componentes: ComponenteAsistencia[];
  }

  interface Props {
    cursos: CursoAsistencia[];
  }

  let { cursos }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Asistencia', href: '/docente/asistencia' },
  ];

  let cursoSel = $state<number | null>(null);
  let componenteSel = $state<number | null>(null);

  // Auto-selección cuando hay una sola opción (evita clics innecesarios).
  $effect(() => {
    if (cursoSel === null && cursos.length === 1) {
      cursoSel = cursos[0].id_curso;
    }
  });
  $effect(() => {
    const c = cursos.find((x) => x.id_curso === cursoSel);
    if (cursoSel !== null && componenteSel === null && c && c.componentes.length === 1) {
      componenteSel = c.componentes[0].id_componente;
    }
  });

  const cursoActual = $derived(cursos.find((c) => c.id_curso === cursoSel) ?? null);
  const componenteActual = $derived(
    cursoActual?.componentes.find((c) => c.id_componente === componenteSel) ?? null,
  );

  function elegirCurso(id: number) {
    cursoSel = id;
    componenteSel = null;
  }

  function resetCurso() {
    cursoSel = null;
    componenteSel = null;
  }

  function resetComponente() {
    componenteSel = null;
  }

  function periodo(c: CursoAsistencia): string {
    // Sin año (null) se muestra sólo el semestre, no el literal «null».
    const sem = `${c.semestre_real === 1 ? '1er' : '2do'} Sem.`;
    return c.agno_real ? `${sem} · ${c.agno_real}` : sem;
  }
</script>

<DocenteLayout {breadcrumbs}>
  <div class="max-w-[1100px] mx-auto w-full p-4 md:p-6 lg:p-8 flex flex-col gap-6">
    <!-- Header -->
    <header class="flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-uta-blue-light shrink-0">
        <ClipboardCheck size={22} class="text-navy" />
      </div>
      <div>
        <h1 class="text-2xl font-extrabold text-ink tracking-tight m-0 leading-tight">Asistencia</h1>
        <p class="text-sm text-ink-2 m-0">Registra la asistencia de tus estudiantes por sesión.</p>
      </div>
    </header>

    {#if cursos.length === 0}
      <div class="flex flex-col items-center justify-center py-20 text-center rounded-2xl border-2 border-dashed bg-sand border-sand-line">
        <GraduationCap size={36} class="text-ink-3 mb-3" />
        <p class="text-sm font-medium text-ink-2">No tienes componentes para tomar asistencia.</p>
        <p class="text-xs text-muted-foreground mt-1 max-w-sm">
          La asistencia se toma sobre los componentes que impartes; aún no tienes ninguno asignado.
        </p>
      </div>
    {:else}
      <!-- Migas de selección -->
      <nav class="flex items-center gap-2 text-sm flex-wrap" aria-label="Selección de asistencia">
        <button
          onclick={resetCurso}
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-all {cursoSel === null ? 'bg-navy text-white' : 'bg-sand text-ink-2 hover:bg-sand-2'}"
        >
          <BookOpen size={14} />
          {cursoActual ? cursoActual.nombre : 'Curso'}
        </button>
        {#if cursoActual}
          <ChevronRight size={14} class="text-ink-3" />
          <button
            onclick={resetComponente}
            disabled={cursoActual.componentes.length <= 1}
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-all {componenteSel === null ? 'bg-navy text-white' : 'bg-sand text-ink-2 hover:bg-sand-2'} disabled:opacity-60 disabled:cursor-default"
          >
            <Layers size={14} />
            {componenteActual ? componenteActual.tipo_componente : 'Componente'}
          </button>
        {/if}
      </nav>

      <!-- PASO 1: elegir curso -->
      {#if cursoSel === null}
        <section class="flex flex-col gap-4">
          <h2 class="text-base font-bold text-ink m-0">
            Seleccione el curso al cual quiere tomar asistencia
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {#each cursos as c (c.id_curso)}
              <button
                onclick={() => elegirCurso(c.id_curso)}
                class="flex flex-col gap-3 p-5 rounded-xl border bg-white border-sand-2 text-left transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md hover:border-navy/30"
              >
                <div class="flex items-start justify-between gap-2">
                  <span class="font-mono text-[12px] bg-uta-blue-light text-navy px-2 py-0.5 rounded-md font-semibold">{c.cod_curso}</span>
                  {#if c.es_titular_curso}
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-ocre">
                      <Crown size={10} /> Titular
                    </span>
                  {/if}
                </div>
                <p class="text-sm font-semibold text-ink leading-snug line-clamp-2 m-0">{c.nombre}</p>
                <div class="flex items-center gap-3 text-xs text-muted-foreground mt-auto">
                  <span>{periodo(c)}</span>
                  <span class="inline-flex items-center gap-1">
                    <Layers size={12} />
                    {c.componentes.length} compon.
                  </span>
                </div>
              </button>
            {/each}
          </div>
        </section>

        <!-- PASO 2: elegir componente -->
      {:else if componenteSel === null && cursoActual}
        <section class="flex flex-col gap-4">
          <h2 class="text-base font-bold text-ink m-0">Seleccione la componente</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {#each cursoActual.componentes as comp (comp.id_componente)}
              <button
                onclick={() => (componenteSel = comp.id_componente)}
                class="flex flex-col gap-3 p-5 rounded-xl border bg-white border-sand-2 text-left transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md hover:border-navy/30"
              >
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-uta-blue-light shrink-0">
                      <Layers size={16} class="text-navy" />
                    </div>
                    <span class="text-sm font-bold text-ink">{comp.tipo_componente}</span>
                  </div>
                  {#if comp.es_titular_componente}
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-ocre" title="Titular de la componente (también califica)">
                      <Crown size={10} /> Titular
                    </span>
                  {:else if comp.imparte}
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-emerald-700">Imparte</span>
                  {:else}
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sand text-muted-foreground" title="Supervisión como titular del curso">Supervisión</span>
                  {/if}
                </div>
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-auto">
                  <Users size={12} />
                  {comp.total_estudiantes} estudiante{comp.total_estudiantes !== 1 ? 's' : ''}
                </div>
              </button>
            {/each}
          </div>
        </section>

        <!-- PASO 3: panel de asistencia -->
      {:else if cursoActual && componenteActual}
        <section class="bg-white rounded-2xl border border-sand-2 p-5 md:p-6">
          {#key componenteActual.id_componente}
            <AsistenciaPanel
              idCurso={cursoActual.id_curso}
              idComponente={componenteActual.id_componente}
              tipoComponente={componenteActual.tipo_componente}
            />
          {/key}
        </section>
      {/if}
    {/if}
  </div>
</DocenteLayout>
