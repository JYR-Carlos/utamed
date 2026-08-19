<script lang="ts">
  /**
   * rosterTable — Paso 2 del flujo de inscripciones: nómina del curso con
   * cambio de estado por fila (menú contextual limitado a TRANSITIONS) y
   * eliminación definitiva.
   *
   * Presentacional: el padre carga el roster y ejecuta los cambios; las
   * filas con _saving muestran spinner mientras se aplica el PATCH.
   */
  import type { CursoItem, RosterItem, EstadoInscripcion } from '../types/inscripcion.types';
  import {
    TRANSITIONS,
    ESTADO_CFG,
    studentName,
    studentUsername,
    initials,
    cursoDisplayName,
  } from '../types/inscripcion.types';

  interface Props {
    roster: RosterItem[];
    loadingRoster: boolean;
    /** Mensaje de error de carga; muestra el estado con botón reintentar. */
    rosterError: string;
    activeCursoId: number;
    selectedCurso: CursoItem | null;
    /** Vuelve al selector de cursos. */
    onBack: () => void;
    onRetry: () => void;
    /** Abre el modal de inscripción masiva. */
    onAddStudents: () => void;
    /** Aplica una transición de estado permitida por TRANSITIONS. */
    onChangeEstado: (item: RosterItem, next: EstadoInscripcion) => void;
    onDelete: (item: RosterItem) => void;
  }

  let {
    roster,
    loadingRoster,
    rosterError,
    activeCursoId,
    selectedCurso,
    onBack,
    onRetry,
    onAddStudents,
    onChangeEstado,
    onDelete,
  }: Props = $props();

  let openMenuId = $state<number | null>(null);
  let menuPos = $state({ x: 0, y: 0 });

  const inscritoCount = $derived(
    roster.filter(
      (r) => r.estado_inscripcion === 'INSCRITO' || r.estado_inscripcion === 'APROBADO',
    ).length,
  );
  const totalCount = $derived(roster.filter((r) => r.estado_inscripcion !== 'ANULADO').length);

  const AVATAR_PALETTE = [
    'bg-blue-100 text-blue-700',
    'bg-violet-100 text-violet-700',
    'bg-emerald-100 text-emerald-700',
    'bg-amber-100 text-amber-700',
    'bg-rose-100 text-rose-700',
    'bg-cyan-100 text-cyan-700',
    'bg-indigo-100 text-indigo-700',
    'bg-orange-100 text-orange-700',
  ];

  function avatarCls(id: number): string {
    return AVATAR_PALETTE[id % AVATAR_PALETTE.length];
  }

  function openMenu(e: MouseEvent) {
    const btn = e.currentTarget as HTMLButtonElement;
    const inscripcionId = parseInt(btn.dataset.inscripcionId || '0');

    if (openMenuId === inscripcionId) {
      openMenuId = null;
      return;
    }

    const rect = btn.getBoundingClientRect();
    menuPos = { x: rect.left, y: rect.bottom + 6 };
    openMenuId = inscripcionId;
  }
</script>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape') openMenuId = null;
  }}
/>

