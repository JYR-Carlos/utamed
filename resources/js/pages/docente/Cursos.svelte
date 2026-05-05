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

  function getProgress(curso: any): number {
    const total = curso.total_estudiantes || 0;
    const pendientes = curso.pendientes_calificar ?? 0;
    if (total === 0) return 100;
    return Math.round(((total - pendientes) / total) * 100);
  }

  function getProgressColor(p: number): string {
    return p >= 75 ? '#10B981' : p >= 50 ? '#F59E0B' : '#EF4444';
  }

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
      if (sortKey === 'progress') return getProgress(b) - getProgress(a);
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

  // ── Progress ring constants ────────────────────────────────────────────────

  const R = 18;
  const C = 2 * Math.PI * R;
</script>

<DocenteLayout>
  <div class="mc-page">

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <header class="mc-header">
      <div>
        <h1 class="mc-title">Mis Cursos</h1>
        <p class="mc-sub">
          {filtered.length}{filtered.length === 1 ? ' asignatura' : ' asignaturas'}
        </p>
      </div>

      <div class="mc-tools">
        <!-- Search -->
        <div class="mc-search">
          <span class="mc-si" aria-hidden="true"><Search size={15} /></span>
          <input
            class="mc-search-input"
            type="search"
            placeholder="Buscar por nombre o código…"
            aria-label="Buscar cursos"
            bind:value={query}
          />
          {#if query}
            <button class="mc-search-clear" onclick={() => (query = '')} aria-label="Limpiar búsqueda">
              <X size={13} />
            </button>
          {/if}
        </div>

        <!-- Sort -->
        <div class="mc-sort" class:open={sortOpen} bind:this={sortRef}>
          <button
            class="mc-sort-btn"
            onclick={() => (sortOpen = !sortOpen)}
            aria-haspopup="listbox"
            aria-expanded={sortOpen}
          >
            <SlidersHorizontal size={15} aria-hidden="true" />
            <span class="mc-sort-pre">Ordenar:</span>
            <span class="mc-sort-lbl">{SORT_LABELS[sortKey]}</span>
            <ChevronDown size={13} aria-hidden="true" />
          </button>
          {#if sortOpen}
            <div class="mc-sort-menu" role="listbox" aria-label="Criterio de orden">
              {#each Object.entries(SORT_LABELS) as [key, label]}
                <button
                  role="option"
                  aria-selected={sortKey === key}
                  class="mc-sort-item"
                  class:active={sortKey === key}
                  onclick={() => { sortKey = key as any; sortOpen = false; }}
                >
                  {label}
                </button>
              {/each}
            </div>
          {/if}
        </div>

        <!-- Segmented view toggle -->
        <div class="mc-seg" role="tablist" aria-label="Modo de vista">
          <button
            role="tab"
            aria-selected={viewMode === 'grid'}
            class="mc-seg-btn"
            class:active={viewMode === 'grid'}
            onclick={() => setViewMode('grid')}
            aria-label="Vista en grilla"
          >
            <LayoutGrid size={15} aria-hidden="true" />
            <span>Grilla</span>
          </button>
          <button
            role="tab"
            aria-selected={viewMode === 'list'}
            class="mc-seg-btn"
            class:active={viewMode === 'list'}
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
      <div class="mc-empty">
        <p class="mc-empty-title">{query ? 'Sin resultados' : 'Sin cursos asignados'}</p>
        <p class="mc-empty-sub">
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
      <section class="mc-sem">
        <div class="mc-sem-head">
          <h2 class="mc-sem-title">{label}</h2>
          <span class="mc-sem-count">{courses.length}</span>
          <span class="mc-sem-line" aria-hidden="true"></span>
        </div>

        {#if viewMode === 'grid'}
          <div class="mc-grid">
            {#each courses as curso (curso.id_curso)}
              {@const a = curso._a}
              {@const prog = getProgress(curso)}
              {@const pCol = getProgressColor(prog)}
              {@const inits = getInitials(curso.nombre ?? '')}
              {@const urgent = (curso.pendientes_calificar ?? 0) > 0}
              {@const offset = C - (prog / 100) * C}
              {@const pid = `dp-${curso.id_curso}`}

              <article
                class="mc-card"
                style="--ac:{a.base};--ac-soft:{a.soft}"
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
                <div class="mc-rail" aria-hidden="true"></div>

                <div class="mc-card-head">
                  <div class="mc-mono" style="background:linear-gradient(135deg,{a.base} 0%,{a.gradientEnd} 100%)">
                    <svg viewBox="0 0 100 100" class="mc-mono-pat" aria-hidden="true">
                      <defs>
                        <pattern id={pid} x="0" y="0" width="14" height="14" patternUnits="userSpaceOnUse">
                          <circle cx="7" cy="7" r="1.2" fill="white" fill-opacity="0.18" />
                        </pattern>
                      </defs>
                      <rect width="100" height="100" fill="url(#{pid})" />
                      <circle cx="78" cy="22" r="34" fill="white" fill-opacity="0.08" />
                      <circle cx="78" cy="22" r="22" fill="white" fill-opacity="0.06" />
                    </svg>
                    <span class="mc-mono-txt">{inits}</span>
                  </div>
                  <div class="mc-card-meta">
                    <span class="mc-code">{curso.cod_curso}</span>
                    {#if urgent}<span class="mc-dot" title="Tiene actividades pendientes"></span>{/if}
                  </div>
                </div>

                <div class="mc-card-body">
                  <h3 class="mc-card-title">{curso.nombre}</h3>
                  <p class="mc-card-prog">{curso.carrera_nombre || curso.programa_nombre || 'Sin carrera'}</p>
                </div>

                <div class="mc-chips">
                  <div class="mc-chip" class:quiet={!(curso.total_estudiantes > 0)}>
                    <Users size={13} aria-hidden="true" />
                    <span class="cv">{curso.total_estudiantes ?? 0}</span>
                    <span class="cl">estudiantes</span>
                  </div>
                  <div class="mc-chip" class:warn={urgent} class:quiet={!urgent}>
                    <BookOpenCheck size={13} aria-hidden="true" />
                    <span class="cv">{curso.pendientes_calificar ?? 0}</span>
                    <span class="cl">por revisar</span>
                  </div>
                  {#if curso.anuncios != null}
                    <div class="mc-chip" class:info={(curso.anuncios ?? 0) > 0} class:quiet={!(curso.anuncios > 0)}>
                      <Bell size={13} aria-hidden="true" />
                      <span class="cv">{curso.anuncios}</span>
                      <span class="cl">anuncios</span>
                    </div>
                  {/if}
                </div>

                <div class="mc-foot">
                  <svg width="44" height="44" role="img" aria-label="Progreso calificaciones: {prog}%">
                    <circle cx="22" cy="22" r={R} stroke="#E6E8EE" stroke-width="4" fill="none" />
                    <circle
                      cx="22" cy="22" r={R}
                      stroke={pCol} stroke-width="4" fill="none"
                      stroke-linecap="round"
                      stroke-dasharray={C} stroke-dashoffset={offset}
                      transform="rotate(-90 22 22)"
                    />
                    <text x="50%" y="50%" dy="0.35em" text-anchor="middle" class="mc-ring-txt" aria-hidden="true">{prog}%</text>
                  </svg>
                  <div class="mc-foot-meta">
                    <span class="mc-foot-lbl">Calificaciones</span>
                    <span class="mc-foot-sub">{prog === 100 ? 'Completas' : `${100 - prog}% pendiente`}</span>
                  </div>
                  <div class="mc-cta" aria-hidden="true"><ArrowRight size={17} /></div>
                </div>
              </article>
            {/each}
          </div>

        {:else}
          <div class="mc-list">
            {#each courses as curso (curso.id_curso)}
              {@const a = curso._a}
              {@const prog = getProgress(curso)}
              {@const pCol = getProgressColor(prog)}
              {@const inits = getInitials(curso.nombre ?? '')}
              {@const urgent = (curso.pendientes_calificar ?? 0) > 0}
              {@const pid2 = `dpl-${curso.id_curso}`}

              <article
                class="mc-row"
                style="--ac:{a.base};--ac-soft:{a.soft}"
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
                <div class="mc-rail" aria-hidden="true"></div>

                <div class="mc-row-mono">
                  <div class="mc-mono mc-mono-sm" style="background:linear-gradient(135deg,{a.base} 0%,{a.gradientEnd} 100%)">
                    <svg viewBox="0 0 100 100" class="mc-mono-pat" aria-hidden="true">
                      <defs>
                        <pattern id={pid2} x="0" y="0" width="14" height="14" patternUnits="userSpaceOnUse">
                          <circle cx="7" cy="7" r="1.2" fill="white" fill-opacity="0.18" />
                        </pattern>
                      </defs>
                      <rect width="100" height="100" fill="url(#{pid2})" />
                      <circle cx="78" cy="22" r="34" fill="white" fill-opacity="0.08" />
                    </svg>
                    <span class="mc-mono-txt">{inits}</span>
                  </div>
                </div>

                <div class="mc-row-id">
                  <div class="mc-row-tl">
                    <h3 class="mc-row-title">{curso.nombre}</h3>
                    {#if urgent}<span class="mc-dot" title="Tiene actividades pendientes"></span>{/if}
                  </div>
                  <div class="mc-row-sub">
                    <span class="mc-code">{curso.cod_curso}</span>
                    <span aria-hidden="true">·</span>
                    <span>{curso.carrera_nombre || curso.programa_nombre || 'Sin carrera'}</span>
                  </div>
                </div>

                <div class="mc-row-chips">
                  <div class="mc-chip mc-chip-sm" class:quiet={!(curso.total_estudiantes > 0)}>
                    <Users size={13} aria-hidden="true" />
                    <span class="cv">{curso.total_estudiantes ?? 0}</span>
                  </div>
                  <div class="mc-chip mc-chip-sm" class:warn={urgent} class:quiet={!urgent}>
                    <BookOpenCheck size={13} aria-hidden="true" />
                    <span class="cv">{curso.pendientes_calificar ?? 0}</span>
                  </div>
                </div>

                <div class="mc-row-prog">
                  <div class="mc-row-bar">
                    <div class="mc-row-fill" style="width:{prog}%;background:{pCol}"></div>
                  </div>
                  <span class="mc-row-pct">{prog}%</span>
                </div>

                <div class="mc-cta mc-cta-sm" aria-hidden="true"><ArrowRight size={17} /></div>
              </article>
            {/each}
          </div>
        {/if}
      </section>
    {/each}

  </div>
</DocenteLayout>

<style>
  /* ── Page ────────────────────────────────────────────────────────── */
  .mc-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 28px 24px 60px;
  }

  /* ── Header ──────────────────────────────────────────────────────── */
  .mc-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 32px;
    flex-wrap: wrap;
  }
  .mc-title {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin: 0 0 4px;
    color: #0E1220;
    line-height: 1.15;
  }
  .mc-sub { font-size: 0.875rem; color: #5C6478; margin: 0; }
  .mc-tools { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

  /* ── Search ──────────────────────────────────────────────────────── */
  .mc-search {
    position: relative;
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid #E8EAF0;
    border-radius: 12px;
    padding: 0 10px 0 34px;
    height: 40px;
    width: 260px;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  .mc-search:focus-within {
    border-color: #4F46E5;
    box-shadow: 0 0 0 3px #EEF0FF;
  }
  .mc-si {
    position: absolute;
    left: 11px;
    color: #8B92A6;
    pointer-events: none;
    display: flex;
    align-items: center;
  }
  .mc-search-input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.875rem;
    color: #0E1220;
    font-family: inherit;
    appearance: none;
  }
  .mc-search-input::placeholder { color: #8B92A6; }
  .mc-search-input::-webkit-search-cancel-button { display: none; }
  .mc-search-clear {
    border: none;
    background: transparent;
    color: #5C6478;
    padding: 3px 5px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    cursor: pointer;
  }
  .mc-search-clear:hover { background: #F4F5F7; color: #0E1220; }

  /* ── Sort ────────────────────────────────────────────────────────── */
  .mc-sort { position: relative; }
  .mc-sort-btn {
    display: flex;
    align-items: center;
    gap: 7px;
    height: 40px;
    padding: 0 13px;
    background: #fff;
    border: 1px solid #E8EAF0;
    border-radius: 12px;
    font-size: 0.875rem;
    color: #2B3142;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
    white-space: nowrap;
    font-family: inherit;
  }
  .mc-sort-btn:hover { border-color: #D4D7E0; }
  .mc-sort.open .mc-sort-btn {
    border-color: #4F46E5;
    box-shadow: 0 0 0 3px #EEF0FF;
  }
  .mc-sort-pre { color: #8B92A6; }
  .mc-sort-lbl { color: #0E1220; font-weight: 500; }
  .mc-sort-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 200px;
    background: #fff;
    border: 1px solid #E8EAF0;
    border-radius: 12px;
    padding: 6px;
    box-shadow: 0 10px 28px rgba(20,25,50,0.09);
    z-index: 20;
  }
  .mc-sort-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 9px 11px;
    background: transparent;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #2B3142;
    cursor: pointer;
    font-family: inherit;
  }
  .mc-sort-item:hover { background: #FAFBFC; }
  .mc-sort-item.active { background: #EEF0FF; color: #4F46E5; font-weight: 500; }

  /* ── Segmented toggle ────────────────────────────────────────────── */
  .mc-seg {
    display: inline-flex;
    background: #fff;
    border: 1px solid #E8EAF0;
    border-radius: 12px;
    padding: 4px;
    gap: 2px;
  }
  .mc-seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 13px;
    height: 32px;
    border: none;
    background: transparent;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #5C6478;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    font-family: inherit;
  }
  .mc-seg-btn:hover { color: #0E1220; }
  .mc-seg-btn.active { background: #EEF0FF; color: #4F46E5; }

  /* ── Semester section ────────────────────────────────────────────── */
  .mc-sem { margin-bottom: 36px; }
  .mc-sem-head { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
  .mc-sem-title {
    font-size: 1rem;
    font-weight: 600;
    color: #0E1220;
    margin: 0;
    letter-spacing: -0.01em;
    white-space: nowrap;
  }
  .mc-sem-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 11px;
    background: #FAFBFC;
    border: 1px solid #E8EAF0;
    font-size: 0.75rem;
    color: #5C6478;
  }
  .mc-sem-line { flex: 1; height: 1px; background: #E8EAF0; }

  /* ── Grid layout ─────────────────────────────────────────────────── */
  .mc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
  }

  /* ── Shared: accent rail ─────────────────────────────────────────── */
  .mc-rail {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: var(--ac);
    opacity: 0;
    transition: opacity 0.18s ease;
    pointer-events: none;
  }

  /* ── Card ────────────────────────────────────────────────────────── */
  .mc-card {
    position: relative;
    background: #fff;
    border: 1px solid #E8EAF0;
    border-radius: 16px;
    padding: 18px 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    text-align: left;
    outline: none;
    font-family: inherit;
    border-style: solid;
  }
  .mc-card:hover {
    transform: translateY(-2px);
    border-color: var(--ac);
    box-shadow: 0 12px 24px -8px rgba(0,0,0,0.10), 0 2px 4px rgba(0,0,0,0.04);
  }
  .mc-card:focus-visible {
    box-shadow: 0 0 0 3px var(--ac-soft), 0 0 0 5px var(--ac);
  }
  .mc-card:hover .mc-rail { opacity: 1; }
  .mc-card:hover .mc-cta { background: var(--ac); color: #fff; transform: translateX(2px); }

  /* ── Card head ───────────────────────────────────────────────────── */
  .mc-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
  .mc-card-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
  .mc-code { font-family: ui-monospace, monospace; font-size: 0.6875rem; color: #8B92A6; letter-spacing: 0.02em; }
  .mc-dot {
    display: inline-block;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #EF4444;
    box-shadow: 0 0 0 3px rgba(239,68,68,0.18);
  }

  /* ── Monogram ────────────────────────────────────────────────────── */
  .mc-mono {
    width: 54px; height: 54px;
    border-radius: 13px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .mc-mono-sm { width: 44px; height: 44px; border-radius: 10px; }
  .mc-mono-pat { position: absolute; inset: 0; width: 100%; height: 100%; }
  .mc-mono-txt {
    position: relative;
    font-size: 1.2rem;
    font-weight: 700;
    color: white;
    letter-spacing: -0.01em;
    user-select: none;
  }
  .mc-mono-sm .mc-mono-txt { font-size: 1rem; }

  /* ── Card body ───────────────────────────────────────────────────── */
  .mc-card-body { display: flex; flex-direction: column; gap: 3px; }
  .mc-card-title {
    font-size: 1.075rem;
    font-weight: 600;
    letter-spacing: -0.015em;
    margin: 0;
    color: #0E1220;
    line-height: 1.2;
  }
  .mc-card-prog { font-size: 0.8125rem; color: #5C6478; margin: 0; }

  /* ── Chips ───────────────────────────────────────────────────────── */
  .mc-chips { display: flex; flex-wrap: wrap; gap: 5px; }
  .mc-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 7px;
    font-size: 0.75rem;
    background: #FAFBFC;
    color: #2B3142;
    border: 1px solid transparent;
  }
  .mc-chip-sm { padding: 3px 7px; }
  .mc-chip.quiet { color: #8B92A6; background: transparent; border-color: #E8EAF0; }
  .mc-chip.warn { background: #FEF3CC; color: #92580B; }
  .mc-chip.info { background: #EAF1FF; color: #2563EB; }
  .cv { font-weight: 600; }
  .cl { opacity: 0.8; }

  /* ── Card foot (ring + meta + cta) ──────────────────────────────── */
  .mc-foot {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 11px;
    padding-top: 13px;
    border-top: 1px solid #E8EAF0;
  }
  .mc-ring-txt { font-size: 0.6875rem; font-weight: 700; fill: #0E1220; }
  .mc-foot-meta { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
  .mc-foot-lbl { font-size: 0.8125rem; font-weight: 500; color: #0E1220; }
  .mc-foot-sub { font-size: 0.75rem; color: #5C6478; }

  /* ── CTA arrow ───────────────────────────────────────────────────── */
  .mc-cta {
    width: 34px; height: 34px;
    border-radius: 9px;
    background: #FAFBFC;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #5C6478;
    transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
    flex-shrink: 0;
  }
  .mc-cta-sm { width: 30px; height: 30px; border-radius: 8px; }

  /* ── List layout ─────────────────────────────────────────────────── */
  .mc-list { display: flex; flex-direction: column; gap: 8px; }
  .mc-row {
    position: relative;
    display: grid;
    grid-template-columns: 46px minmax(200px, 1fr) auto auto auto;
    gap: 14px;
    align-items: center;
    background: #fff;
    border: 1px solid #E8EAF0;
    border-radius: 14px;
    padding: 12px 16px 12px 14px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    text-align: left;
    outline: none;
    font-family: inherit;
    border-style: solid;
  }
  .mc-row:hover {
    border-color: var(--ac);
    transform: translateX(2px);
    box-shadow: 0 4px 12px -4px rgba(0,0,0,0.09);
  }
  .mc-row:focus-visible {
    box-shadow: 0 0 0 3px var(--ac-soft), 0 0 0 5px var(--ac);
  }
  .mc-row:hover .mc-rail { opacity: 1; }
  .mc-row:hover .mc-cta { background: var(--ac); color: #fff; }

  .mc-row-mono { display: flex; align-items: center; }
  .mc-row-id { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
  .mc-row-tl { display: flex; align-items: center; gap: 7px; }
  .mc-row-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #0E1220;
  }
  .mc-row-sub {
    display: flex;
    gap: 5px;
    align-items: center;
    font-size: 0.75rem;
    color: #5C6478;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
  }
  .mc-row-chips { display: flex; gap: 4px; align-items: center; }
  .mc-row-prog { display: flex; align-items: center; gap: 9px; width: 160px; flex-shrink: 0; }
  .mc-row-bar { flex: 1; height: 6px; background: #E8EAF0; border-radius: 999px; overflow: hidden; }
  .mc-row-fill { height: 100%; border-radius: 999px; transition: width 0.4s ease; }
  .mc-row-pct { font-size: 0.75rem; font-weight: 600; color: #2B3142; min-width: 34px; text-align: right; }

  /* ── Empty state ─────────────────────────────────────────────────── */
  .mc-empty {
    text-align: center;
    padding: 56px 20px;
    border: 1px dashed #D4D7E0;
    border-radius: 16px;
    background: #fff;
  }
  .mc-empty-title { font-size: 1rem; font-weight: 600; color: #0E1220; margin: 0 0 4px; }
  .mc-empty-sub { font-size: 0.875rem; color: #5C6478; margin: 0; }

  /* ── Responsive ──────────────────────────────────────────────────── */
  @media (max-width: 768px) {
    .mc-page { padding: 20px 16px 48px; }
    .mc-header { flex-direction: column; align-items: stretch; }
    .mc-tools { flex-wrap: wrap; }
    .mc-search { width: 100%; }
    .mc-row {
      grid-template-columns: 40px 1fr auto;
      grid-template-rows: auto auto;
      row-gap: 8px;
    }
    .mc-row-chips { grid-column: 2 / 4; }
    .mc-row-prog { display: none; }
  }
</style>
