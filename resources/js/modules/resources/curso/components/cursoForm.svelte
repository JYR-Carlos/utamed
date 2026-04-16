<script lang="ts">
  /**
   * Componente: Modal de Formulario de Curso
   *
   * Modal para crear o editar un curso.
   * Incluye selectores cascada para asignatura/plan.
   *
   * Props:
   * - isOpen: boolean = $bindable() - Estado del modal
   * - editingCurso: Curso | null = $bindable() - Curso siendo editado (null = crear nuevo)
   * - asignaturas: Asignatura[] - Lista de asignaturas disponibles
   * - planes: Plan[] - Lista de planes disponibles
   * - docentes: Docente[] - Lista de docentes disponibles
   * - onSubmit: (data: CursoFormState) => void - Callback submit
   */
  import { X } from 'lucide-svelte';
  import type { Curso, Asignatura, Plan, Docente, CursoFormData } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    editingCurso?: Curso | null;
    asignaturas?: Asignatura[];
    planes?: Plan[];
    docentes?: Docente[];
    onSubmit?: (data: CursoFormData) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    editingCurso = $bindable<Curso | null>(null),
    asignaturas = [],
    planes = [],
    docentes = [],
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  let formData = $state<CursoFormData>({
    id_asignatura: 0,
    id_plan: 0,
    cod_curso: 0,
    nombre: '',
    fecha_inicio: '',
    numero_semestre: undefined,
    agno_real: new Date().getFullYear(),
    semestre_real: 1,
    id_docente_sugerido: undefined,
  });

  let isSubmitting = $state(false);

  // Sincronizar con editingCurso
  $effect(() => {
    if (editingCurso) {
      const fechaFormato = editingCurso.fecha_inicio
        ? new Date(editingCurso.fecha_inicio).toISOString().split('T')[0]
        : '';
      formData = {
        id_asignatura: editingCurso.asignacionPlan?.id_asignatura || 0,
        id_plan: editingCurso.asignacionPlan?.id_plan || 0,
        cod_curso: editingCurso.cod_curso,
        nombre: editingCurso.nombre || '',
        fecha_inicio: fechaFormato,
        numero_semestre: editingCurso.numero_semestre,
        agno_real: editingCurso.asignacionPlan?.agno_planificado || new Date().getFullYear(),
        semestre_real: editingCurso.asignacionPlan?.semestre_planificado || 1,
        id_docente_sugerido: editingCurso.id_docente_titular,
      };
    } else {
      formData = {
        id_asignatura: 0,
        id_plan: 0,
        cod_curso: 0,
        nombre: '',
        fecha_inicio: '',
        numero_semestre: undefined,
        agno_real: new Date().getFullYear(),
        semestre_real: 1,
        id_docente_sugerido: undefined,
      };
    }
  });

  function handleSubmit(e: Event) {
    e.preventDefault();
    isSubmitting = true;
    try {
      onSubmit(formData);
      isOpen = false;
    } finally {
      isSubmitting = false;
    }
  }

  function handleClose() {
    isOpen = false;
    onClose();
  }
</script>

{#if isOpen}
  <!-- Modal Overlay -->
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    onclick={(e) => e.target === e.currentTarget && handleClose()}
    onkeydown={(e) => e.key === 'Escape' && handleClose()}
    role="dialog"
    aria-modal="true"
    tabindex="-1"
  >
    <!-- Modal Content -->
    <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div
        class="sticky top-0 flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-white"
      >
        <h2 class="text-lg font-semibold text-gray-900">
          {editingCurso ? 'Editar Curso' : 'Crear Curso'}
        </h2>
        <button
          onclick={handleClose}
          class="p-1 text-gray-500 hover:text-gray-700 transition"
          title="Cerrar"
        >
          <X size={20} />
        </button>
      </div>

      <!-- Form -->
      <form onsubmit={handleSubmit} class="p-6 space-y-4">
        <!-- Código Curso -->
        <div>
          <label for="cod_curso" class="block text-sm font-medium text-gray-700 mb-1">
            Código del Curso *
          </label>
          <input
            id="cod_curso"
            type="number"
            bind:value={formData.cod_curso}
            required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          />
        </div>

        <!-- Nombre Curso -->
        <div>
          <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
            Nombre del Curso
          </label>
          <input
            id="nombre"
            type="text"
            bind:value={formData.nombre}
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          />
        </div>

        <!-- Asignatura -->
        <div>
          <label for="id_asignatura" class="block text-sm font-medium text-gray-700 mb-1">
            Asignatura *
          </label>
          <select
            id="id_asignatura"
            bind:value={formData.id_asignatura}
            required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          >
            <option value={0}>Seleccionar asignatura</option>
            {#each asignaturas as asig}
              <option value={asig.id_asignatura}>
                {asig.cod_asignatura} - {asig.nombre}
              </option>
            {/each}
          </select>
        </div>

        <!-- Plan -->
        <div>
          <label for="id_plan" class="block text-sm font-medium text-gray-700 mb-1"> Plan * </label>
          <select
            id="id_plan"
            bind:value={formData.id_plan}
            required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          >
            <option value={0}>Seleccionar plan</option>
            {#each planes as plan}
              <option value={plan.id_plan}>
                {plan.id_plan} - {plan.agno_plan}
              </option>
            {/each}
          </select>
        </div>

        <!-- Fecha Inicio -->
        <div>
          <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-1">
            Fecha Inicio
          </label>
          <input
            id="fecha_inicio"
            type="date"
            lang="es"
            bind:value={formData.fecha_inicio}
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          />
        </div>

        <!-- Año Real -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="agno_real" class="block text-sm font-medium text-gray-700 mb-1">
              Año
            </label>
            <input
              id="agno_real"
              type="number"
              bind:value={formData.agno_real}
              class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
            />
          </div>

          <!-- Semestre Real -->
          <div>
            <label for="semestre_real" class="block text-sm font-medium text-gray-700 mb-1">
              Semestre
            </label>
            <select
              id="semestre_real"
              bind:value={formData.semestre_real}
              class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
            >
              <option value={1}>1</option>
              <option value={2}>2</option>
            </select>
          </div>
        </div>

        <!-- Número Semestre -->
        <div>
          <label for="numero_semestre" class="block text-sm font-medium text-gray-700 mb-1">
            Número de Semestre en Carrera
          </label>
          <input
            id="numero_semestre"
            type="number"
            bind:value={formData.numero_semestre}
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          />
        </div>

        <!-- Docente Titular -->
        <div>
          <label for="id_docente_sugerido" class="block text-sm font-medium text-gray-700 mb-1">
            Docente Titular {editingCurso ? '' : '*'}
          </label>
          <select
            id="id_docente_sugerido"
            bind:value={formData.id_docente_sugerido}
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          >
            <option value={undefined}>Seleccionar docente</option>
            {#each docentes as doc}
              <option value={doc.id_docente}>
                {doc.usuario?.nombre1 ?? ''}
                {doc.usuario?.apellido1 ?? ''}{doc.cargo ? ` — ${doc.cargo}` : ''}
              </option>
            {/each}
          </select>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-3 pt-4">
          <button
            type="button"
            onclick={handleClose}
            class="px-4 py-2 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition font-medium"
          >
            Cancelar
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? 'Guardando...' : editingCurso ? 'Actualizar' : 'Crear'}
          </button>
        </div>
      </form>
    </div>
  </div>
{/if}
