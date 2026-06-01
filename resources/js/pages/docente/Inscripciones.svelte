<script lang="ts">
  /**
   * Página de gestión de inscripciones para Docentes.
   *
   * Muestra las inscripciones de los cursos donde el docente dicta clases.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import type { PaginatedResponse } from '@/types/admin.types';

  // Reuse types or define subset
  interface Curso {
    id_curso: number;
    cod_curso: string;
    nombre: string;
  }

  interface Usuario {
    nombre1: string;
    apellido1: string;
    username: string;
  }

  interface Estudiante {
    id_estudiante: number;
    usuario: Usuario;
  }

  interface Inscripcion {
    id_curso: number;
    id_estudiante: number;
    cod_inscripcion_uta: string;
    fecha_inscripcion: string;
    estado_inscripcion: string;
    num_intento: number;
    curso: Curso;
    estudiante: Estudiante;
  }

  interface Props {
    inscripciones: PaginatedResponse<Inscripcion>;
    cursos: Curso[];
    filters: { search?: string; id_curso?: number; estado_inscripcion?: string };
  }

  let { inscripciones, cursos, filters }: Props = $props();

  // Filters state
  let search = $state(filters.search || '');
  let selectedCurso = $state(filters.id_curso || '');
  let selectedEstado = $state(filters.estado_inscripcion || '');

  function openCreatePage() {
    // Pass selected course to create page if filtered
    let url = '/docente/inscripciones/create';
    if (selectedCurso) {
      url += `?id_curso=${selectedCurso}`;
    }
    router.visit(url);
  }

  function applyFilters() {
    router.get(
      '/docente/inscripciones',
      {
        search,
        id_curso: selectedCurso,
        estado_inscripcion: selectedEstado,
      },
      { preserveState: true, preserveScroll: true },
    );
  }
</script>

<DocenteLayout>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 py-6">
    <div class="md:flex md:items-center md:justify-between mb-6">
      <div class="min-w-0 flex-1">
        <h2
          class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight"
        >
          Inscripciones de Estudiantes
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Gestiona los estudiantes inscritos en tus cursos asignados.
        </p>
      </div>
      <div class="mt-4 flex md:ml-4 md:mt-0">
        <button
          type="button"
          onclick={openCreatePage}
          class="ml-3 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="-ml-0.5 mr-1.5 h-5 w-5"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"
              clip-rule="evenodd"
            />
          </svg>
          Nueva Inscripción
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-lg shadow mb-6 flex flex-wrap gap-4 items-end">
      <div class="flex-1 min-w-[200px]">
        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
        <input
          type="text"
          id="search"
          bind:value={search}
          placeholder="Estudiante..."
          class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border"
        />
      </div>
      <div class="w-64">
        <label for="curso" class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
        <select
          id="curso"
          bind:value={selectedCurso}
          class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border"
        >
          <option value="">Todos mis cursos</option>
          {#each cursos as curso}
            <option value={curso.id_curso}>{curso.cod_curso} - {curso.nombre}</option>
          {/each}
        </select>
      </div>
      <button
        onclick={applyFilters}
        class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
      >
        Filtrar
      </button>
    </div>

    <!-- Inscripciones Table -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-300">
          <thead class="bg-gray-50">
            <tr>
              <th
                scope="col"
                class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6"
                >Estudiante</th
              >
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                >Curso</th
              >
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                >Cód. Inscripción</th
              >
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                >Fecha</th
              >
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                >Estado</th
              >
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            {#if inscripciones.data.length === 0}
              <tr>
                <td colspan="5" class="py-10 text-center text-gray-500 text-sm">
                  No se encontraron inscripciones.
                </td>
              </tr>
            {:else}
              {#each inscripciones.data as inscripcion}
                <tr>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                    <div class="font-medium text-gray-900">
                      {inscripcion.estudiante.usuario.nombre1}
                      {inscripcion.estudiante.usuario.apellido1}
                    </div>
                    <div class="text-gray-500">{inscripcion.estudiante.usuario.username}</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    <div class="font-medium text-gray-900">{inscripcion.curso.cod_curso}</div>
                    <div class="text-gray-500 truncate max-w-xs">{inscripcion.curso.nombre}</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {inscripcion.cod_inscripcion_uta || '-'}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {inscripcion.fecha_inscripcion}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm">
                    <span
                      class={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${
                        inscripcion.estado_inscripcion === 'INSCRITO'
                          ? 'bg-green-50 text-green-700 ring-green-600/20'
                          : inscripcion.estado_inscripcion === 'RETIRADO'
                            ? 'bg-red-50 text-red-700 ring-red-600/20'
                            : 'bg-gray-50 text-gray-600 ring-gray-500/10'
                      }`}
                    >
                      {inscripcion.estado_inscripcion}
                    </span>
                  </td>
                </tr>
              {/each}
            {/if}
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      {#if inscripciones.total > inscripciones.per_page}
        <div
          class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-b-xl"
        >
          <div class="flex flex-1 justify-between sm:hidden">
            <button
              disabled={!inscripciones.prev_page_url}
              onclick={() => router.visit(inscripciones.prev_page_url!)}
              class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              >Anterior</button
            >
            <button
              disabled={!inscripciones.next_page_url}
              onclick={() => router.visit(inscripciones.next_page_url!)}
              class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              >Siguiente</button
            >
          </div>
          <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Mostrando <span class="font-medium">{inscripciones.from || 0}</span> a
                <span class="font-medium">{inscripciones.to || 0}</span>
                de <span class="font-medium">{inscripciones.total}</span> resultados
              </p>
            </div>
            <div>
              <nav
                class="isolate inline-flex -space-x-px rounded-md shadow-sm"
                aria-label="Pagination"
              >
                {#each inscripciones.links as link}
                  {#if link.url}
                    <button
                      onclick={() => router.visit(link.url!)}
                      class={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ${
                        link.active
                          ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                          : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'
                      }`}
                    >
                      {@html link.label}
                    </button>
                  {:else}
                    <span
                      class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-500 ring-1 ring-inset ring-gray-300 focus:outline-offset-0"
                      >{@html link.label}</span
                    >
                  {/if}
                {/each}
              </nav>
            </div>
          </div>
        </div>
      {/if}
    </div>
  </div>
</DocenteLayout>
