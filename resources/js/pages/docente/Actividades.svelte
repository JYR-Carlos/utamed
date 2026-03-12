<script lang="ts">
  /**
   * Página de gestión de actividades/tareas en un curso.
   *
   * Permite a docentes crear, leer, actualizar y eliminar actividades
   * (tareas, evaluaciones, trabajos) en sus cursos.
   *
   * Características:
   * - CRUD de actividades con datos como nombre, tipo, fecha límite
   * - Soporte para actividades individuales o grupales
   * - Asignación a secciones específicas del curso
   * - Organización por unidades temáticas
   * - Configuración de tipo de entrega (online, presencial, etc.)
   * - Validación de acceso (solo docente responsable del curso)
   *
   * Tabla relacionada:
   * - agenda.actividad: Información de actividades/tareas
   */
  import { router, Link } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import { Plus, Edit2, Trash2, ArrowLeft, Users, ClipboardList } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';

  interface Props {
    curso: any;
    actividades: Actividad[];
    secciones: any[];
    unidades: any[];
  }

  let { curso, actividades, secciones, unidades }: Props = $props();

  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let isLoading = $state(false);
  let editingActividad = $state<Actividad | null>(null);
  let deletingActividad = $state<Actividad | null>(null);

  let formData = $state<Partial<Actividad>>({
    nombre: '',
    fecha_limite: '',
    tipo_actividad: 1,
    tipo_entrega: 'online',
    es_grupal: false,
    max_integrantes: 1,
    visible: true,
    id_seccion: 0,
    id_unidad: 0,
  });

  function openCreateModal() {
    editingActividad = null;
    formData = {
      nombre: '',
      fecha_limite: '',
      tipo_actividad: 1,
      tipo_entrega: 'online',
      es_grupal: false,
      max_integrantes: 1,
      visible: true,
      id_seccion: 0,
      id_unidad: 0,
    };
    showModal = true;
  }

  function openEditModal(actividad: Actividad) {
    editingActividad = actividad;
    formData = { ...actividad };
    showModal = true;
  }

  function closeModal() {
    showModal = false;
    editingActividad = null;
  }

  function handleSubmit() {
    isLoading = true;

    if (editingActividad) {
      router.put(`/docente/cursos/${curso.id_curso}/actividades/${editingActividad.id_actividad}`, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    } else {
      router.post(`/docente/cursos/${curso.id_curso}/actividades`, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    }
  }

  function openDeleteDialog(actividad: Actividad) {
    deletingActividad = actividad;
    showDeleteDialog = true;
  }

  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingActividad = null;
  }

  function handleDelete() {
    if (!deletingActividad) return;

    isLoading = true;
    router.delete(`/docente/cursos/${curso.id_curso}/actividades/${deletingActividad.id_actividad}`, {
      onSuccess: () => {
        closeDeleteDialog();
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }
</script>

<DocenteLayout>
  <div class="p-8 max-w-5xl mx-auto">
    <!-- Header with back button -->
    <div class="mb-8">
      <div class="mb-4">
        <Link href="/docente/cursos" class="inline-flex items-center gap-2 text-blue-500 font-medium hover:text-blue-600 transition-colors py-2">
          <ArrowLeft size={20} />
          Mis Cursos
        </Link>
      </div>
      <div class="flex justify-between items-start gap-4">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 mb-0">Actividades del Curso</h1>
          <p class="text-gray-600 text-base mt-2">
            {curso.cod_asignatura} - {curso.asignatura_nombre}
          </p>
        </div>
        <div class="flex gap-3">
          <Link
            href={`/docente/inscripciones?id_curso=${curso.id_curso}`}
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-900 border border-gray-300 rounded-md font-medium hover:bg-gray-200 transition-all"
          >
            <Users size={20} />
            Inscripciones
          </Link>
          <button
            onclick={openCreateModal}
            class="inline-flex items-center gap-2 px-6 py-3 bg-blue-500 text-white rounded-lg font-semibold hover:bg-blue-600 transition-colors whitespace-nowrap"
          >
            <Plus size={20} />
            Nueva Actividad
          </button>
        </div>
      </div>
    </div>

    <!-- Activities List -->
    <div>
      {#if actividades.length === 0}
        <div class="text-center p-16 bg-white rounded-xl border border-gray-200">
          <div class="text-5xl mb-4">📋</div>
          <h3 class="text-xl text-gray-900 font-semibold mb-2">No hay actividades creadas</h3>
          <p class="text-gray-600 mb-6">Crea tu primera actividad para este curso</p>
          <button
            onclick={openCreateModal}
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-900 border border-gray-300 rounded-md font-medium hover:bg-gray-200 transition-all"
          >
            <Plus size={18} />
            Crear Actividad
          </button>
        </div>
      {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {#each actividades as actividad}
            <div class="bg-white border border-gray-200 rounded-xl p-6 hover:border-blue-500 hover:shadow-lg transition-all flex flex-col">
              <div class="flex justify-between items-start mb-4">
                <div class="flex items-center gap-3 flex-1">
                  <h3 class="text-lg font-semibold text-gray-900">{actividad.nombre}</h3>
                  {#if actividad.visible}
                    <span class="inline-block text-xs font-medium px-3 py-1 bg-green-100 text-green-900 rounded-full">Visible</span>
                  {:else}
                    <span class="inline-block text-xs font-medium px-3 py-1 bg-red-100 text-red-900 rounded-full">Oculta</span>
                  {/if}
                </div>
                <div class="flex gap-2">
                  <button
                    onclick={() => openEditModal(actividad)}
                    class="p-2 bg-transparent border border-gray-200 rounded-md text-gray-600 hover:bg-gray-100 hover:text-blue-500 hover:border-blue-500 transition-all"
                    title="Editar"
                  >
                    <Edit2 size={18} />
                  </button>
                  <button
                    onclick={() => openDeleteDialog(actividad)}
                    class="p-2 bg-transparent border border-gray-200 rounded-md text-gray-600 hover:bg-gray-100 hover:text-red-600 hover:border-red-600 transition-all"
                    title="Eliminar"
                  >
                    <Trash2 size={18} />
                  </button>
                </div>
              </div>

              <div class="flex flex-col gap-3 mb-4 flex-1">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 font-medium">Tipo:</span>
                  <span class="text-gray-900 font-medium">{actividad.tipo_actividad}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 font-medium">Entrega:</span>
                  <span class="text-gray-900 font-medium capitalize">{actividad.tipo_entrega}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 font-medium">Modalidad:</span>
                  <span class="text-gray-900 font-medium">
                    {#if actividad.es_grupal}
                      👥 Grupal (máx. {actividad.max_integrantes} personas)
                    {:else}
                      👤 Individual
                    {/if}
                  </span>
                </div>
                <div class="flex justify-between text-sm bg-blue-50 px-3 py-2 rounded-md border-l-4 border-blue-500">
                  <span class="text-gray-600 font-medium">Fecha Límite:</span>
                  <span class="text-blue-600 font-semibold">{formatDate(actividad.fecha_limite)}</span>
                </div>
              </div>

              <div class="flex gap-2">
                <Link
                  href={`/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/evaluacion`}
                  class="flex-1 inline-flex items-center justify-center gap-1 px-4 py-2.5 bg-blue-50 border border-blue-200 rounded-md font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-500 transition-all text-sm no-underline"
                >
                  <ClipboardList size={16} />
                  Evaluar
                </Link>
                <button
                  onclick={() => openEditModal(actividad)}
                  class="flex-1 inline-flex items-center justify-center gap-1 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-md font-medium text-gray-900 hover:bg-gray-200 hover:border-blue-500 hover:text-blue-600 transition-all text-sm"
                >
                  <Edit2 size={16} />
                  Editar
                </button>
              </div>
            </div>
          {/each}
        </div>
      {/if}
    </div>
  </div>

  <!-- Add/Edit Modal -->
  <FormModal
    bind:isOpen={showModal}
    title={editingActividad ? 'Editar Actividad' : 'Nueva Actividad'}
    onClose={closeModal}
    onSubmit={handleSubmit}
    {isLoading}
  >
    <div class="mb-4">
      <label for="nombre" class="block font-medium text-gray-700 mb-2 text-sm">Nombre de la Actividad *</label>
      <input
        id="nombre"
        type="text"
        bind:value={formData.nombre}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
        placeholder="Ej: Tarea 1, Evaluación Parcial"
        required
      />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="mb-4">
        <label for="fecha_limite" class="block font-medium text-gray-700 mb-2 text-sm">Fecha Límite *</label>
        <input
          id="fecha_limite"
          type="date"
          bind:value={formData.fecha_limite}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
          required
        />
      </div>

      <div class="mb-4">
        <label for="tipo_entrega" class="block font-medium text-gray-700 mb-2 text-sm">Tipo de Entrega *</label>
        <select
          id="tipo_entrega"
          bind:value={formData.tipo_entrega}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
          required
        >
          <option value="online">En línea</option>
          <option value="presencial">Presencial</option>
          <option value="hibrido">Híbrido</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="mb-4">
        <label for="id_seccion" class="block font-medium text-gray-700 mb-2 text-sm">Sección *</label>
        <select
          id="id_seccion"
          bind:value={formData.id_seccion}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
          required
        >
          <option value={0}>Seleccione una sección</option>
          {#each secciones as seccion}
            <option value={seccion.id_seccion}>
              {seccion.numero_seccion}
            </option>
          {/each}
        </select>
      </div>

      <div class="mb-4">
        <label for="id_unidad" class="block font-medium text-gray-700 mb-2 text-sm">Unidad *</label>
        <select
          id="id_unidad"
          bind:value={formData.id_unidad}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
          required
        >
          <option value={0}>Seleccione una unidad</option>
          {#each unidades as unidad}
            <option value={unidad.id_unidad}>
              {unidad.nombre}
            </option>
          {/each}
        </select>
      </div>
    </div>

    <div class="mb-4">
      <label class="flex items-center gap-2 text-gray-700 text-sm cursor-pointer">
        <input type="checkbox" bind:checked={formData.es_grupal} class="w-4.5 h-4.5 cursor-pointer" />
        <span>Es una actividad grupal</span>
      </label>
    </div>

    {#if formData.es_grupal}
      <div class="mb-4">
        <label for="max_integrantes" class="block font-medium text-gray-700 mb-2 text-sm">Máximo de Integrantes</label>
        <input
          id="max_integrantes"
          type="number"
          bind:value={formData.max_integrantes}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
          min="2"
          max="100"
        />
      </div>
    {/if}

    <div class="mb-4">
      <label class="flex items-center gap-2 text-gray-700 text-sm cursor-pointer">
        <input type="checkbox" bind:checked={formData.visible} class="w-4.5 h-4.5 cursor-pointer" />
        <span>Visible para estudiantes</span>
      </label>
    </div>
  </FormModal>

  <!-- Delete Confirmation -->
  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Actividad?"
    message="Esta acción no se puede deshacer. Los datos de entrega asociados podrían verse afectados."
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    {isLoading}
  />
</DocenteLayout>
