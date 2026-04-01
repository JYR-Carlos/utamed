<script lang="ts">
  /**
   * Página para crear una nueva inscripción de estudiante en un curso (Vista Docente).
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import axios, { AxiosError } from 'axios';

  interface Estudiante {
    id_estudiante: number;
    usuario?: { nombre1: string; apellido1: string; username: string };
  }

  interface Curso {
    id_curso: number;
    cod_curso: string;
    nombre: string;
  }

  interface Props {
    cursos: Curso[];
    estudiantes: Estudiante[];
    idCursoSeleccionado?: number;
    auth: { user: any };
  }

  let { cursos, estudiantes, idCursoSeleccionado, auth }: Props = $props();

  let formData = $state({
    id_curso: idCursoSeleccionado ? Number(idCursoSeleccionado) : 0,
    id_estudiante: 0,
    cod_inscripcion_uta: '',
    fecha_inscripcion: new Date().toISOString().split('T')[0],
    estado_inscripcion: 'INSCRITO',
    num_intento: 1,
  });

  let isSubmitting = $state(false);
  let errorMessage = $state('');
  let errors = $state<Record<string, string>>({});
  let estudiantesDisponibles = $state<Estudiante[]>([]);

  $effect(() => {
    if (formData.id_curso) {
      cargarEstudiantesDisponibles();
    } else {
      estudiantesDisponibles = [];
    }
  });

  async function cargarEstudiantesDisponibles() {
    try {
      const response = await axios.get('/docente/inscripciones/ajax/disponibles', {
        params: { id_curso: formData.id_curso },
      });
      estudiantesDisponibles = response.data.estudiantes || [];
    } catch (error) {
      console.error('Error loading available estudiantes:', error);
      estudiantesDisponibles = [];
    }
  }

  function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    errorMessage = '';
    errors = {};

    router.post('/docente/inscripciones', formData, {
      onStart: () => {
        isSubmitting = true;
      },
      onFinish: () => {
        isSubmitting = false;
      },
      onError: (pageErrors) => {
        console.log('Validation errors:', pageErrors);
        errors = pageErrors;

        // Show specific errors if available
        const errorList = Object.entries(pageErrors)
          .map(([field, message]) => `${field}: ${message}`)
          .join('\n');

        errorMessage = errorList || 'Por favor, verifica los errores en el formulario.';
      },
    });
  }

  function handleCancel() {
    router.get('/docente/inscripciones');
  }
</script>

<DocenteLayout {auth}>
  <div class="max-w-2xl mx-auto px-4 sm:px-6 md:px-8 py-6">
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900">Nueva Inscripción</h1>
      <p class="mt-2 text-sm text-gray-600">Inscribe un estudiante en uno de tus cursos</p>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
      <form onsubmit={handleSubmit} class="space-y-6">
        {#if errorMessage}
          <div class="rounded-md bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">{errorMessage}</p>
          </div>
        {/if}

        <!-- Curso -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Curso *</label>
          <select
            bind:value={formData.id_curso}
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
          >
            <option value={0}>Seleccionar curso...</option>
            {#each cursos as curso}
              <option value={curso.id_curso}>
                {curso.cod_curso} - {curso.nombre}
              </option>
            {/each}
          </select>
          {#if errors.id_curso}
            <p class="mt-2 text-sm text-red-600">{errors.id_curso}</p>
          {/if}
        </div>

        <!-- Estudiante -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Estudiante *</label>
          <select
            bind:value={formData.id_estudiante}
            required
            disabled={!formData.id_curso || estudiantesDisponibles.length === 0}
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100"
          >
            <option value={0}>
              {!formData.id_curso
                ? 'Selecciona un curso primero'
                : estudiantesDisponibles.length === 0
                  ? 'No hay estudiantes disponibles'
                  : 'Seleccionar estudiante...'}
            </option>
            {#each estudiantesDisponibles as estudiante}
              <option value={estudiante.id_estudiante}>
                {estudiante.usuario?.nombre1}
                {estudiante.usuario?.apellido1}
                ({estudiante.usuario?.username})
              </option>
            {/each}
          </select>
          {#if errors.id_estudiante}
            <p class="mt-2 text-sm text-red-600">{errors.id_estudiante}</p>
          {/if}
        </div>

        <!-- Código Inscripción UTA -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Código Inscripción UTA</label>
          <input
            type="text"
            bind:value={formData.cod_inscripcion_uta}
            placeholder="ej: INS-2024-001"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
          />
          {#if errors.cod_inscripcion_uta}
            <p class="mt-2 text-sm text-red-600">{errors.cod_inscripcion_uta}</p>
          {/if}
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-3 border-t pt-6">
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
            {isSubmitting ? 'Guardando...' : 'Crear Inscripción'}
          </button>
        </div>
      </form>
    </div>
  </div>
</DocenteLayout>
