<script lang="ts">
  /**
   * editAsignacionModal — Edita la posición de una asignatura dentro de la
   * malla de un plan: año, semestre y tipo de ramo.
   *
   * Hace el PUT directamente vía mallaApi.editAsignacion; el padre solo
   * recibe onSuccess para recargar la malla.
   */
  import type { AsignacionPlan } from '../types/mallaCurricular.types';
  import { TIPO_RAMO_LABELS } from '../types/mallaCurricular.types';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { editAsignacion } from '../services/mallaApi';

  interface Props {
    isOpen: boolean;
    planId: number;
    /** Asignación a editar; null mientras el modal está cerrado. */
    editingAsignacion: AsignacionPlan | null;
    /** Callback tras guardar (p.ej. recargar la malla). */
    onSuccess: () => void;
    /** Prefijo base de rutas (p.ej. '/admin' o '/docente/jefe-carrera'). */
    routePrefix?: string;
  }

  let {
    isOpen = $bindable(),
    planId,
    editingAsignacion,
    onSuccess,
    routePrefix = '/admin',
  }: Props = $props();

  // tipo_ramo admite string porque la opción "Sin tipo" del select usa ''
  // como valor; handleSubmit lo normaliza a number|null antes de enviar.
  let editForm = $state<{
    agno_planificado: number;
    semestre_planificado: number;
    tipo_ramo: number | string | null;
  }>({ agno_planificado: 1, semestre_planificado: 1, tipo_ramo: '' });
  let editLoading = $state(false);
  let editError = $state<string | null>(null);

  // Carga los datos de la asignación al abrir. Solo ESCRIBE editForm (no lo
  // lee), así que el efecto no se re-dispara mientras el usuario edita.
  $effect.pre(() => {
    if (isOpen && editingAsignacion) {
      editForm = {
        agno_planificado: editingAsignacion.agno_planificado,
        semestre_planificado: editingAsignacion.semestre_planificado,
        tipo_ramo: editingAsignacion.tipo_ramo ?? '',
      };
      editError = null;
    }
  });

  function handleClose() {
    isOpen = false;
    editError = null;
  }

  /** Normaliza tipo_ramo ('' → null) y envía el PUT. */
  function handleSubmit() {
    if (!editingAsignacion) return;
    editLoading = true;

    let tipoRamoValue: number | null = null;
    if (editForm.tipo_ramo && editForm.tipo_ramo !== '') {
      tipoRamoValue =
        typeof editForm.tipo_ramo === 'number'
          ? editForm.tipo_ramo
          : parseInt(String(editForm.tipo_ramo));
    }

    editAsignacion(
      planId,
      editingAsignacion.id_asignatura,
      {
        agno_planificado: editForm.agno_planificado,
        semestre_planificado: editForm.semestre_planificado,
        tipo_ramo: tipoRamoValue,
      },
      {
        onSuccess: () => {
          editLoading = false;
          isOpen = false;
          onSuccess();
        },
        onError: () => {
          editLoading = false;
          editError = 'Error al actualizar la asignación.';
        },
      },
      routePrefix,
    );
  }
</script>

<FormModal
  bind:isOpen
  title="Editar Asignación"
  onClose={handleClose}
  onSubmit={handleSubmit}
  isLoading={editLoading}
>
  {#if editError}
    <div class="mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">
      {editError}
    </div>
  {/if}
  {#if editingAsignacion}
    <p class="mb-4 text-sm text-gray-600">
      <span class="font-mono font-bold text-blue-600"
        >{editingAsignacion.asignatura?.cod_asignatura}</span
      >
      — {editingAsignacion.asignatura?.nombre}
    </p>
  {/if}
  <div class="grid grid-cols-2 gap-4 mb-4">
    <div>
      <label for="edit-agno" class="block text-sm font-medium text-gray-700 mb-1.5">Año *</label>
      <select
        bind:value={editForm.agno_planificado}
        id="edit-agno"
        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 bg-white"
        required
      >
        {#each Array.from({ length: 10 }, (_, i) => i + 1) as y}
          <option value={y}>{y}</option>
        {/each}
      </select>
    </div>
    <div>
      <label for="edit-semestre" class="block text-sm font-medium text-gray-700 mb-1.5"
        >Semestre *</label
      >
      <select
        bind:value={editForm.semestre_planificado}
        id="edit-semestre"
        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 bg-white"
        required
      >
        <option value={1}>1</option>
        <option value={2}>2</option>
      </select>
    </div>
  </div>
  <div>
    <label for="edit-tipo-ramo" class="block text-sm font-medium text-gray-700 mb-1.5"
      >Tipo de Ramo</label
    >
    <select
      bind:value={editForm.tipo_ramo}
      id="edit-tipo-ramo"
      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 bg-white"
    >
      <option value="">Sin tipo (opcional)</option>
      {#each Object.entries(TIPO_RAMO_LABELS) as [id, label]}
        <option value={Number(id)}>{label}</option>
      {/each}
    </select>
  </div>
</FormModal>
