<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';

  interface Estado {
    id_estado: number;
    titulo: string;
  }

  interface Actividad {
    id_actividad: number;
    nombre: string;
    fecha_limite: string;
    tipo_actividad: number;
    tipo_entrega: string;
    es_grupal: boolean;
    max_integrantes: number;
    componente: { id_componente: number; tipo: string } | null;
    unidad: { id_unidad: number; nombre: string } | null;
    asignado: boolean;
    grupo_numero: number | null;
    nota_grupal: number | null;
    nota_individual: number | null;
    diferencia_decimas: number | null;
    estado: Estado | null;
  }

  interface Props {
    curso: {
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura_nombre: string;
    };
    actividades: Actividad[];
  }

  let { curso, actividades }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso.nombre, href: `/estudiante/cursos/${curso.id_curso}` },
    { title: 'Actividades', href: '' },
  ]);

  let activeUnit = $state<number | 'all'>('all');
  let filterSumativa = $state(false);
  let filterConEntrega = $state(false);
  let filterGrupal = $state(false);

  function notaFinal(act: Actividad): number | null {
    if (act.nota_individual !== null && act.nota_individual !== undefined) {
      const diff = act.diferencia_decimas ?? 0;
      return Math.min(10, Math.max(0, act.nota_individual + diff * 0.1));
    }
    return act.nota_grupal;
  }

  function getStatus(act: Actividad): 'graded' | 'submitted' | 'pending' | 'notdue' {
    const nota = notaFinal(act);
    if (nota !== null) return 'graded';
    if (!act.asignado) return 'notdue';
    const t = act.estado?.titulo?.toLowerCase() ?? '';
    if (t.includes('entregad') || t.includes('completad')) return 'submitted';
    return 'pending';
  }

  function formatDate(d: string) {
    return new Date(d).toLocaleDateString('es-CL', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  }

  function isUrgent(fechaLimite: string): boolean {
    const diff = new Date(fechaLimite).getTime() - Date.now();
    return diff > 0 && diff < 7 * 24 * 60 * 60 * 1000;
  }

  const unidades = $derived.by(() => {
    const map = new Map<number, { id: number; nombre: string; count: number }>();
    for (const act of actividades) {
      if (act.unidad) {
        if (!map.has(act.unidad.id_unidad)) {
          map.set(act.unidad.id_unidad, {
            id: act.unidad.id_unidad,
            nombre: act.unidad.nombre,
            count: 0,
          });
        }
        map.get(act.unidad.id_unidad)!.count++;
      }
    }
    return [...map.values()];
  });

  const grouped = $derived.by(() => {
    const filtered = actividades.filter((act) => {
      if (activeUnit !== 'all' && act.unidad?.id_unidad !== activeUnit) return false;
      if (filterSumativa && act.componente?.tipo?.toLowerCase() !== 'sumativa') return false;
      if (
        filterConEntrega &&
        (!act.tipo_entrega || act.tipo_entrega.toLowerCase() === 'sin entrega')
      )
        return false;
      if (filterGrupal && !act.es_grupal) return false;
      return true;
    });

    const map = new Map<string, { unidad: Actividad['unidad']; acts: Actividad[] }>();
    for (const act of filtered) {
      const key = act.unidad ? String(act.unidad.id_unidad) : '__sin';
      if (!map.has(key)) map.set(key, { unidad: act.unidad ?? null, acts: [] });
      map.get(key)!.acts.push(act);
    }
    return [...map.values()];
  });

  const anyFilter = $derived(filterSumativa || filterConEntrega || filterGrupal);
  const totalShown = $derived(grouped.reduce((a, g) => a + g.acts.length, 0));

  function clearFilters() {
    filterSumativa = false;
    filterConEntrega = false;
    filterGrupal = false;
  }
</script>

<svelte:head>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=JetBrains+Mono:wght@400;500;700&display=swap"
    rel="stylesheet"
  />
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="pg">
    <!-- Heritage stripe -->
    <div class="heritage-stripe" aria-hidden="true">
      <span class="h-navy"></span>
      <span class="h-wine"></span>
      <span class="h-gold"></span>
      <span class="h-navy2"></span>
    </div>

    <!-- Shell -->
    <div class="shell">
      <!-- Sidebar -->
      <aside class="sidebar" aria-label="Navegación del curso">
        <Link href="/estudiante/cursos/{curso.id_curso}" class="side-back">
          <svg
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"><polyline points="15 6 9 12 15 18" /></svg
          >
          Volver a Mis Cursos
        </Link>

        <div class="side-course">
          <div class="side-course-eyebrow">{curso.cod_curso} · {curso.asignatura_nombre}</div>
          <h2 class="side-course-name">{curso.nombre}</h2>
        </div>

        <div class="side-section-label">Unidades</div>
        <div class="side-units">
          <button
            class="side-unit {activeUnit === 'all' ? 'active' : ''}"
            onclick={() => (activeUnit = 'all')}
          >
            <span class="side-unit-num">—</span>
            <span class="side-unit-text">Todas las actividades</span>
            <span class="side-unit-count">{actividades.length}</span>
          </button>
          {#each unidades as u, i}
            <button
              class="side-unit {activeUnit === u.id ? 'active' : ''}"
              onclick={() => (activeUnit = u.id)}
            >
              <span class="side-unit-num">U{String(i + 1).padStart(2, '0')}</span>
              <span class="side-unit-text">{u.nombre}</span>
              <span class="side-unit-count">{u.count}</span>
            </button>
          {/each}
        </div>

        <div class="side-divider"></div>

        <button class="side-action">
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.6"
            stroke-linecap="round"
            stroke-linejoin="round"
            ><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path
              d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"
            /></svg
          >
          <span>Ver Bibliografía</span>
          <svg
            width="14"
            height="14"
            class="side-action-arrow"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            ><line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" /></svg
          >
        </button>
        <div style="height:8px"></div>
        <button class="side-action">
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.6"
            stroke-linecap="round"
            stroke-linejoin="round"
            ><path d="M22 10 12 5 2 10l10 5 10-5Z" /><path
              d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"
            /></svg
          >
          <span>Programa del Curso</span>
          <svg
            width="14"
            height="14"
            class="side-action-arrow"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            ><line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" /></svg
          >
        </button>
      </aside>

      <!-- Main content -->
      <main class="main-col">
        <header class="listing-head">
          <div class="listing-eyebrow">Contenido del curso</div>
          <h1 class="listing-title">Actividades</h1>
          <div class="listing-sub">
            {totalShown}
            {totalShown === 1 ? 'actividad' : 'actividades'}
            {activeUnit !== 'all' ? ' · filtrando por unidad seleccionada' : ''}
          </div>
        </header>

        <!-- Filter toolbar -->
        <div class="listing-toolbar" role="toolbar" aria-label="Filtros">
          <span class="filter-label">Filtros</span>
          <button
            class="filter-chip {filterSumativa ? 'active' : ''}"
            onclick={() => (filterSumativa = !filterSumativa)}
            aria-pressed={filterSumativa}
          >
            Sumativas
            {#if filterSumativa}<span class="filter-chip-x" aria-hidden="true">×</span>{/if}
          </button>
          <button
            class="filter-chip {filterConEntrega ? 'active' : ''}"
            onclick={() => (filterConEntrega = !filterConEntrega)}
            aria-pressed={filterConEntrega}
          >
            Con Entrega
            {#if filterConEntrega}<span class="filter-chip-x" aria-hidden="true">×</span>{/if}
          </button>
          <button
            class="filter-chip {filterGrupal ? 'active' : ''}"
            onclick={() => (filterGrupal = !filterGrupal)}
            aria-pressed={filterGrupal}
          >
            Grupales
            {#if filterGrupal}<span class="filter-chip-x" aria-hidden="true">×</span>{/if}
          </button>
          {#if anyFilter}
            <button class="filter-clear" onclick={clearFilters}>Limpiar filtros</button>
          {/if}
        </div>

        <!-- Activity groups -->
        {#if grouped.length === 0}
          <div class="empty-state">
            <svg
              width="40"
              height="40"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.3"
              stroke-linecap="round"
              stroke-linejoin="round"
              style="opacity:.35;margin:0 auto 12px"
              ><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path
                d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"
              /></svg
            >
            <p>No hay actividades con los filtros aplicados.</p>
          </div>
        {:else}
          {#each grouped as group, gi}
            <section>
              <div class="unit-divider">
                <span class="unit-divider-num">UNIDAD {String(gi + 1).padStart(2, '0')}</span>
                <span class="unit-divider-title">{group.unidad?.nombre ?? 'Sin unidad'}</span>
                <span class="unit-divider-line" aria-hidden="true"></span>
              </div>

              {#each group.acts as act}
                {@const status = getStatus(act)}
                {@const nota = notaFinal(act)}
                {@const passed = nota !== null && nota >= 4.0}
                {@const urgent = isUrgent(act.fecha_limite)}
                <Link
                  href="/estudiante/actividades/{act.id_actividad}"
                  class="act-card {status === 'graded'
                    ? 'is-graded'
                    : status === 'pending'
                      ? 'is-pending'
                      : status === 'submitted'
                        ? 'is-submitted'
                        : 'is-not-due'}"
                >
                  <div class="act-num">
                    <span class="act-num-code">ACT-{String(act.id_actividad).padStart(2, '0')}</span
                    >
                  </div>

                  <div class="act-body">
                    <h3 class="act-title">{act.nombre}</h3>
                    <div class="act-meta-row">
                      {#if act.componente}
                        <span class="act-tag {act.componente.tipo.toLowerCase()}">
                          <span class="act-tag-dot" aria-hidden="true"></span>
                          {act.componente.tipo}
                        </span>
                      {/if}
                      <span class="act-tag">
                        {#if act.es_grupal}
                          <svg
                            width="11"
                            height="11"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            ><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle
                              cx="9"
                              cy="7"
                              r="4"
                            /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path
                              d="M16 3.13a4 4 0 0 1 0 7.75"
                            /></svg
                          >
                          Grupal{act.max_integrantes > 1 ? ` (${act.max_integrantes})` : ''}
                        {:else}
                          <svg
                            width="11"
                            height="11"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            ><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle
                              cx="12"
                              cy="7"
                              r="4"
                            /></svg
                          >
                          Individual
                        {/if}
                      </span>
                      {#if act.tipo_entrega && act.tipo_entrega.toLowerCase() !== 'sin entrega'}
                        <span class="act-tag obligatoria">Entrega Obligatoria</span>
                      {/if}
                    </div>
                    <div class="act-due {urgent ? 'act-due-urgent' : ''}">
                      <svg
                        width="13"
                        height="13"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        ><rect x="3" y="4" width="18" height="18" rx="2" /><line
                          x1="16"
                          y1="2"
                          x2="16"
                          y2="6"
                        /><line x1="8" y1="2" x2="8" y2="6" /><line
                          x1="3"
                          y1="10"
                          x2="21"
                          y2="10"
                        /></svg
                      >
                      <span>Fecha límite: <strong>{formatDate(act.fecha_limite)}</strong></span>
                      {#if urgent}<span>· ¡Próximo a vencer!</span>{/if}
                    </div>
                  </div>

                  <div class="act-status">
                    {#if status === 'pending'}
                      <span class="act-status-pill pending">Por Entregar</span>
                    {:else if status === 'submitted'}
                      <span class="act-status-pill submitted">Entregada</span>
                    {:else if status === 'notdue'}
                      <span class="act-status-pill notdue">Próximamente</span>
                    {:else if status === 'graded'}
                      <span class="act-grade-label {passed ? 'pass' : 'fail'}"
                        >{passed ? 'Aprobado' : 'Reprobado'}</span
                      >
                      <div class="act-grade">
                        <span class="act-grade-num {passed ? 'pass' : 'fail'}"
                          >{nota!.toFixed(1)}</span
                        >
                        <span class="act-grade-scale">/ 7.0</span>
                      </div>
                    {/if}
                  </div>

                  <span class="act-card-arrow" aria-hidden="true">
                    <svg
                      width="18"
                      height="18"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      ><line x1="5" y1="12" x2="19" y2="12" /><polyline
                        points="12 5 19 12 12 19"
                      /></svg
                    >
                  </span>
                </Link>
              {/each}
            </section>
          {/each}
        {/if}
      </main>
    </div>
  </div>
</StudentLayout>

<style>
  .pg {
    --bg: #ffffff;
    --surface: #ffffff;
    --surface-2: #f4f6fa;
    --surface-3: #eeeff3;
    --primary: #1b3158;
    --primary-2: #2a4576;
    --primary-3: #4865a1;
    --primary-soft: #e8edf5;
    --primary-tint: #f1f4fa;
    --accent: #7a1f2b;
    --accent-2: #9d2937;
    --accent-soft: #f4e6e8;
    --accent-tint: #faeef0;
    --gold: #b58a3c;
    --gold-soft: #f5ebd2;
    --gold-ink: #6e5217;
    --ink-1: #11141b;
    --ink-2: #2c3142;
    --ink-3: #5a6075;
    --ink-4: #8a8e9c;
    --ink-5: #b8bcc8;
    --line: #e2dbc8;
    --line-2: #cfc6ab;
    --success: #2c7a4b;
    --danger: #993244;
    --danger-soft: #fff;
    --info: #1f5ba8;
    --info-soft: #e4ecf8;
    --font-sans: 'Inter Tight', system-ui, sans-serif;
    --font-display: 'Fraunces', 'Inter Tight', Georgia, serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;
    --radius-lg: 14px;
    --shadow: 0 2px 6px rgba(20, 30, 55, 0.05), 0 8px 24px rgba(20, 30, 55, 0.06);

    font-family: var(--font-sans);
    background: var(--bg);
    min-height: 100vh;
    color: var(--ink-1);
    -webkit-font-smoothing: antialiased;
  }

  /* Heritage stripe */
  .heritage-stripe {
    display: flex;
    height: 4px;
    width: 100%;
  }
  .h-navy {
    flex: 6;
    background: var(--primary);
  }
  .h-wine {
    flex: 1.6;
    background: var(--accent);
  }
  .h-gold {
    flex: 1.2;
    background: var(--gold);
  }
  .h-navy2 {
    flex: 1;
    background: var(--primary);
  }

  /* Shell */
  .shell {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 28px 80px;
    display: grid;
    grid-template-columns: 264px minmax(0, 1fr);
    gap: 36px;
  }

  /* Sidebar */
  .sidebar {
    padding-top: 28px;
    position: sticky;
    top: 0;
    align-self: start;
    max-height: 100vh;
    overflow-y: auto;
  }

  :global(.side-back) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--ink-3);
    padding: 6px 10px 6px 4px;
    border-radius: 6px;
    margin-bottom: 18px;
    transition: color 0.15s;
    text-decoration: none;
    font-family: var(--font-sans);
  }
  :global(.side-back:hover) {
    color: var(--primary);
  }

  .side-course {
    border-left: 3px solid var(--accent);
    padding: 4px 0 4px 14px;
    margin-bottom: 24px;
  }
  .side-course-eyebrow {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .side-course-name {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 4px 0 0;
    color: var(--ink-1);
    line-height: 1.1;
  }

  .side-section-label {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    margin: 18px 0 10px;
    padding-left: 4px;
  }
  .side-units {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .side-unit {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--ink-2);
    text-align: left;
    width: 100%;
    transition:
      background 0.15s,
      color 0.15s;
    border: none;
    border-left: 2px solid transparent;
    background: transparent;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .side-unit:hover {
    background: var(--primary-tint);
    color: var(--primary);
  }
  .side-unit.active {
    background: var(--primary-tint);
    color: var(--primary);
    font-weight: 600;
    border-left-color: var(--primary);
  }
  .side-unit-num {
    font-family: var(--font-mono);
    font-size: 10.5px;
    color: var(--ink-4);
    margin-top: 2px;
    flex-shrink: 0;
    width: 26px;
  }
  .side-unit.active .side-unit-num {
    color: var(--primary-3);
  }
  .side-unit-text {
    flex: 1;
    line-height: 1.35;
  }
  .side-unit-count {
    font-family: var(--font-mono);
    font-size: 10.5px;
    color: var(--ink-4);
  }

  .side-divider {
    height: 1px;
    background: var(--line);
    margin: 18px 0;
  }

  .side-action {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--surface);
    border: 1px solid var(--line);
    font-size: 13px;
    color: var(--ink-2);
    font-weight: 500;
    transition: all 0.15s;
    text-align: left;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .side-action span {
    flex: 1;
  }
  .side-action:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-tint);
  }
  .side-action-arrow {
    margin-left: auto;
  }

  /* Main */
  .main-col {
    padding-top: 28px;
  }

  .listing-head {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 24px;
  }
  .listing-eyebrow {
    font-size: 11px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--accent);
    font-weight: 600;
  }
  .listing-title {
    font-family: var(--font-display);
    font-size: 36px;
    font-weight: 600;
    letter-spacing: -0.015em;
    line-height: 1.05;
    margin: 0;
    color: var(--ink-1);
  }
  .listing-sub {
    font-size: 13.5px;
    color: var(--ink-3);
    margin-top: 6px;
  }

  .listing-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    padding: 8px;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 12px;
    flex-wrap: wrap;
  }
  .filter-label {
    font-size: 11px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    padding: 0 8px 0 6px;
    border-right: 1px solid var(--line);
    margin-right: 4px;
  }
  .filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--ink-2);
    border: 1px solid transparent;
    background: transparent;
    transition: all 0.15s;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .filter-chip:hover {
    background: var(--primary-tint);
    color: var(--primary);
  }
  .filter-chip.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
  }
  .filter-chip-x {
    display: inline-grid;
    place-items: center;
    width: 14px;
    height: 14px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.18);
    font-size: 11px;
    line-height: 1;
  }
  .filter-clear {
    margin-left: auto;
    font-size: 12px;
    color: var(--ink-3);
    padding: 7px 12px;
    border-radius: 8px;
    transition:
      color 0.15s,
      background 0.15s;
    background: transparent;
    border: none;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .filter-clear:hover {
    color: var(--accent);
    background: var(--accent-tint);
  }

  .unit-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 28px 0 14px;
  }
  .unit-divider:first-child {
    margin-top: 0;
  }
  .unit-divider-num {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ink-4);
    letter-spacing: 0.08em;
    white-space: nowrap;
  }
  .unit-divider-title {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-2);
    letter-spacing: -0.005em;
    white-space: nowrap;
  }
  .unit-divider-line {
    flex: 1;
    height: 1px;
    background: var(--line);
  }

  /* Activity card — Link renders as <a> */
  :global(.act-card) {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
    margin-bottom: 14px;
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 20px;
    padding: 22px 24px;
    align-items: start;
    transition: all 0.15s;
    cursor: pointer;
    position: relative;
    text-align: left;
    width: 100%;
    text-decoration: none;
    color: inherit;
  }
  :global(.act-card:hover) {
    border-color: var(--primary-3);
    box-shadow: var(--shadow);
    transform: translateY(-1px);
  }
  :global(.act-card.is-graded) {
    border-left: 3px solid var(--accent);
    padding-left: 22px;
  }
  :global(.act-card.is-pending) {
    border-left: 3px solid var(--gold);
    padding-left: 22px;
  }
  :global(.act-card.is-submitted) {
    border-left: 3px solid var(--info);
    padding-left: 22px;
  }
  :global(.act-card.is-not-due) {
    border-left: 3px solid var(--line-2);
    padding-left: 22px;
  }

  .act-num {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
  }
  .act-num-code {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ink-4);
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .act-body {
    min-width: 0;
  }
  .act-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 0 0 8px;
    color: var(--ink-1);
    line-height: 1.2;
  }
  .act-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 10px;
    align-items: center;
  }
  .act-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 500;
    padding: 4px 9px;
    border-radius: 999px;
    color: var(--ink-2);
    background: var(--surface-3);
    border: 1px solid var(--line);
  }
  .act-tag.sumativa {
    background: var(--accent-tint);
    color: var(--accent);
    border-color: var(--accent-soft);
  }
  .act-tag.formativa {
    background: var(--primary-tint);
    color: var(--primary);
    border-color: var(--primary-soft);
  }
  .act-tag.obligatoria {
    background: var(--gold-soft);
    color: var(--gold-ink);
    border-color: #ead79a;
  }
  .act-tag-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
  }

  .act-due {
    font-size: 12px;
    color: var(--ink-3);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
  }
  .act-due strong {
    color: var(--ink-2);
    font-weight: 600;
  }
  .act-due-urgent {
    color: var(--accent);
  }
  .act-due-urgent strong {
    color: var(--accent);
  }

  .act-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    min-width: 130px;
  }
  .act-status-pill {
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
  }
  .act-status-pill.pending {
    background: var(--gold-soft);
    color: var(--gold-ink);
  }
  .act-status-pill.submitted {
    background: var(--info-soft);
    color: var(--info);
  }
  .act-status-pill.notdue {
    background: transparent;
    color: var(--ink-4);
    border: 1px solid var(--line);
  }

  .act-grade {
    display: flex;
    align-items: baseline;
    gap: 4px;
    font-family: var(--font-display);
  }
  .act-grade-num {
    font-size: 28px;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1;
  }
  .act-grade-num.pass {
    color: var(--success);
  }
  .act-grade-num.fail {
    color: var(--danger);
  }
  .act-grade-scale {
    font-size: 13px;
    color: var(--ink-4);
    font-family: var(--font-sans);
    font-weight: 400;
  }
  .act-grade-label {
    font-size: 10.5px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    font-weight: 700;
  }
  .act-grade-label.fail {
    color: var(--danger);
  }
  .act-grade-label.pass {
    color: var(--success);
  }

  .act-card-arrow {
    position: absolute;
    right: 24px;
    bottom: 22px;
    color: var(--ink-4);
    opacity: 0;
    transition:
      opacity 0.15s,
      transform 0.15s;
  }
  :global(.act-card:hover) .act-card-arrow {
    opacity: 1;
    transform: translateX(2px);
  }

  .empty-state {
    padding: 60px 24px;
    text-align: center;
    color: var(--ink-3);
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
    font-size: 14px;
  }

  @media (max-width: 820px) {
    .shell {
      grid-template-columns: 1fr;
      padding: 0 16px 60px;
    }
    .sidebar {
      position: static;
      max-height: none;
      padding-top: 16px;
    }
  }
  @media (max-width: 1100px) {
    .shell {
      grid-template-columns: 232px minmax(0, 1fr);
      gap: 24px;
    }
  }
</style>
