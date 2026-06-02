<script lang="ts">
  /**
   * Componente: Modal de Formulario de Componente
   *
   * Modal para crear o editar un componente dentro de un curso.
   * - CREAR: selecciona tipo de componente + docente titular inicial (opcional).
   * - EDITAR: permite cambiar el tipo y gestionar la lista de docentes en tiempo real
   *           (agregar / eliminar) sin recargar la página. El primer docente asignado
   *           es siempre el titular (corona).
   *
   * Props:
   * - isOpen: boolean = $bindable() - Estado del modal
   * - cursoId: number - ID del curso al que pertenece el componente
   * - editingComponente: Componente | null - Componente siendo editado (null = crear nuevo)
   * - tiposComponente: TipoComponente[] - Tipos de componente disponibles
   * - docentes: Docente[] - Docentes disponibles para asignar
   * - onSubmit: (data: ComponenteFormState) => void - Callback submit (solo tipo en edición)
   */
  import { X, Crown, Trash2, Plus } from 'lucide-svelte';
  import type {
    Componente,
    TipoComponente,
    Docente,
    DocenteAsignadoComponente,
  } from '../types/curso.types';
  import type { ComponenteFormState } from '../types/curso.types';
  import {
    addDocenteComponente,
    removeDocenteComponente,
    setTitularComponente,
  } from '../services/cursoApi';

  interface Props {
    isOpen?: boolean;
    cursoId?: number;
    editingComponente?: Componente | null;
    tiposComponente?: TipoComponente[];
    docentes?: Docente[];
    onSubmit?: (data: ComponenteFormState) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    cursoId = 0,
    editingComponente = $bindable<Componente | null>(null),
    tiposComponente = [],
    docentes = [],
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  let formData = $state<ComponenteFormState>({
    id_tipo_componente: 0,
    id_docente: undefined,
  });

  // Copia local de docentes asignados (se actualiza en tiempo real en modo edición)
  let docentesAsignados = $state<DocenteAsignadoComponente[]>([]);
  let nuevoDocenteId = $state<number | string>('');
  let isAddingDocente = $state(false);
  let isSettingTitular = $state(false);
  let errorMsg = $state<string | null>(null);
  let successMsg = $state<string | null>(null);
  let successTimeout: ReturnType<typeof setTimeout> | null = null;
  let isSubmitting = $state(false);

  function showSuccess(msg: string) {
    if (successTimeout) clearTimeout(successTimeout);
    errorMsg = null;
    successMsg = msg;
    successTimeout = setTimeout(() => {
      successMsg = null;
    }, 3500);
  }

  // Sincronizar con editingComponente cuando se abre el modal
  $effect(() => {
    if (editingComponente) {
      formData = {
        id_tipo_componente: editingComponente.id_tipo_componente,
        id_docente: undefined,
      };
      docentesAsignados = [...(editingComponente.docentes ?? [])];
    } else {
      formData = { id_tipo_componente: 0, id_docente: undefined };
      docentesAsignados = [];
    }
    nuevoDocenteId = '';
    errorMsg = null;
    successMsg = null;
  });

  // Docentes que todavía no están asignados al componente (para el selector de agregar)
  const docentesDisponibles = $derived(
    docentes.filter((d) => !docentesAsignados.some((da) => da.id_docente === d.id_docente)),
  );

  async function handleAddDocente() {
    const id = Number(nuevoDocenteId);
    if (!id || !editingComponente) return;

    isAddingDocente = true;
    errorMsg = null;

    const result = await addDocenteComponente(editingComponente.id_componente, id);

    if ('error' in result) {
      errorMsg = result.error;
    } else {
      docentesAsignados = [...docentesAsignados, result.docente_componente];
      nuevoDocenteId = '';
      showSuccess('Docente asignado correctamente');
    }

    isAddingDocente = false;
  }

  async function handleRemoveDocente(dc: DocenteAsignadoComponente) {
    if (!editingComponente) return;
    errorMsg = null;
    successMsg = null;

    const result = await removeDocenteComponente(
      editingComponente.id_componente,
      dc.id_docente_componente,
    );

    if ('error' in result) {
      errorMsg = result.error;
    } else {
      docentesAsignados = docentesAsignados.filter(
        (d) => d.id_docente_componente !== dc.id_docente_componente,
      );
      showSuccess('Docente removido correctamente');
    }
  }

  async function handleSetTitular(dc: DocenteAsignadoComponente) {
    if (!editingComponente || dc.es_titular) return;
    errorMsg = null;
    successMsg = null;
    isSettingTitular = true;

    const result = await setTitularComponente(
      editingComponente.id_componente,
      dc.id_docente_componente,
    );

    if ('error' in result) {
      errorMsg = result.error;
    } else {
      // Actualizar estado local: quitar titular a todos, poner al seleccionado
      docentesAsignados = docentesAsignados.map((d) => ({
        ...d,
        es_titular: d.id_docente_componente === dc.id_docente_componente,
      }));
      showSuccess('Titular actualizado correctamente');
    }

    isSettingTitular = false;
  }

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
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">
          {editingComponente ? 'Editar Componente' : 'Crear Componente'}
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
      <form onsubmit={handleSubmit} class="p-6 space-y-5">
        <!-- Tipo Componente -->
        <div>
          <label for="id_tipo_componente" class="block text-sm font-medium text-gray-700 mb-1">
            Tipo de Componente *
          </label>
          <select
            id="id_tipo_componente"
            bind:value={formData.id_tipo_componente}
            required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          >
            <option value={0}>Seleccionar tipo</option>
            {#each tiposComponente as tipo}
              <option value={tipo.id_tipo_componente}>{tipo.tipo}</option>
            {/each}
          </select>
        </div>

        {#if !editingComponente}
          <!-- CREAR: selector de docente titular inicial -->
          <div>
            <label for="id_docente" class="block text-sm font-medium text-gray-700 mb-1">
              Docente Titular Inicial
            </label>
            <select
              id="id_docente"
              bind:value={formData.id_docente}
              class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
            >
              <option value={undefined}>Sin asignar</option>
              {#each docentes as doc}
                <option value={doc.id_docente}>
                  {doc.nombre_completo || `${doc.nombre1 ?? ''} ${doc.apellido1 ?? ''}`.trim()}
                </option>
              {/each}
            </select>
          </div>
        {:else}
          <!-- EDITAR: gestión de docentes en tiempo real -->
          <div>
            <p class="block text-sm font-medium text-gray-700 mb-2">Docentes Asignados</p>

            {#if successMsg}
              <p class="text-sm text-green-600 mb-2 flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
                {successMsg}
              </p>
            {/if}
            {#if errorMsg}
              <p class="text-sm text-red-600 mb-2">{errorMsg}</p>
            {/if}

            <!-- Lista de docentes actuales -->
            {#if docentesAsignados.length > 0}
              <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg mb-3">
                {#each docentesAsignados as dc (dc.id_docente_componente)}
                  <li class="flex items-center gap-2 px-3 py-2">
                    {#if dc.es_titular}
                      <Crown size={14} class="text-amber-500 shrink-0" />
                    {:else}
                      <button
                        type="button"
                        onclick={() => handleSetTitular(dc)}
                        disabled={isSettingTitular}
                        class="shrink-0 p-0 text-gray-300 hover:text-amber-400 transition disabled:opacity-50"
                        title="Hacer titular de este componente"
                      >
                        <Crown size={14} />
                      </button>
                    {/if}
                    <span class="flex-1 text-sm text-gray-800 truncate">
                      {dc.nombre_completo ||
                        `${dc.nombre1 ?? ''} ${dc.apellido1 ?? ''}`.trim() ||
                        `Docente #${dc.id_docente}`}
                      {#if dc.es_titular}
                        <span class="text-xs text-amber-600 font-medium ml-1">(Titular)</span>
                      {/if}
                    </span>
                    <button
                      type="button"
                      onclick={() => handleRemoveDocente(dc)}
                      class="p-1 text-gray-400 hover:text-red-500 transition shrink-0"
                      title="Quitar docente"
                    >
                      <Trash2 size={14} />
                    </button>
                  </li>
                {/each}
              </ul>
            {:else}
              <p class="text-sm text-gray-500 mb-3">Sin docentes asignados.</p>
            {/if}

            <!-- Agregar docente -->
            {#if docentesDisponibles.length > 0}
              <div class="flex gap-2">
                <select
                  bind:value={nuevoDocenteId}
                  class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
                >
                  <option value="">Seleccionar docente…</option>
                  {#each docentesDisponibles as doc}
                    <option value={doc.id_docente}>
                      {doc.nombre_completo || `${doc.nombre1 ?? ''} ${doc.apellido1 ?? ''}`.trim()}
                    </option>
                  {/each}
                </select>
                <button
                  type="button"
                  onclick={handleAddDocente}
                  disabled={!nuevoDocenteId || isAddingDocente}
                  class="flex items-center gap-1 px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <Plus size={14} />
                  {isAddingDocente ? '…' : 'Agregar'}
                </button>
              </div>
            {/if}
          </div>
        {/if}

        <!-- Buttons -->
        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onclick={handleClose}
            class="px-4 py-2 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition font-medium"
          >
            {editingComponente ? 'Cerrar' : 'Cancelar'}
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? 'Guardando...' : editingComponente ? 'Actualizar Tipo' : 'Crear'}
          </button>
        </div>
      </form>
    </div>
  </div>
{/if}
