<script lang="ts">
  import { Link } from '@inertiajs/svelte';
  import { Search, Folder, ChevronRight, BookOpenCheck, Calendar } from 'lucide-svelte';
  import type { SidebarCourse } from '@/types';
  import { hasPermission } from '@/services/permissionValidator';

  /**
   * Lista de cursos con buscador, período vigente e histórico colapsable.
   *
   * Un solo componente para docente, estudiante y ayudante: lo único que
   * cambia por rol es el prefijo de ruta y qué accesos rápidos (Programa /
   * Actividades) tienen sentido, según qué rutas existen para ese rol.
   */
  interface Props {
    courses: SidebarCourse[];
    basePath: string;
    showPrograma?: boolean;
    showActividades?: boolean;
    emptyLabel: string;
  }

  let { courses, basePath, showPrograma = false, showActividades = false, emptyLabel }: Props = $props();

  let byYear = $derived(
    courses.reduce(
      (acc, curso) => {
        const year = curso.agno_real;
        const sem = curso.semestre_real;
        if (year == null || sem == null) return acc;
        if (!acc[year]) acc[year] = {};
        if (!acc[year][sem]) acc[year][sem] = [];
        acc[year][sem].push(curso);
        return acc;
      },
      {} as Record<number, Record<number, SidebarCourse[]>>,
    ),
  );

  let sortedYears = $derived(
    Object.keys(byYear)
      .map(Number)
      .filter((n) => !isNaN(n))
      .sort((a, b) => b - a),
  );

  // Período vigente = el más reciente con cursos asignados.
  let currentPeriod = $derived.by(() => {
    const year = sortedYears[0];
    if (year == null) return null;
    const sems = Object.keys(byYear[year])
      .map(Number)
      .sort((a, b) => b - a);
    if (sems.length === 0) return null;
    return { year, sem: sems[0] };
  });

  let currentCourses = $derived(
    currentPeriod ? byYear[currentPeriod.year][currentPeriod.sem] : [],
  );

  let historicalYears = $derived(
    sortedYears
      .map((year) => ({
        year,
        sems: Object.keys(byYear[year])
          .map(Number)
          .sort((a, b) => b - a)
          .filter(
            (sem) => !(currentPeriod && year === currentPeriod.year && sem === currentPeriod.sem),
          ),
      }))
      .filter((y) => y.sems.length > 0),
  );

  // Historial aplanado a un nivel: "Año · Semestre" en vez de año > semestre.
  let historicalGroups = $derived(
    historicalYears.flatMap(({ year, sems }) =>
      sems.map((sem) => ({
        key: `${year}-${sem}`,
        label: `Año ${year} · Semestre ${sem}`,
        courses: byYear[year][sem],
      })),
    ),
  );

  let historicalCount = $derived(
    historicalGroups.reduce((acc, g) => acc + g.courses.length, 0),
  );

  // ─── Acordeón (NAV-02) ──────────────────────────────────────────────────
  // Período actual desplegado y anteriores plegados por defecto; lo que el
  // usuario elija se recuerda en localStorage (por rol, vía basePath).
  const storageKey = () => `utamed:course-list-nav:${basePath}`;

  function leerEstado(): { actual: boolean; anteriores: boolean } {
    try {
      const guardado = JSON.parse(localStorage.getItem(storageKey()) ?? 'null');
      if (guardado && typeof guardado === 'object') {
        return { actual: guardado.actual !== false, anteriores: guardado.anteriores === true };
      }
    } catch {
      // Sin almacenamiento disponible: valores por defecto.
    }
    return { actual: true, anteriores: false };
  }

  const estadoInicial = leerEstado();
  let currentOpen = $state(estadoInicial.actual);
  let historicalOpen = $state(estadoInicial.anteriores);

  function guardarEstado() {
    try {
      localStorage.setItem(
        storageKey(),
        JSON.stringify({ actual: currentOpen, anteriores: historicalOpen }),
      );
    } catch {
      // No se pudo recordar; no afecta a la navegación.
    }
  }

  let search = $state('');

  function matches(curso: SidebarCourse, q: string) {
    return curso.nombre.toLowerCase().includes(q) || curso.cod_curso.toLowerCase().includes(q);
  }

  let filteredCurrent = $derived.by(() => {
    const q = search.trim().toLowerCase();
    if (!q) return currentCourses;
    return currentCourses.filter((c) => matches(c, q));
  });

  let filteredHistorical = $derived.by(() => {
    const q = search.trim().toLowerCase();
    if (!q) return historicalGroups;
    return historicalGroups
      .map((g) => ({ ...g, courses: g.courses.filter((c) => matches(c, q)) }))
      .filter((g) => g.courses.length > 0);
  });

  // Mientras se busca, los grupos con coincidencias se muestran desplegados
  // sin tocar lo que el usuario dejó guardado.
  let buscando = $derived(search.trim() !== '');
  let mostrarActual = $derived(currentOpen || buscando);
  let mostrarAnteriores = $derived(historicalOpen || (buscando && filteredHistorical.length > 0));
</script>

