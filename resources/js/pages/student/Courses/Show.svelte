<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import { ArrowLeft, PlayCircle, FileText, Bookmark, Share2, ScrollText } from 'lucide-svelte';
  import CourseSidebar from '@/components/student/CourseSidebar.svelte';
  import ResourceCard from '@/components/student/ResourceCard.svelte';
  import ActividadesView from '../Activities/ActividadesView.svelte';
  import BibsIndex from './Bibs/Index.svelte';
  import Syllabus from './Syllabus.svelte';

  interface Actividad {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    con_entrega: boolean;
    es_grupal: boolean;
    max_integrantes: number;
    fecha_limite: string;
    visible: boolean;
  }

  interface Props {
    curso?: Curso;
    actividades?: Actividad[];
  }

  let { curso, actividades = [] }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  let activeModuleId = $state('module-1-2');
  let activeView = $state<'principal' | 'bibliografias'>('principal');

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.nombre ?? 'Curso', href: '' },
  ]);

  // Estados de los filtros
  let filterSumativa = $state(false);
  let filterEntrega = $state(false);
  let filterGrupal = $state(false);

  // Actividades de ejemplo
  const actividadesEjemplo: Actividad[] = [
    {
      id_actividad: 1001,
      nombre: 'Taller de diseño inicial',
      es_sumativa: true,
      con_entrega: true,
      es_grupal: false,
      max_integrantes: 1,
      fecha_limite: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      visible: true,
    },
    {
      id_actividad: 1002,
      nombre: 'Proyecto grupal - Prototipo interactivo',
      es_sumativa: true,
      con_entrega: true,
      es_grupal: true,
      max_integrantes: 4,
      fecha_limite: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      visible: true,
    },
  ];

  const actividadesBase = $derived(actividades.length > 0 ? actividades : actividadesEjemplo);

  const actividadesFiltradas = $derived(
    actividadesBase.filter((actividad) => {
      const cumpleSumativa = !filterSumativa || actividad.es_sumativa;
      const cumpleEntrega = !filterEntrega || actividad.con_entrega;
      const cumpleGrupal = !filterGrupal || actividad.es_grupal;

      return cumpleSumativa && cumpleEntrega && cumpleGrupal;
    }),
  );

  function clearFilters() {
    filterSumativa = false;
    filterEntrega = false;
    filterGrupal = false;
  }

  function toggleFilter(type: string) {
    if (type === 'sumativa') {
      filterSumativa = !filterSumativa;
    } else if (type === 'entrega') {
      filterEntrega = !filterEntrega;
    } else if (type === 'grupal') {
      filterGrupal = !filterGrupal;
    }
  }

  let showSyllabus = $state(false);
  let showActividades = $state(true);
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

    <!-- Content Views -->
    <div class="w-full md:flex-1">
      {#if activeView === 'principal'}
        <div class="space-y-4 px-2">
          <button
            class="px-4 py-2 rounded bg-primary text-secondary hover:bg-secondary hover:text-primary font-semibold focus:outline-none"
            onclick={() => (showSyllabus = !showSyllabus)}
            aria-expanded={showSyllabus}
            aria-controls="syllabus-section"
          >
            {showSyllabus ? '▼' : '►'} Acerca de este curso
          </button>
          {#if showSyllabus}
            <div id="syllabus-section" class="pl-4 border-gray-200 mb-2">
              <Syllabus {curso} />
            </div>
          {/if}

          {#if showActividades}
            <div id="actividades-section" class="pl-4 border-gray-200">
              <ActividadesView
                {activeModuleId}
                {id_curso}
                {filterSumativa}
                {filterEntrega}
                {filterGrupal}
                filtered={actividadesFiltradas}
                onToggleFilter={toggleFilter}
                onClearFilters={clearFilters}
              />
            </div>
          {/if}
        </div>
      {:else if activeView === 'bibliografias'}
        <BibsIndex {id_curso} />
      {/if}
    </div>
  </div>
</StudentLayout>

<style>
  .pg {
    --bg: #ffffff;
    --surface: #ffffff;
    --surface-2: #f4f6fa;
    --surface-3: #eeeff3;
    --primary: #1e4e8c;
    --primary-2: #2a66ac;
    --primary-3: #4a84c4;
    --primary-soft: #d0e3f5;
    --primary-tint: #eef4fb;
    --accent: #0e6494;
    --accent-2: #1a80bc;
    --accent-soft: #d0eaf8;
    --accent-tint: #eaf5fc;
    --gold: #3d78b4;
    --gold-soft: #daeaf8;
    --gold-ink: #194f82;
    --ink-1: #11141b;
    --ink-2: #2c3142;
    --ink-3: #5a6075;
    --ink-4: #8a8e9c;
    --ink-5: #b8bcc8;
    --line: #d5e2f0;
    --line-2: #b8cfe8;
    --success: #2c7a4b;
    --danger: #993244;
    --danger-soft: #f5e1e4;
    --info: #1f5ba8;
    --info-soft: #e4ecf8;

    --radius-lg: 14px;
    --shadow: 0 2px 6px rgba(20, 30, 55, 0.05), 0 8px 24px rgba(20, 30, 55, 0.06);

    font-family: var(--font-sans);
    background: var(--bg);
    min-height: 100vh;
    color: var(--ink-1);
    -webkit-font-smoothing: antialiased;
  }

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

  .shell {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 28px 80px;
    display: grid;
    grid-template-columns: 264px minmax(0, 1fr);
    gap: 36px;
  }

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

  :global(.side-action-link) {
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
    text-decoration: none;
  }
  :global(.side-action-link:hover) {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-tint);
  }
  :global(.side-action-link span) {
    flex: 1;
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
  .side-course-meta {
    font-size: 12px;
    color: var(--ink-3);
    margin-top: 6px;
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
    border-color: #9dc3e8;
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
