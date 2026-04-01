<script lang="ts">
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
    rosterError: string;
    activeCursoId: number;
    selectedCurso: CursoItem | null;
    onBack: () => void;
    onRetry: () => void;
    onAddStudents: () => void;
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

  const inscritoCount = $derived(roster.filter((r) => r.estado_inscripcion === 'INSCRITO').length);
  const totalCount = $derived(roster.filter((r) => r.estado_inscripcion !== 'ANULADO').length);

  function openMenu(e: MouseEvent) {
    const btn = e.currentTarget as HTMLButtonElement;
    const inscripcionId = parseInt(btn.dataset.inscripcionId || '0');

    // Toggle: si ya está abierto, cerrarlo
    if (openMenuId === inscripcionId) {
      openMenuId = null;
      return;
    }

    const rect = btn.getBoundingClientRect();
    menuPos = {
      x: rect.left,
      y: rect.bottom + 6,
    };
    openMenuId = inscripcionId;
  }
</script>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape') openMenuId = null;
  }}
/>

<div class="max-w-[1100px]">
  <nav class="mb-4">
    <button
      onclick={onBack}
      class="inline-flex items-center gap-1.5 text-[0.8125rem] text-gray-500 bg-transparent border-0 cursor-pointer p-0 hover:text-gray-900 transition-colors"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg
      >
      Todos los Cursos
    </button>
  </nav>

  <div class="flex justify-between items-start flex-wrap gap-4 mb-6">
    <div class="flex items-start gap-5 flex-wrap">
      <div>
        <h1 class="text-[1.625rem] font-bold text-gray-900 mb-0.5 mt-0">
          {selectedCurso ? cursoDisplayName(selectedCurso) : `Curso ${activeCursoId}`}
        </h1>
        {#if selectedCurso}
          <p class="text-[0.8125rem] text-gray-500 m-0">
            {selectedCurso.cod_curso}
            {#if selectedCurso.asignatura_nombre}&nbsp;·&nbsp;{selectedCurso.asignatura_nombre}{/if}
            {#if selectedCurso.carrera_nombre}&nbsp;·&nbsp;{selectedCurso.carrera_nombre}{/if}
            {#if selectedCurso.agno_real}&nbsp;·&nbsp;{selectedCurso.agno_real} S{selectedCurso.semestre_real}{/if}
          </p>
        {/if}
      </div>
      <div
        class="flex flex-col items-center bg-green-50 border-[1.5px] border-green-200 rounded-[10px] px-3.5 py-2"
      >
        <div class="flex items-baseline gap-0.5">
          <span class="text-xl font-extrabold text-green-600">{inscritoCount}</span>
          <span class="text-sm text-gray-400">/</span>
          <span class="text-base font-semibold text-gray-700">{totalCount}</span>
        </div>
        <span class="text-[0.6875rem] text-gray-500 mt-0.5">Inscritos activos</span>
      </div>
    </div>
    <div class="flex items-center gap-2.5 shrink-0">
      <a
        href="/admin/inscripciones_cursos/export/csv?id_curso={activeCursoId}"
        class="inline-flex items-center gap-1.5 px-4 py-[0.55rem] bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium cursor-pointer no-underline hover:bg-gray-50 hover:border-gray-400 transition-all"
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
        >
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
        </svg>
        Exportar CSV
      </a>
      <button
        onclick={onAddStudents}
        class="inline-flex items-center gap-1.5 px-[1.125rem] py-[0.55rem] bg-gradient-to-br from-blue-500 to-blue-600 text-white border-0 rounded-lg text-sm font-medium cursor-pointer transition-all shadow-sm hover:opacity-90"
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
        >
          <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Agregar Estudiantes
      </button>
    </div>
  </div>

  {#if loadingRoster}
    <div class="flex items-center justify-center gap-3 py-12 text-gray-500">
      <div
        class="w-5 h-5 border-[2.5px] border-gray-200 border-t-blue-500 rounded-full animate-spin"
      ></div>
      <span>Cargando roster…</span>
    </div>
  {:else if rosterError}
    <div class="flex flex-col items-center gap-4 py-16 text-center">
      <p class="text-red-600 text-sm">{rosterError}</p>
      <button
        onclick={onRetry}
        class="inline-flex items-center gap-1.5 px-4 py-[0.55rem] bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium cursor-pointer hover:bg-gray-50 hover:border-gray-400 transition-all"
        >Reintentar</button
      >
    </div>
  {:else if roster.length === 0}
    <div class="flex flex-col items-center gap-4 py-16 text-center">
      <p class="text-gray-500 text-[0.9375rem]">Este curso no tiene estudiantes inscritos aún.</p>
      <button
        onclick={onAddStudents}
        class="inline-flex items-center gap-1.5 px-[1.125rem] py-[0.55rem] bg-gradient-to-br from-blue-500 to-blue-600 text-white border-0 rounded-lg text-sm font-medium cursor-pointer transition-all shadow-sm hover:opacity-90"
        >+ Agregar Estudiantes</button
      >
    </div>
  {:else}
    <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-sm">
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50">
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
              >Estudiante</th
            >
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
              >Usuario</th
            >
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
              >Fecha Inscripción</th
            >
            <th
              class="px-4 py-3 text-center text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
              >Intentos</th
            >
            <th
              class="px-4 py-3 text-center text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
              >Promedio</th
            >
            <th
              class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
              >Estado</th
            >
            <th class="px-4 py-3 border-b border-gray-200"></th>
          </tr>
        </thead>
        <tbody>
          {#each roster as item, idx (item.id_inscripcion_curso)}
            {@const cfg = ESTADO_CFG[item.estado_inscripcion] ?? ESTADO_CFG['INSCRITO']}
            {@const transitions = TRANSITIONS[item.estado_inscripcion] ?? []}
            {@const dimCls = cfg.rowCls === 'dimmed' ? 'opacity-60' : ''}
            {@const voidCls = cfg.rowCls === 'voided' ? 'opacity-40 line-through' : ''}
            <tr
              class={idx < roster.length - 1 ? 'border-b border-slate-100' : ''}
              class:opacity-60={item._saving}
            >
              <td
                class="px-4 py-3 align-middle flex items-center gap-2.5 min-w-[160px] {dimCls} {voidCls}"
              >
                <div
                  class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-[0.6875rem] font-bold flex items-center justify-center shrink-0"
                >
                  {initials(item)}
                </div>
                <span class="font-medium text-gray-900">{studentName(item)}</span>
              </td>
              <td
                class="px-4 py-3 align-middle font-mono text-[0.8125rem] text-gray-500 {dimCls} {voidCls}"
                >{studentUsername(item)}</td
              >
              <td class="px-4 py-3 align-middle whitespace-nowrap text-gray-500 {dimCls} {voidCls}"
                >{item.fecha_inscripcion ?? '—'}</td
              >
              <td class="px-4 py-3 align-middle text-center text-gray-700 {dimCls}"
                >{item.num_intento}</td
              >
              <td class="px-4 py-3 align-middle text-center text-gray-700 {dimCls}"
                >{item.promedio_parcial != null ? item.promedio_parcial : '—'}</td
              >
              <td class="px-4 py-3 align-middle min-w-[140px]">
                {#if transitions.length > 0}
                  <div class="relative inline-block">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border-0 cursor-pointer transition-opacity whitespace-nowrap disabled:cursor-wait {cfg.cls}"
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
                  </div>
                {:else}
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap cursor-default {cfg.cls}"
                    >{cfg.label}</span
                  >
                {/if}
              </td>
              <td class="px-4 py-3 align-middle w-10 text-center">
                <button
                  type="button"
                  class="p-1.5 text-gray-300 bg-transparent border-0 cursor-pointer rounded inline-flex items-center hover:bg-red-50 hover:text-red-500 transition-all"
                  onclick={() => onDelete(item)}
                  title="Eliminar permanentemente"
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
  <!-- Overlay clickeable para cerrar menú -->
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div class="fixed inset-0 z-30" onclick={() => (openMenuId = null)} role="presentation"></div>

  <!-- Menú desplegable en posición fixed -->
  {@const firstItem = roster.find((r) => r.id_inscripcion_curso === openMenuId)}
  {#if firstItem}
    {@const cfg = ESTADO_CFG[firstItem.estado_inscripcion] ?? ESTADO_CFG['INSCRITO']}
    {@const transitions = TRANSITIONS[firstItem.estado_inscripcion] ?? []}
    {#if transitions.length > 0}
      <div
        class="fixed z-40 bg-white border border-gray-200 rounded-lg shadow-[0_8px_24px_rgba(0,0,0,0.16)] min-w-[185px] overflow-hidden"
        style="left: {menuPos.x}px; top: {menuPos.y}px;"
      >
        {#each transitions as t}
          <button
            type="button"
            class="flex items-center gap-2 w-full px-3.5 py-2 text-[0.8125rem] text-gray-700 bg-white border-0 cursor-pointer text-left hover:bg-gray-100 transition-colors"
            onclick={() => {
              openMenuId = null;
              onChangeEstado(firstItem, t.next);
            }}
          >
            <span class="text-sm">{t.icon}</span>{t.label}
          </button>
        {/each}
      </div>
    {/if}
  {/if}
{/if}
