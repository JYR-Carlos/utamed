<script lang="ts">
  /**
   * SyllabusPermisosModal
   * Modal para el Docente Titular del curso.
   * Muestra una matriz de docentes × permisos de syllabus y permite
   * otorgar o revocar cada permiso con un toggle.
   */
  import {
    getSyllabusPermisos,
    syncSyllabusPermiso,
    type DocenteConPermisos,
    type PermisoSlug,
  } from '../services/permisosApi';

  interface Props {
    isOpen: boolean;
    onClose: () => void;
    cursoId: number;
    cursoNombre?: string;
    basePath?: string;
  }

  let {
    isOpen = $bindable(),
    onClose,
    cursoId,
    cursoNombre = '',
    basePath = '/docente',
  }: Props = $props();

  // ─── Estado ─────────────────────────────────────────────────────────────
  let docentes = $state<DocenteConPermisos[]>([]);
  let slugs = $state<PermisoSlug[]>([]);
  let loading = $state(false);
  let error = $state<string | null>(null);
  /** { `${id_usuario}-${id_permiso}`: 'saving' | 'ok' | 'error' } */
  let cellState = $state<Record<string, 'saving' | 'ok' | 'error'>>({});

  // Etiquetas legibles para los módulos del syllabus
  const LABELS: Record<string, string> = {
    'cursos/programas:agregar': 'Crear programa',
    'cursos/programas/modificar:modulo_1': 'Sección I',
    'cursos/programas/modificar:modulo_2': 'Sección II',
    'cursos/programas/modificar:modulo_3': 'Sección III',
    'cursos/programas/modificar:modulo_4': 'Sección IV',
    'cursos/programas/modificar:modulo_5': 'Sección V',
    'cursos/programas/modificar:modulo_6': 'Sección VI',
    'cursos/programas/modificar:modulo_7': 'Sección VII',
    'cursos/programas/modificar:modulo_8': 'Sección VIII',
    'cursos/programas/modificar:modulo_9': 'Sección IX',
  };

  function label(slug: string) {
    return LABELS[slug] ?? slug;
  }

  // ─── Carga de datos ──────────────────────────────────────────────────────
  $effect(() => {
    if (isOpen && cursoId) {
      loadData();
    }
  });

  async function loadData() {
    loading = true;
    error = null;
    try {
      const res = await getSyllabusPermisos(cursoId, basePath);
      docentes = res.docentes;
      slugs = res.slugs_disponibles;
    } catch (e: any) {
      error = e.message ?? 'Error al cargar permisos';
    } finally {
      loading = false;
    }
  }

  // ─── Toggle ──────────────────────────────────────────────────────────────
  async function toggle(docente: DocenteConPermisos, permiso: PermisoSlug) {
    const key = `${docente.id_usuario}-${permiso.id_permiso}`;
    const actual = docente.permisos[permiso.id_permiso] ?? false;

    // Optimistic update
    docente.permisos[permiso.id_permiso] = !actual;
    docentes = [...docentes]; // trigger reactivity
    cellState = { ...cellState, [key]: 'saving' };

    try {
      await syncSyllabusPermiso(
        cursoId,
        { id_usuario: docente.id_usuario, slug: permiso.slug, otorgar: !actual },
        basePath,
      );
      cellState = { ...cellState, [key]: 'ok' };
      setTimeout(() => {
        cellState = { ...cellState, [key]: undefined as any };
      }, 1200);
    } catch (e: any) {
      // Revert on error
      docente.permisos[permiso.id_permiso] = actual;
      docentes = [...docentes];
      cellState = { ...cellState, [key]: 'error' };
      setTimeout(() => {
        cellState = { ...cellState, [key]: undefined as any };
      }, 2500);
    }
  }
</script>

