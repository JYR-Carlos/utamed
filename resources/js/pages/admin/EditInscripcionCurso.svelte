<script lang="ts">
  /**
   * Página para editar una inscripción de estudiante en un curso.
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import InscripcionDeleteConfirm from '@/modules/resources/inscripcion/components/inscripcionDeleteConfirm.svelte';
  import {
    updateInscripcionForm,
    destroyInscripcionForm,
    goToInscripcionesIndex,
  } from '@/modules/resources/inscripcion/services/inscripcionApi';
  import type { BreadcrumbItem } from '@/types';

  interface Usuario {
    nombre1: string;
    apellido1: string;
    username: string;
  }

  interface Estudiante {
    id_estudiante: number;
    usuario?: Usuario;
  }

  interface Curso {
    id_curso: number;
    cod_curso: string;
    nombre: string;
  }

  interface Inscripcion {
    id_curso: number;
    id_estudiante: number;
    cod_inscripcion_uta: string | null;
    fecha_inscripcion: string;
    estado_inscripcion: string;
    num_intento: number;
    promedio_parcial: number | null;
    curso?: Curso;
    estudiante?: Estudiante;
  }

  interface Props {
    inscripcion: Inscripcion;
    curso: Curso;
  }

  let { inscripcion, curso }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inscripciones', href: '/admin/inscripciones_cursos' },
    { title: 'Editar', href: '#' },
  ];

  let formData = $state({
    cod_inscripcion_uta: inscripcion.cod_inscripcion_uta || '',
    fecha_inscripcion: inscripcion.fecha_inscripcion,
    estado_inscripcion: inscripcion.estado_inscripcion,
    num_intento: inscripcion.num_intento,
    promedio_parcial: inscripcion.promedio_parcial?.toString() || '',
  });

  let isSubmitting = $state(false);
  let errorMessage = $state('');
  let errors = $state<Record<string, string>>({});

  function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    isSubmitting = true;
    errorMessage = '';
    errors = {};

    updateInscripcionForm(inscripcion.id_curso, inscripcion.id_estudiante, formData, {
      onError: (errs) => {
        errors = errs;
        errorMessage = 'Por favor, verifica los errores en el formulario.';
      },
      onFinish: () => {
        isSubmitting = false;
      },
    });
  }

  function handleCancel() {
    goToInscripcionesIndex();
  }

  let showDeleteConfirm = $state(false);

  function handleDelete() {
    showDeleteConfirm = true;
  }

  function confirmDelete() {
    destroyInscripcionForm(inscripcion.id_curso, inscripcion.id_estudiante);
  }

  function cancelDelete() {
    showDeleteConfirm = false;
  }
</script>

<AdminLayout {breadcrumbs} width="narrow">
  <div>
    <div>
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Editar Inscripción</h1>
        <p class="mt-2 text-sm text-gray-600">
          Curso: {curso.cod_curso} - {curso.nombre}
          <br />
          Estudiante: {inscripcion.estudiante?.usuario?.nombre1}
          {inscripcion.estudiante?.usuario?.apellido1}
        </p>
      </div>

      <!-- Form -->
      <div class="bg-white shadow rounded-lg p-6">
        <form onsubmit={handleSubmit} class="space-y-6">
          {#if errorMessage}
            <div class="rounded-md bg-red-50 p-4">
              <p class="text-sm font-medium text-red-800">{errorMessage}</p>
            </div>
          {/if}

          <!-- Course Info (Read-only) -->
          <div>
            <label for="edit-curso" class="block text-sm font-medium text-gray-700 mb-2">Curso</label>
            <input
              id="edit-curso"
              type="text"
              value="{curso.cod_curso} - {curso.nombre}"
              disabled
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-600"
            />
          </div>

          <!-- Student Info (Read-only) -->
          <div>
            <label for="edit-estudiante" class="block text-sm font-medium text-gray-700 mb-2"
              >Estudiante</label
            >
            <input
              id="edit-estudiante"
              type="text"
              value="{inscripcion.estudiante?.usuario?.nombre1}
							{inscripcion.estudiante?.usuario?.apellido1}
							({inscripcion.estudiante?.usuario?.username})"
              disabled
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-600"
            />
          </div>

          <!-- Código Inscripción UTA -->
          <div>
            <label for="edit-cod_inscripcion_uta" class="block text-sm font-medium text-gray-700 mb-2"
              >Código Inscripción UTA</label
            >
            <input
              id="edit-cod_inscripcion_uta"
              type="text"
              bind:value={formData.cod_inscripcion_uta}
              placeholder="ej: INS-2024-001"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />
            {#if errors.cod_inscripcion_uta}
              <p class="mt-2 text-sm text-red-600">{errors.cod_inscripcion_uta}</p>
            {/if}
          </div>

          <!-- Fecha Inscripción -->
          <div>
            <label for="edit-fecha_inscripcion" class="block text-sm font-medium text-gray-700 mb-2"
              >Fecha de Inscripción</label
            >
            <input
              id="edit-fecha_inscripcion"
              type="date"
              bind:value={formData.fecha_inscripcion}
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />
            {#if errors.fecha_inscripcion}
              <p class="mt-2 text-sm text-red-600">{errors.fecha_inscripcion}</p>
            {/if}
          </div>

          <!-- Estado Inscripción -->
          <div>
            <label for="edit-estado_inscripcion" class="block text-sm font-medium text-gray-700 mb-2"
              >Estado de Inscripción</label
            >
            <select
              id="edit-estado_inscripcion"
              bind:value={formData.estado_inscripcion}
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            >
              <option value="INSCRITO">Inscrito</option>
              <option value="RETIRADO">Retirado</option>
              <option value="SUSPENDIDO">Suspendido</option>
              <option value="APROBADO">Aprobado</option>
              <option value="REPROBADO">Reprobado</option>
            </select>
            {#if errors.estado_inscripcion}
              <p class="mt-2 text-sm text-red-600">{errors.estado_inscripcion}</p>
            {/if}
          </div>

          <!-- Número Intento -->
          <div>
            <label for="edit-num_intento" class="block text-sm font-medium text-gray-700 mb-2"
              >Número de Intento</label
            >
            <input
              id="edit-num_intento"
              type="number"
              bind:value={formData.num_intento}
              min="1"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />
            {#if errors.num_intento}
              <p class="mt-2 text-sm text-red-600">{errors.num_intento}</p>
            {/if}
          </div>

          <!-- Promedio Parcial -->
          <div>
            <label for="edit-promedio_parcial" class="block text-sm font-medium text-gray-700 mb-2"
              >Promedio Parcial</label
            >
            <input
              id="edit-promedio_parcial"
              type="number"
              bind:value={formData.promedio_parcial}
              placeholder="0.0"
              min="0"
              max="7"
              step="0.1"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />
            {#if errors.promedio_parcial}
              <p class="mt-2 text-sm text-red-600">{errors.promedio_parcial}</p>
            {/if}
          </div>

          <!-- Form Actions -->
          <div class="flex justify-between items-center border-t pt-6">
            <button
              type="button"
              onclick={handleDelete}
              class="px-4 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50"
            >
              Eliminar Inscripción
            </button>

            <div class="space-x-3">
              <button
                type="button"
                onclick={handleCancel}
                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
              >
                Cancelar
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400"
              >
                {isSubmitting ? 'Guardando...' : 'Guardar Cambios'}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</AdminLayout>

<InscripcionDeleteConfirm
  bind:isOpen={showDeleteConfirm}
  {inscripcion}
  onConfirm={confirmDelete}
  onCancel={cancelDelete}
/>
