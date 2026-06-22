<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import {
    Search,
    X,
    LayoutGrid,
    List,
    SlidersHorizontal,
    ChevronDown,
    Users,
    BookOpenCheck,
    Bell,
    ArrowRight,
  } from 'lucide-svelte';

  // ── Types ──────────────────────────────────────────────────────────────────

  interface Accent {
    base: string;
    soft: string;
    gradientEnd: string;
    hue: number;
  }

  interface Props {
    cursosSemestre1: any[];
    cursosSemestre2: any[];
    availableRoles?: any[];
    availablePermissions?: Record<string, any[]>;
  }

  // ── Props ──────────────────────────────────────────────────────────────────

  let {
    cursosSemestre1 = [],
    cursosSemestre2 = [],
  }: Props = $props();

  // ── Accent palette (6 rotating colors) ────────────────────────────────────

  const ACCENTS: Accent[] = [
    { base: '#4F46E5', soft: '#EEF0FF', gradientEnd: '#6366F1', hue: 245 },
    { base: '#7C3AED', soft: '#F3EEFF', gradientEnd: '#8B5CF6', hue: 270 },
    { base: '#2563EB', soft: '#EAF1FF', gradientEnd: '#3B82F6', hue: 220 },
    { base: '#C026D3', soft: '#FBEEFC', gradientEnd: '#D946EF', hue: 300 },
    { base: '#0284C7', soft: '#E6F4FB', gradientEnd: '#0EA5E9', hue: 200 },
    { base: '#9333EA', soft: '#F5EEFC', gradientEnd: '#A855F7', hue: 280 },
  ];

  // ── Helpers ────────────────────────────────────────────────────────────────

  function getInitials(name: string): string {
    return (name || '?')
      .split(' ')
      .slice(0, 2)
      .map((w: string) => w[0] || '')
      .join('')
      .toUpperCase() || '?';
  }

  // ── View state ─────────────────────────────────────────────────────────────

  let viewMode = $state<'grid' | 'list'>('grid');
  let query = $state('');
  let sortKey = $state<'name' | 'progress' | 'pending' | 'students'>('name');
  let sortOpen = $state(false);
  let sortRef = $state<HTMLDivElement | null>(null);

  const SORT_LABELS: Record<string, string> = {
    name: 'Alfabético',
    progress: 'Progreso',
    pending: 'Por calificar',
    students: 'N° estudiantes',
  };

  onMount(() => {
    const saved = localStorage.getItem('docente-cursos-view');
    if (saved === 'list' || saved === 'grid') viewMode = saved;

    function onDoc(e: MouseEvent) {
      if (sortRef && !sortRef.contains(e.target as Node)) sortOpen = false;
    }
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  });

  function setViewMode(mode: 'grid' | 'list') {
    viewMode = mode;
    localStorage.setItem('docente-cursos-view', mode);
  }

  // ── Data derivation ────────────────────────────────────────────────────────

  const allCursos = $derived([
    ...cursosSemestre1.map((c: any, i: number) => ({
      ...c,
      _a: ACCENTS[i % ACCENTS.length],
      _sem: 1,
    })),
    ...cursosSemestre2.map((c: any, i: number) => ({
      ...c,
      _a: ACCENTS[i % ACCENTS.length],
      _sem: 2,
    })),
  ]);

  const filtered = $derived.by(() => {
    const q = query.trim().toLowerCase();
    const list = allCursos.filter(
      (c: any) =>
        !q ||
        (c.nombre ?? '').toLowerCase().includes(q) ||
        (c.cod_curso ?? '').toLowerCase().includes(q),
    );
    return [...list].sort((a: any, b: any) => {
      if (sortKey === 'name') return (a.nombre ?? '').localeCompare(b.nombre ?? '');
      if (sortKey === 'progress') return 100//getProgress(b) - getProgress(a);
      if (sortKey === 'pending') return (b.pendientes_calificar ?? 0) - (a.pendientes_calificar ?? 0);
      if (sortKey === 'students') return (b.total_estudiantes ?? 0) - (a.total_estudiantes ?? 0);
      return 0;
    });
  });

  const grouped = $derived.by(() => {
    const s1 = filtered.filter((c: any) => c._sem === 1);
    const s2 = filtered.filter((c: any) => c._sem === 2);
    const out: { label: string; courses: any[] }[] = [];
    if (s1.length) out.push({ label: 'Primer Semestre', courses: s1 });
    if (s2.length) out.push({ label: 'Segundo Semestre', courses: s2 });
    return out;
  });