{#if isOpen}
  <!-- Backdrop -->
  <button
    class="fixed inset-0 bg-black/40 z-40 cursor-default"
    onclick={onClose}
    aria-label="Cerrar"
  ></button>

  <!-- Panel -->
  <div
    class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-50
           bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh]
           flex flex-col overflow-hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="syllabus-permisos-title"
  >
    <!-- Header -->
    <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
      <div>
        <h2 id="syllabus-permisos-title" class="text-lg font-bold text-gray-900">
          Permisos de Programa / Syllabus
        </h2>
        {#if cursoNombre}
          <p class="text-sm text-gray-500 mt-0.5">{cursoNombre}</p>
        {/if}
        <p class="text-xs text-gray-400 mt-1">
          Configura qué docentes pueden editar cada sección del programa de este curso.
        </p>
      </div>
      <button
        onclick={onClose}
        class="ml-4 p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
        aria-label="Cerrar"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
          stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>

    <!-- Body -->
    <div class="flex-1 overflow-auto p-6">
      {#if loading}
        <div class="flex items-center justify-center py-16 text-gray-400">
          <svg class="animate-spin w-6 h-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
          </svg>
          Cargando permisos...
        </div>

      {:else if error}
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
          {error}
        </div>

      {:else if docentes.length === 0}
        <div class="text-center py-16 text-gray-400 text-sm">
          No hay otros docentes asignados a este curso.
        </div>

      {:else}
        <!-- Matriz de permisos -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm border-separate border-spacing-0">
            <thead>
              <tr>
                <th class="sticky left-0 bg-white text-left font-semibold text-gray-700 px-3 py-2
                           border-b border-r border-gray-200 min-w-[160px]">
                  Docente
                </th>
                {#each slugs as permiso}
                  <th class="text-center font-medium text-gray-600 px-2 py-2 border-b border-gray-200
                             min-w-[90px] text-xs leading-tight whitespace-pre-line">
                    {label(permiso.slug)}
                  </th>
                {/each}
              </tr>
            </thead>
            <tbody>
              {#each docentes as docente, i}
                <tr class={i % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                  <!-- Nombre docente -->
                  <td class="sticky left-0 font-medium text-gray-800 px-3 py-3
                             border-b border-r border-gray-100
                             {i % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                    {docente.nombre || `Usuario #${docente.id_usuario}`}
                  </td>

                  <!-- Toggle por permiso -->
                  {#each slugs as permiso}
                    {@const key = `${docente.id_usuario}-${permiso.id_permiso}`}
                    {@const aktivo = docente.permisos[permiso.id_permiso] ?? false}
                    {@const estado = cellState[key]}
                    <td class="text-center px-2 py-3 border-b border-gray-100">
                      <button
                        onclick={() => toggle(docente, permiso)}
                        disabled={estado === 'saving'}
                        class="relative inline-flex items-center justify-center w-10 h-6 rounded-full
                               transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400
                               {estado === 'saving' ? 'opacity-60 cursor-wait' : 'cursor-pointer'}
                               {aktivo ? 'bg-blue-500' : 'bg-gray-200'}"
                        aria-label="{aktivo ? 'Revocar' : 'Otorgar'} permiso {label(permiso.slug)}"
                        title="{aktivo ? 'Revocar' : 'Otorgar'}"
                      >
                        <span
                          class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow
                                 transition-transform duration-200
                                 {aktivo ? 'translate-x-4' : 'translate-x-0'}"
                        ></span>
                      </button>

                      {#if estado === 'error'}
                        <p class="text-xs text-red-500 mt-0.5">Error</p>
                      {/if}
                    </td>
                  {/each}
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        <!-- Leyenda -->
        <div class="mt-4 flex items-center gap-4 text-xs text-gray-500">
          <span class="flex items-center gap-1.5">
            <span class="inline-block w-4 h-4 rounded-full bg-blue-500"></span>
            Permiso otorgado
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-block w-4 h-4 rounded-full bg-gray-200"></span>
            Sin permiso
          </span>
        </div>
      {/if}
    </div>

    <!-- Footer -->
    <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
      <button
        onclick={onClose}
        class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm
               font-medium rounded-xl transition-colors"
      >
        Cerrar
      </button>
    </div>
  </div>
{/if}
