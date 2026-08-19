<script lang="ts">
  /**
   * asignaturaForm — Modal de creación/edición de asignaturas.
   *
   * - Crear:  POST {routePrefix}/asignaturas
   * - Editar: PUT  {routePrefix}/asignaturas/{id}. El backend no modifica el
   *   registro existente: crea una nueva versión de la asignatura y marca la
   *   anterior como histórica (de ahí el aviso "Creando nueva versión").
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { useForm } from '@inertiajs/svelte';
  import type { Asignatura } from '@/types/admin.types';
  import { untrack } from 'svelte';

  interface Props {
    isOpen?: boolean;
    /** Asignatura a editar; null para modo creación. */
    editingAsignatura?: Asignatura | null;
    /** Callback tras guardar con éxito (p.ej. recargar la lista). */
    onSuccess?: () => void;
    /** Callback al cerrar sin guardar; el padre es quien pone isOpen en false. */
    onClose?: () => void;
    /** Prefijo base de rutas (p.ej. '/admin' o '/docente/jefe-carrera'). */
    routePrefix?: string;
  }

  let {
    isOpen = $bindable(false),
    editingAsignatura = null,
    onSuccess = () => {},
    onClose = () => {},
    routePrefix = '/admin',
  }: Props = $props();

  /** Estado limpio del formulario (modo creación). */
  const valoresIniciales = {
    cod_asignatura: '',
    nombre: '',
    descripcion: '',
    creditos_sct: 0,
    horas_catedra: 0,
    horas_taller: 0,
    horas_laboratorio: 0,
    horas_dirigidas: 0,
    horas_autonomas: 0,
  };

  /** Valores del formulario a partir de una asignatura existente (modo edición). */
  function valoresDeAsignatura(asignatura: Asignatura) {
    return {
      cod_asignatura: asignatura.cod_asignatura,
      nombre: asignatura.nombre,
      descripcion: asignatura.descripcion || '',
      creditos_sct: asignatura.creditos_sct || 0,
      horas_catedra: asignatura.horas_catedra || 0,
      horas_taller: asignatura.horas_taller || 0,
      horas_laboratorio: asignatura.horas_laboratorio || 0,
      horas_dirigidas: asignatura.horas_dirigidas || 0,
      horas_autonomas: asignatura.horas_autonomas || 0,
    };
  }

  let formData = useForm({ ...valoresIniciales });

  // Sincroniza el formulario cada vez que se abre el modal. Dependencias
  // trackeadas: isOpen y editingAsignatura (leídas fuera de untrack).
  //
  // Las mutaciones de $formData DEBEN ir dentro de untrack(): leer $formData
  // aquí suscribe el efecto al store del formulario, y defaults()/reset() lo
  // actualizan, así que sin untrack el efecto se re-dispara a sí mismo en
  // bucle (effect_update_depth_exceeded) y el modal queda sin poder cerrarse.
  //
  // defaults() persiste entre aperturas, por eso en modo creación se
  // restablecen explícitamente los valoresIniciales: si no, reset() dejaría
  // los datos de la última asignatura editada.
  $effect(() => {
    if (!isOpen) return;
    const asignatura = editingAsignatura;
    untrack(() => {
      $formData.defaults(asignatura ? valoresDeAsignatura(asignatura) : { ...valoresIniciales });
      $formData.reset();
      $formData.clearErrors();
    });
  });

  /** Limpia el formulario y delega el cierre al padre (dueño de isOpen). */
  function handleClose() {
    $formData.reset();
    $formData.clearErrors();
    onClose();
  }

  /** Envía el formulario: POST crea una asignatura, PUT genera una nueva versión. */
  function handleSubmit() {
    const url = editingAsignatura
      ? `${routePrefix}/asignaturas/${editingAsignatura.id_asignatura}`
      : `${routePrefix}/asignaturas`;

    const opts = {
      onSuccess: () => {
        isOpen = false;
        onSuccess();
      },
    };

    if (editingAsignatura) {
      $formData.put(url, opts);
    } else {
      $formData.post(url, opts);
    }
  }

  /** Clases compartidas por todos los campos del formulario. */
  const inputClass =
    'w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';