<div class="max-w-[1100px] space-y-6">
  <!-- Breadcrumb -->
  <nav>
    <button
      onclick={onBack}
      class="group inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-700 transition-colors"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="15"
        height="15"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="group-hover:-translate-x-0.5 transition-transform duration-150"
      >
        <path d="m15 18-6-6 6-6" />
      </svg>
      Todos los cursos
    </button>
  </nav>

  <!-- Page header -->
  <div class="flex justify-between items-start flex-wrap gap-4">
    <div class="min-w-0">
      <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
        {selectedCurso ? cursoDisplayName(selectedCurso) : `Curso ${activeCursoId}`}
      </h1>
      {#if selectedCurso}
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-2">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 text-blue-600 text-xs font-mono font-bold ring-1 ring-inset ring-blue-100"
          >
            {selectedCurso.cod_curso}
          </span>
          {#if selectedCurso.asignatura_nombre}
            <span class="text-xs text-gray-500">{selectedCurso.asignatura_nombre}</span>
          {/if}
          {#if selectedCurso.carrera_nombre}
            <span class="text-gray-200 text-xs select-none">·</span>
            <span class="text-xs text-gray-400">{selectedCurso.carrera_nombre}</span>
          {/if}
          {#if selectedCurso.agno_real}
            <span class="text-gray-200 text-xs select-none">·</span>
            <span class="text-xs text-gray-400 tabular-nums"
              >{selectedCurso.agno_real} S{selectedCurso.semestre_real}</span
            >
          {/if}
        </div>
      {/if}
    </div>

    <!-- Stats + actions row -->
    <div class="flex items-center gap-3 shrink-0 flex-wrap">
      <!-- Inscritos counter -->
      <div
        class="flex items-center gap-2.5 bg-white border border-gray-200 rounded-xl px-3.5 py-2 shadow-xs"
      >
        <div class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></div>
        <div class="flex items-baseline gap-0.5">
          <span class="text-lg font-extrabold text-gray-800 tabular-nums">{inscritoCount}</span>
          <span class="text-xs text-gray-300 mx-0.5">/</span>
          <span class="text-sm font-semibold text-gray-400 tabular-nums">{totalCount}</span>
        </div>
        <span class="text-xs text-gray-400">activos</span>
      </div>

      <a
        href="/admin/inscripciones_cursos/export/csv?id_curso={activeCursoId}"
        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white text-gray-600 border border-gray-200 rounded-xl text-sm font-medium hover:bg-gray-50 hover:border-gray-300 transition-all no-underline shadow-xs"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
        </svg>
        CSV
      </a>

      <button
        onclick={onAddStudents}
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 active:bg-blue-800 transition-colors"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="15"
          height="15"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Agregar Estudiantes
      </button>
    </div>
  </div>

  <!-- Content states -->
  {#if loadingRoster}
    <div class="flex items-center justify-center gap-3 py-20 text-gray-400">
      <div class="w-5 h-5 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin"
      ></div>
      <span class="text-sm">Cargando nómina…</span>
    </div>
  {:else if rosterError}
    <div class="flex flex-col items-center gap-4 py-16 text-center">
      <div
        class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-400"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line
            x1="12"
            y1="16"
            x2="12.01"
            y2="16"
          />
        </svg>
      </div>
      <p class="text-red-500 text-sm">{rosterError}</p>
      <button
        onclick={onRetry}
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-xl text-sm font-medium hover:bg-gray-50 hover:border-gray-300 transition-all"
      >
        Reintentar
      </button>
    </div>
  {:else if roster.length === 0}
    <div class="flex flex-col items-center gap-4 py-20 text-center">
      <div
        class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-300"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="22"
          height="22"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
      </div>
      <div>
        <p class="text-gray-600 font-medium text-sm">Sin estudiantes inscritos</p>
        <p class="text-gray-400 text-sm mt-0.5">Agrega el primer estudiante a este curso.</p>
      </div>
      <button
        onclick={onAddStudents}
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition-colors"
      >
        + Agregar Estudiantes
      </button>
    </div>
  {:else}
    <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-gray-50/80 border-b border-gray-200">
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-semibold text-gray-400 uppercase tracking-wider"
              >Estudiante</th
            >
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-semibold text-gray-400 uppercase tracking-wider"
              >Usuario</th
            >
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-semibold text-gray-400 uppercase tracking-wider"
              >Inscripción</th
            >
            <th
              class="px-4 py-3 text-center text-[0.6875rem] font-semibold text-gray-400 uppercase tracking-wider"
              >Intento</th
            >
            <th
              class="px-4 py-3 text-center text-[0.6875rem] font-semibold text-gray-400 uppercase tracking-wider"
              >Promedio</th
            >
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-semibold text-gray-400 uppercase tracking-wider"
              >Estado</th
            >
            <th class="w-12 px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          {#each roster as item (item.id_inscripcion_curso)}
            {@const cfg = ESTADO_CFG[item.estado_inscripcion] ?? ESTADO_CFG['INSCRITO']}
            {@const transitions = TRANSITIONS[item.estado_inscripcion] ?? []}
            {@const isVoided = cfg.rowCls === 'voided'}
            {@const isDimmed = cfg.rowCls === 'dimmed'}
            <tr
              class="group hover:bg-gray-50/60 transition-colors {isVoided ? 'opacity-40' : ''}"
              class:opacity-60={item._saving}
            >
              <td class="px-4 py-3 align-middle">
                <div class="flex items-center gap-3 min-w-[170px]">
                  <div
                    class="w-8 h-8 rounded-full text-[0.6875rem] font-bold flex items-center justify-center shrink-0 {avatarCls(item.id_estudiante)}"
                  >
                    {initials(item)}
                  </div>
                  <span
                    class="font-medium text-gray-900 {isVoided ? 'line-through text-gray-400' : ''} {isDimmed ? 'text-gray-500' : ''}"
                  >
                    {studentName(item)}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 align-middle">
                <span
                  class="font-mono text-xs text-gray-500 bg-gray-50 px-2 py-0.5 rounded-md ring-1 ring-gray-200"
                >
                  {studentUsername(item)}
                </span>
              </td>
              <td class="px-4 py-3 align-middle whitespace-nowrap text-sm text-gray-400">
                {item.fecha_inscripcion ?? '—'}
              </td>
              <td class="px-4 py-3 align-middle text-center">
                <span class="text-sm font-medium text-gray-600 tabular-nums">{item.num_intento}</span>
              </td>
              <td class="px-4 py-3 align-middle text-center">
                {#if item.promedio_parcial != null}
                  <span class="text-sm font-semibold text-gray-700 tabular-nums"
                    >{item.promedio_parcial}</span
                  >
                {:else}
                  <span class="text-gray-300 text-sm">—</span>
                {/if}
              </td>
              <td class="px-4 py-3 align-middle min-w-[140px]">
                <!-- Píldora con borde y flecha: sin el borde parecía una
                     insignia de estado y nada indicaba que abriera un menú
                     para cambiarlo. -->
                {#if transitions.length > 0}
                  <button
                    type="button"
                    title="Cambiar el estado de esta inscripción"
                    aria-label="Cambiar el estado de esta inscripción"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold cursor-pointer whitespace-nowrap border border-current/25 hover:brightness-95 disabled:cursor-wait {cfg.cls}"
                    class:animate-pulse={item._saving}
                    onclick={openMenu}
                    data-inscripcion-id={item.id_inscripcion_curso}
                    disabled={item._saving}
                  >
                    {cfg.label}
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="10"
                      height="10"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="3"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <polyline points="6 9 12 15 18 9" />
                    </svg>
                  </button>
                {:else}
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap cursor-default {cfg.cls}"
                  >
                    {cfg.label}
                  </span>
                {/if}
              </td>
              <td class="px-4 py-3 align-middle text-center">
                <button
                  type="button"
                  class="p-1.5 text-gray-300 bg-transparent border-0 cursor-pointer rounded-lg inline-flex items-center opacity-0 group-hover:opacity-100 hover:bg-red-50 hover:text-red-400 transition-all"
                  onclick={() => onDelete(item)}
                  title="Eliminar inscripción"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="3 6 5 6 21 6" />
                    <path
                      d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                    />
                  </svg>
                </button>
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>
  {/if}
</div>

{#if openMenuId !== null}
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div class="fixed inset-0 z-30" onclick={() => (openMenuId = null)} role="presentation"></div>

  {@const firstItem = roster.find((r) => r.id_inscripcion_curso === openMenuId)}
  {#if firstItem}
    {@const transitions = TRANSITIONS[firstItem.estado_inscripcion] ?? []}
    {#if transitions.length > 0}
      <div
        class="fixed z-40 bg-white border border-gray-200 rounded-xl shadow-xl min-w-[195px] overflow-hidden py-1"
        style="left: {menuPos.x}px; top: {menuPos.y}px;"
      >
        {#each transitions as t}
          <button
            type="button"
            class="flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-gray-700 bg-white border-0 cursor-pointer text-left hover:bg-gray-50 transition-colors"
            onclick={() => {
              openMenuId = null;
              onChangeEstado(firstItem, t.next);
            }}
          >
            <span class="text-base leading-none">{t.icon}</span>
            {t.label}
          </button>
        {/each}
      </div>
    {/if}
  {/if}
{/if}