{#snippet courseRow(curso: SidebarCourse, current: boolean)}
  <div
    class="rounded-xl border px-3 py-2.5 transition-all {current
      ? 'bg-white border-slate-100 hover:border-slate-200 hover:shadow-sm'
      : 'bg-slate-50 border-transparent'}"
  >
    <Link href="{basePath}/{curso.id_curso}" class="flex items-center gap-2 group">
      <Folder
        size={current ? 18 : 16}
        class="{current ? 'text-[#678FCA]' : 'text-slate-400'} shrink-0"
      />
      <div class="flex-1 min-w-0 flex flex-col">
        <span class="font-mono text-[10.5px] text-slate-400 truncate">{curso.cod_curso}</span>
        <span
          class="truncate text-[13.5px] font-semibold text-slate-700 group-hover:text-[#22213F] transition-colors"
          title={curso.nombre}>{curso.nombre}</span
        >
      </div>
    </Link>
    {#if showPrograma || showActividades}
      <div class="flex items-center gap-1.5 flex-wrap mt-2">
        {#if showPrograma}
          {#if curso.tiene_programa}
            <Link
              href="{basePath}/{curso.id_curso}/programa"
              class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-all"
            >
              <BookOpenCheck size={14} class="shrink-0" /> Programa
            </Link>
          {:else if hasPermission(curso.permisos, 'cursos/programas:crear')}
            <Link
              href="{basePath}/{curso.id_curso}/programa/create"
              class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 transition-all"
            >
              <BookOpenCheck size={14} class="shrink-0" /> Crear programa
            </Link>
          {/if}
        {/if}
        {#if showActividades}
          <Link
            href="{basePath}/{curso.id_curso}/actividades"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-600 bg-slate-50 hover:bg-[#22213F]/8 hover:text-[#22213F] transition-all"
          >
            <Calendar size={14} class="shrink-0" /> Actividades
          </Link>
        {/if}
      </div>
    {/if}
  </div>
{/snippet}

<!-- Buscador -->
<div class="px-4 mb-3">
  <div
    class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg transition-all focus-within:border-[#22213F]/40 focus-within:ring-2 focus-within:ring-[#22213F]/10"
  >
    <Search size={14} class="text-slate-400 shrink-0" />
    <input
      type="text"
      bind:value={search}
      placeholder="Buscar curso o período…"
      class="flex-1 bg-transparent border-0 outline-none text-[13px] text-slate-700 placeholder:text-slate-400"
    />
  </div>
</div>

{#if courses.length === 0}
  <p class="px-8 py-2 text-sm text-slate-400 italic font-medium">{emptyLabel}</p>
{:else}
  <!-- Período actual: desplegado por defecto, plegable -->
  {#if currentPeriod}
    <div class="px-4 mt-2 mb-2">
      <button
        type="button"
        class="flex w-full items-center gap-2 px-2 py-1.5 mb-1 rounded-lg text-left hover:bg-slate-50 transition-all"
        aria-expanded={mostrarActual}
        onclick={() => {
          currentOpen = !mostrarActual;
          guardarEstado();
        }}
      >
        <ChevronRight
          size={12}
          class="shrink-0 text-[#22213F] transition-transform {mostrarActual ? 'rotate-90' : ''}"
        />
        <span class="text-[11px] font-bold uppercase tracking-widest text-[#22213F]">
          Período actual · Año {currentPeriod.year} · Semestre {currentPeriod.sem}
        </span>
        <span
          class="ml-auto text-[10px] font-semibold text-[#22213F]/70 bg-[#22213F]/5 rounded-full px-2 py-0.5"
        >
          {currentCourses.length}
        </span>
      </button>

      {#if mostrarActual}
        <div class="flex flex-col gap-1.5">
          {#each filteredCurrent as curso (curso.id_curso)}
            {@render courseRow(curso, true)}
          {/each}
          {#if filteredCurrent.length === 0}
            <p class="px-2 py-1 text-xs text-slate-400 italic">Sin coincidencias</p>
          {/if}
        </div>
      {/if}
    </div>
  {/if}

  <!-- Períodos anteriores: un solo nivel "Año · Semestre" en un <details> nativo. -->
  {#if historicalYears.length > 0}
    <div class="px-4">
      <details
        open={mostrarAnteriores}
        ontoggle={(e) => {
          const abierto = (e.currentTarget as HTMLDetailsElement).open;
          if (abierto !== mostrarAnteriores && !buscando) {
            historicalOpen = abierto;
            guardarEstado();
          }
        }}
      >
        <summary
          class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wide text-slate-500 hover:bg-slate-50 transition-all cursor-pointer list-none [&::-webkit-details-marker]:hidden"
        >
          <ChevronRight
            size={12}
            class="shrink-0 text-slate-400 transition-transform {mostrarAnteriores ? 'rotate-90' : ''}"
          />
          Períodos anteriores
          <span
            class="ml-auto text-[10px] font-semibold text-slate-400 bg-slate-100 rounded-full px-2 py-0.5"
          >
            {historicalCount}
          </span>
        </summary>

        <div class="flex flex-col gap-3 mt-2 mb-1">
          {#each filteredHistorical as group (group.key)}
            <div>
              <p class="px-2 mb-1.5 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">
                {group.label}
              </p>
              <div class="flex flex-col gap-1.5">
                {#each group.courses as curso (curso.id_curso)}
                  {@render courseRow(curso, false)}
                {/each}
              </div>
            </div>
          {/each}
          {#if search.trim() && filteredHistorical.length === 0}
            <p class="px-2 py-1 text-xs text-slate-400 italic">
              Sin coincidencias en períodos anteriores
            </p>
          {/if}
        </div>
      </details>
    </div>
  {/if}
{/if}