</script>

<FormModal
  bind:isOpen
  title={editingAsignatura ? 'Crear Nueva Versión de Asignatura' : 'Nueva Asignatura'}
  onClose={handleClose}
  onSubmit={handleSubmit}
  isLoading={$formData.processing}
>
  {#if editingAsignatura}
    <div class="mb-4 flex gap-3 items-start bg-blue-50 border border-blue-300 rounded-lg px-4 py-3">
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
        class="text-blue-500 mt-0.5 shrink-0"
        ><circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line
          x1="12"
          y1="8"
          x2="12.01"
          y2="8"
        /></svg
      >
      <div>
        <p class="text-sm font-semibold text-blue-800">Creando nueva versión</p>
        <p class="text-xs text-blue-700 mt-0.5">
          Los cambios crearán una <strong>nueva versión</strong> de la asignatura. La versión anterior
          será marcada como histórica. Esto preserva el historial completo de cambios.
        </p>
      </div>
    </div>
  {/if}

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="cod_asignatura" class="block text-sm font-medium text-gray-700 mb-2">Código</label
      >
      <input
        id="cod_asignatura"
        type="text"
        bind:value={$formData.cod_asignatura}
        class={inputClass}
        class:border-red-500={$formData.errors.cod_asignatura}
        placeholder="Ej: MED101"
        required
      />
      {#if $formData.errors.cod_asignatura}
        <p class="text-red-500 text-sm">{$formData.errors.cod_asignatura}</p>
      {/if}
    </div>

    <div class="mb-4">
      <label for="creditos_sct" class="block text-sm font-medium text-gray-700 mb-2"
        >Créditos SCT</label
      >
      <input
        id="creditos_sct"
        type="number"
        bind:value={$formData.creditos_sct}
        class={inputClass}
        class:border-red-500={$formData.errors.creditos_sct}
        min="0"
      />
      {#if $formData.errors.creditos_sct}
        <p class="text-red-500 text-sm">{$formData.errors.creditos_sct}</p>
      {/if}
    </div>
  </div>

  <div class="mb-4">
    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
    <input
      id="nombre"
      type="text"
      bind:value={$formData.nombre}
      class={inputClass}
      class:border-red-500={$formData.errors.nombre}
      placeholder="Ej: Anatomía Humana"
      required
    />
    {#if $formData.errors.nombre}
      <p class="text-red-500 text-sm">{$formData.errors.nombre}</p>
    {/if}
  </div>

  <div class="mb-4">
    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label
    >
    <textarea
      id="descripcion"
      bind:value={$formData.descripcion}
      class={inputClass}
      rows="3"
      placeholder="Descripción de la asignatura"
    ></textarea>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="horas_catedra" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Cátedra</label
      >
      <input
        id="horas_catedra"
        type="number"
        bind:value={$formData.horas_catedra}
        class={inputClass}
        min="0"
      />
    </div>

    <div class="mb-4">
      <label for="horas_taller" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Taller</label
      >
      <input
        id="horas_taller"
        type="number"
        bind:value={$formData.horas_taller}
        class={inputClass}
        min="0"
      />
    </div>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="horas_laboratorio" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Laboratorio</label
      >
      <input
        id="horas_laboratorio"
        type="number"
        bind:value={$formData.horas_laboratorio}
        class={inputClass}
        min="0"
      />
    </div>

    <div class="mb-4">
      <label for="horas_dirigidas" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Dirigidas</label
      >
      <input
        id="horas_dirigidas"
        type="number"
        bind:value={$formData.horas_dirigidas}
        class={inputClass}
        min="0"
      />
    </div>
  </div>

  <div class="mb-4">
    <label for="horas_autonomas" class="block text-sm font-medium text-gray-700 mb-2"
      >Horas Autónomas</label
    >
    <input
      id="horas_autonomas"
      type="number"
      bind:value={$formData.horas_autonomas}
      class={inputClass}
      min="0"
    />
  </div>
</FormModal>