</script>

<DocenteLayout>
  <div class="max-w-[1280px] mx-auto px-6 py-7 pb-15 max-md:px-4 max-md:py-5 max-md:pb-12">

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <header class="flex items-end justify-between gap-5 mb-8 flex-wrap max-md:flex-col max-md:items-stretch">
      <div>
        <h1 class="text-[2rem] font-bold tracking-tight mb-1 text-[#0E1220] leading-[1.15]">Mis Cursos</h1>
        <p class="text-sm text-[#5C6478] m-0">
          {filtered.length}{filtered.length === 1 ? ' asignatura' : ' asignaturas'}
        </p>
      </div>

      <div class="flex gap-2.5 items-center flex-wrap max-md:w-full">
        <!-- Search -->
        <div class="relative flex items-center bg-white border border-[#E8EAF0] rounded-xl px-2.5 pl-[34px] h-10 w-[260px] transition-all focus-within:border-[#4F46E5] focus-within:ring-4 focus-within:ring-[#EEF0FF] max-md:w-full">
          <span class="absolute left-[11px] text-[#8B92A6] pointer-events-none flex items-center" aria-hidden="true"><Search size={15} /></span>
          <input
            class="flex-1 border-none outline-none bg-transparent text-sm text-[#0E1220]"
            type="search"
            placeholder="Buscar por nombre o código…"
            aria-label="Buscar cursos"
            bind:value={query}
          />
          {#if query}
            <button class="border-none bg-transparent text-[#5C6478] px-1.5 py-1 rounded-md flex items-center cursor-pointer hover:bg-[#F4F5F7] hover:text-[#0E1220]" onclick={() => (query = '')} aria-label="Limpiar búsqueda">
              <X size={13} />
            </button>
          {/if}
        </div>

        <!-- Sort -->
        <div class="relative" bind:this={sortRef}>
          <button
            class="flex items-center gap-[7px] h-10 px-[13px] bg-white border border-[#E8EAF0] rounded-xl text-sm text-[#2B3142] cursor-pointer transition-all hover:border-[#D4D7E0] whitespace-nowrap {sortOpen ? 'border-[#4F46E5] ring-4 ring-[#EEF0FF]' : ''}"
            onclick={() => (sortOpen = !sortOpen)}
            aria-haspopup="listbox"
            aria-expanded={sortOpen}
          >
            <SlidersHorizontal size={15} aria-hidden="true" />
            <span class="text-[#8B92A6]">Ordenar:</span>
            <span class="text-[#0E1220] font-medium">{SORT_LABELS[sortKey]}</span>
            <ChevronDown size={13} aria-hidden="true" />
          </button>
          {#if sortOpen}
            <div class="absolute top-[calc(100%+6px)] right-0 min-w-[200px] bg-white border border-[#E8EAF0] rounded-xl p-1.5 shadow-[0_10px_28px_rgba(20,25,50,0.09)] z-20" role="listbox" aria-label="Criterio de orden">
              {#each Object.entries(SORT_LABELS) as [key, label]}
                <button
                  role="option"
                  aria-selected={sortKey === key}
                  class="block w-full text-left px-[11px] py-[9px] bg-transparent border-none rounded-lg text-sm text-[#2B3142] cursor-pointer hover:bg-[#FAFBFC] {sortKey === key ? 'bg-[#EEF0FF] text-[#4F46E5] font-medium' : ''}"
                  onclick={() => { sortKey = key as any; sortOpen = false; }}
                >
                  {label}
                </button>
              {/each}
            </div>
          {/if}
        </div>

        <!-- Segmented view toggle -->
        <div class="inline-flex bg-white border border-[#E8EAF0] rounded-xl p-1 gap-0.5" role="tablist" aria-label="Modo de vista">
          <button
            role="tab"
            aria-selected={viewMode === 'grid'}
            class="inline-flex items-center gap-1.5 px-[13px] py-[5px] h-8 border-none bg-transparent text-[0.8125rem] font-medium text-[#5C6478] rounded-lg cursor-pointer transition-colors hover:text-[#0E1220] {viewMode === 'grid' ? 'bg-[#EEF0FF] !text-[#4F46E5]' : ''}"
            onclick={() => setViewMode('grid')}
            aria-label="Vista en grilla"
          >
            <LayoutGrid size={15} aria-hidden="true" />
            <span>Grilla</span>
          </button>
          <button
            role="tab"
            aria-selected={viewMode === 'list'}
            class="inline-flex items-center gap-1.5 px-[13px] py-[5px] h-8 border-none bg-transparent text-[0.8125rem] font-medium text-[#5C6478] rounded-lg cursor-pointer transition-colors hover:text-[#0E1220] {viewMode === 'list' ? 'bg-[#EEF0FF] !text-[#4F46E5]' : ''}"
            onclick={() => setViewMode('list')}
            aria-label="Vista en lista"
          >
            <List size={15} aria-hidden="true" />
            <span>Lista</span>
          </button>
        </div>
      </div>
    </header>

    <!-- ── Empty state ─────────────────────────────────────────────────────── -->
    {#if grouped.length === 0}
      <div class="text-center py-14 px-5 border border-dashed border-[#D4D7E0] rounded-2xl bg-white">
        <p class="text-base font-semibold text-[#0E1220] m-0 mb-1">{query ? 'Sin resultados' : 'Sin cursos asignados'}</p>
        <p class="text-sm text-[#5C6478] m-0">
          {#if query}
            No encontramos cursos para "{query}".
          {:else}
            No tienes cursos asignados este periodo.
          {/if}
        </p>
      </div>
    {/if}

    <!-- ── Semester sections ────────────────────────────────────────────────── -->
    {#each grouped as { label, courses } (label)}
      <section class="mb-9">
        <div class="flex items-center gap-2.5 mb-4.5">
          <h2 class="text-base font-semibold text-[#0E1220] m-0 tracking-[-0.01em] whitespace-nowrap">{label}</h2>
          <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-[#FAFBFC] border border-[#E8EAF0] text-xs text-[#5C6478]">
            {courses.length}
          </span>
          <span class="flex-1 h-px bg-[#E8EAF0]" aria-hidden="true"></span>
        </div>

        {#if viewMode === 'grid'}
          <div class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-4">
            {#each courses as curso (curso.id_curso)}
              {@const a = curso._a}
              {@const inits = getInitials(curso.nombre ?? '')}
              {@const urgent = (curso.pendientes_calificar ?? 0) > 0}
              {@const pid = `dp-${curso.id_curso}`}

              <article
                class="group relative bg-white border border-[#E8EAF0] rounded-2xl p-[18px_20px_16px] flex flex-col gap-3.5 overflow-hidden cursor-pointer transition-all hover:-translate-y-0.5 hover:shadow-[0_12px_24px_-8px_rgba(0,0,0,0.10),0_2px_4px_rgba(0,0,0,0.04)] focus-visible:ring-4 focus-visible:ring-[var(--ac-soft)] focus-visible:border-[var(--ac)] text-left outline-none border-solid"
                style="--ac:{a.base};--ac-soft:{a.soft}; hover:border-[var(--ac)]"
                onclick={() => router.visit(`/docente/cursos/${curso.id_curso}`)}
                onkeydown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    router.visit(`/docente/cursos/${curso.id_curso}`);
                  }
                }}
                role="button"
                tabindex="0"
                aria-label="Abrir curso: {curso.nombre}"
              >
                <!-- Rail -->
                <div class="absolute left-0 top-0 bottom-0 w-[3px] bg-[var(--ac)] opacity-0 transition-opacity group-hover:opacity-100 pointer-events-none" aria-hidden="true"></div>

                <div class="flex justify-between items-start gap-2.5">
                  <div class="w-[54px] h-[54px] rounded-[13px] relative overflow-hidden flex items-center justify-center shrink-0" style="background:linear-gradient(135deg,{a.base} 0%,{a.gradientEnd} 100%)">
                    <svg viewBox="0 0 100 100" class="absolute inset-0 w-full h-full" aria-hidden="true">
                      <defs>
                        <pattern id={pid} x="0" y="0" width="14" height="14" patternUnits="userSpaceOnUse">
                          <circle cx="7" cy="7" r="1.2" fill="white" fill-opacity="0.18" />
                        </pattern>
                      </defs>
                      <rect width="100" height="100" fill="url(#{pid})" />
                      <circle cx="78" cy="22" r="34" fill="white" fill-opacity="0.08" />
                      <circle cx="78" cy="22" r="22" fill="white" fill-opacity="0.06" />
                    </svg>
                    <span class="relative text-[1.2rem] font-bold text-white tracking-[-0.01em] select-none">{inits}</span>
                  </div>
                  <div class="flex flex-col items-end gap-1.5">
                    <span class="font-mono text-[0.6875rem] text-[#8B92A6] tracking-[0.02em]">{curso.cod_curso}</span>
                    {#if urgent}<span class="inline-block w-2 h-2 rounded-full bg-[#EF4444] shadow-[0_0_0_3px_rgba(239,68,68,0.18)]" title="Tiene actividades pendientes"></span>{/if}
                  </div>
                </div>

                <div class="flex flex-col gap-1">
                  <h3 class="text-[1.075rem] font-semibold tracking-[-0.015em] m-0 text-[#0E1220] leading-[1.2]">{curso.nombre}</h3>
                  <p class="text-[0.8125rem] text-[#5C6478] m-0">{curso.carrera_nombre || curso.programa_nombre || 'Sin carrera'}</p>
                </div>

                <div class="flex flex-wrap gap-1.5">
                  <div class="inline-flex items-center gap-1 px-2 py-1 rounded-[7px] text-xs {curso.total_estudiantes > 0 ? 'bg-[#FAFBFC] text-[#2B3142] border border-transparent' : 'bg-transparent text-[#8B92A6] border border-[#E8EAF0]'}">
                    <Users size={13} aria-hidden="true" />
                    <span class="font-semibold">{curso.total_estudiantes ?? 0}</span>
                    <span class="opacity-80">estudiantes</span>
                  </div>
                  <div class="inline-flex items-center gap-1 px-2 py-1 rounded-[7px] text-xs {urgent ? 'bg-[#FEF3CC] text-[#92580B] border border-transparent' : 'bg-transparent text-[#8B92A6] border border-[#E8EAF0]'}">
                    <BookOpenCheck size={13} aria-hidden="true" />
                    <span class="font-semibold">{curso.pendientes_calificar ?? 0}</span>
                    <span class="opacity-80">por revisar</span>
                  </div>
                  {#if curso.anuncios != null}
                    <div class="inline-flex items-center gap-1 px-2 py-1 rounded-[7px] text-xs {(curso.anuncios ?? 0) > 0 ? 'bg-[#EAF1FF] text-[#2563EB] border border-transparent' : 'bg-transparent text-[#8B92A6] border border-[#E8EAF0]'}">
                      <Bell size={13} aria-hidden="true" />
                      <span class="font-semibold">{curso.anuncios}</span>
                      <span class="opacity-80">anuncios</span>
                    </div>
                  {/if}
                </div>

              </article>
            {/each}
          </div>

        {:else}
          <div class="flex flex-col gap-2">
            {#each courses as curso (curso.id_curso)}
              {@const a = curso._a}
              {@const inits = getInitials(curso.nombre ?? '')}
              {@const urgent = (curso.pendientes_calificar ?? 0) > 0}
              {@const pid2 = `dpl-${curso.id_curso}`}

              <article
                class="group relative grid grid-cols-[46px_minmax(200px,1fr)_auto_auto_auto] max-md:grid-cols-[40px_1fr_auto] gap-[14px] items-center bg-white border border-[#E8EAF0] rounded-2xl p-[12px_16px_12px_14px] max-md:p-3 overflow-hidden cursor-pointer transition-all hover:-translate-x-0.5 hover:shadow-[0_4px_12px_-4px_rgba(0,0,0,0.09)] focus-visible:ring-4 focus-visible:ring-[var(--ac-soft)] focus-visible:border-[var(--ac)] text-left outline-none border-solid"
                style="--ac:{a.base};--ac-soft:{a.soft}; hover:border-[var(--ac)]"
                onclick={() => router.visit(`/docente/cursos/${curso.id_curso}`)}
                onkeydown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    router.visit(`/docente/cursos/${curso.id_curso}`);
                  }
                }}
                role="button"
                tabindex="0"
                aria-label="Abrir curso: {curso.nombre}"
              >
                <!-- Rail -->
                <div class="absolute left-0 top-0 bottom-0 w-[3px] bg-[var(--ac)] opacity-0 transition-opacity group-hover:opacity-100 pointer-events-none" aria-hidden="true"></div>

                <div class="flex items-center max-md:row-span-2">
                  <div class="w-[44px] h-[44px] rounded-[10px] relative overflow-hidden flex items-center justify-center shrink-0" style="background:linear-gradient(135deg,{a.base} 0%,{a.gradientEnd} 100%)">
                    <svg viewBox="0 0 100 100" class="absolute inset-0 w-full h-full" aria-hidden="true">
                      <defs>
                        <pattern id={pid2} x="0" y="0" width="14" height="14" patternUnits="userSpaceOnUse">
                          <circle cx="7" cy="7" r="1.2" fill="white" fill-opacity="0.18" />
                        </pattern>
                      </defs>
                      <rect width="100" height="100" fill="url(#{pid2})" />
                      <circle cx="78" cy="22" r="34" fill="white" fill-opacity="0.08" />
                    </svg>
                    <span class="relative text-base font-bold text-white tracking-[-0.01em] select-none">{inits}</span>
                  </div>
                </div>

                <div class="flex flex-col gap-0.5 min-w-0 max-md:col-span-2">
                  <div class="flex items-center gap-[7px]">
                    <h3 class="text-base font-semibold m-0 tracking-[-0.01em] whitespace-nowrap overflow-hidden text-ellipsis text-[#0E1220]">{curso.nombre}</h3>
                    {#if urgent}<span class="inline-block w-2 h-2 rounded-full bg-[#EF4444] shadow-[0_0_0_3px_rgba(239,68,68,0.18)] shrink-0" title="Tiene actividades pendientes"></span>{/if}
                  </div>
                  <div class="flex gap-[5px] items-center text-xs text-[#5C6478] min-w-0 overflow-hidden whitespace-nowrap">
                    <span class="font-mono text-[0.6875rem] text-[#8B92A6] tracking-[0.02em]">{curso.cod_curso}</span>
                    <span aria-hidden="true">·</span>
                    <span>{curso.carrera_nombre || curso.programa_nombre || 'Sin carrera'}</span>
                  </div>
                </div>

                <div class="flex gap-1 items-center max-md:col-span-2">
                  <div class="inline-flex items-center gap-1 px-[7px] py-[3px] rounded-[7px] text-xs {curso.total_estudiantes > 0 ? 'bg-[#FAFBFC] text-[#2B3142] border border-transparent' : 'bg-transparent text-[#8B92A6] border border-[#E8EAF0]'}">
                    <Users size={13} aria-hidden="true" />
                    <span class="font-semibold">{curso.total_estudiantes ?? 0}</span>
                  </div>
                  <div class="inline-flex items-center gap-1 px-[7px] py-[3px] rounded-[7px] text-xs {urgent ? 'bg-[#FEF3CC] text-[#92580B] border border-transparent' : 'bg-transparent text-[#8B92A6] border border-[#E8EAF0]'}">
                    <BookOpenCheck size={13} aria-hidden="true" />
                    <span class="font-semibold">{curso.pendientes_calificar ?? 0}</span>
                  </div>
                </div>

<div class="w-[30px] h-[30px] rounded-[8px] bg-[#FAFBFC] flex items-center justify-center text-[#5C6478] transition-all group-hover:bg-[var(--ac)] group-hover:text-white shrink-0 max-md:hidden" aria-hidden="true"><ArrowRight size={17} /></div>
              </article>
            {/each}
          </div>
        {/if}
      </section>
    {/each}

  </div>
</DocenteLayout>
