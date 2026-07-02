<script lang="ts">
  /**
   * actividadForm — Modal de creación/edición de actividades de un curso.
   *
   * Componente controlado: no hace peticiones HTTP. Valida los selects
   * obligatorios en cliente y delega en onSubmit(data); el padre
   * (pages/docente/Actividades.svelte) ejecuta el POST/PUT vía actividadApi
   * y devuelve los errores del servidor por la prop errors.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { Checkbox } from '@/components/ui/checkbox';
  import { Label } from '@/components/ui/label';
  import type { Actividad } from '@/types/actividad';

  interface Componente {
    id_componente: number;
    tipo_componente?: { tipo: string } | null;
  }

  interface Unidad {
    id_unidad: number;
    nombre: string;
  }

  interface Props {
    isOpen?: boolean;
    /** Enviando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    /** Actividad a editar; null para modo creación. */
    editingActividad?: Actividad | null;
    /** Componentes del curso, opciones del select obligatorio. */
    componentes?: Componente[];
    /** Unidades del curso, opciones del select obligatorio. */
    unidades?: Unidad[];
    /** Errores de validación del servidor; la clave 'error' es mensaje global. */
    errors?: Record<string, string>;
    onClose?: () => void;
    /** Recibe el formulario ya validado en cliente. */
    onSubmit?: (data: Partial<Actividad>) => void;
  }

  let {
    isOpen = $bindable(false),
    isLoading = false,
    editingActividad = null,
    componentes = [],
    unidades = [],
    errors = {},
    onClose = () => {},
    onSubmit = () => {},
  }: Props = $props();

  /** Errores de validación en cliente; los del servidor llegan por props.errors. */
  let clientErrors = $state<Record<string, string>>({});

  /** Estado limpio del formulario (modo creación). */
  const valoresIniciales: Partial<Actividad> = {
    nombre: '',
    fecha_limite: '',
    tipo_actividad: 'SUMATIVA',
    tipo_entrega: 'online',
    es_grupal: false,
    max_integrantes: 1,
    visible: true,
    ponderacion: 0,
    exigencia: 60,
    id_componente: 0,
    id_unidad: 0,
  };

  let formData = $state<Partial<Actividad>>({ ...valoresIniciales });

  // Repuebla el formulario al abrir el modal (trackea isOpen y
  // editingActividad). Aquí formData/clientErrors solo se ESCRIBEN, nunca se
  // leen, así que el efecto no se re-dispara mientras el usuario escribe.
  $effect.pre(() => {
    if (isOpen) {
      clientErrors = {};
      if (editingActividad) {
        // El input type="date" exige YYYY-MM-DD; el backend puede enviar ISO completo.
        const fecha = editingActividad.fecha_limite;
        const fechaFormato = fecha ? (typeof fecha === 'string' ? fecha.split('T')[0] : fecha) : '';

        formData = {
          ...editingActividad,
          fecha_limite: fechaFormato,
        };
      } else {
        formData = { ...valoresIniciales };
      }
    }
  });

  /** Valida los selects obligatorios y delega el envío al padre. */
  function handleSubmit() {
    clientErrors = {};
    const errs: Record<string, string> = {};
    if (!formData.id_componente || formData.id_componente === 0) {
      errs.id_componente = 'Debe seleccionar un componente';
    }
    if (!formData.id_unidad || formData.id_unidad === 0) {
      errs.id_unidad = 'Debe seleccionar una unidad';
    }
    if (Object.keys(errs).length > 0) {
      clientErrors = errs;
      return;
    }
    onSubmit(formData);
  }

  /** Clases base de los campos; el color del borde lo decide cada campo según error. */
  const inputClass =
    'w-full px-3.5 py-2.5 border rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all';
</script>

<FormModal
  bind:isOpen
  title={editingActividad ? 'Editar Actividad' : 'Nueva Actividad'}
  {onClose}
  onSubmit={handleSubmit}
  {isLoading}
>
  {#if errors.error}
    <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3">
      <p class="text-sm text-red-700">{errors.error}</p>
    </div>
  {/if}

  <div class="mb-4">
    <label for="nombre" class="block font-medium text-gray-700 mb-2 text-sm"
      >Nombre de la Actividad *</label
    >
    <input
      id="nombre"
      type="text"
      bind:value={formData.nombre}
      class="{inputClass} {errors.nombre ? 'border-red-400' : 'border-gray-300'}"
      placeholder="Ej: Tarea 1, Evaluación Parcial"
      required
    />
    {#if errors.nombre}<p class="mt-1 text-xs text-red-600">{errors.nombre}</p>{/if}
  </div>

  <div class="mb-4">
    <label for="tipo_actividad" class="block font-medium text-gray-700 mb-2 text-sm"
      >Tipo de Actividad *</label
    >
    <select
      id="tipo_actividad"
      bind:value={formData.tipo_actividad}
      class="{inputClass} border-gray-300"
      required
    >
      <option value="SUMATIVA">Sumativa</option>
      <option value="FORMATIVA">Formativa</option>
    </select>
    {#if errors.tipo_actividad}<p class="mt-1 text-xs text-red-600">{errors.tipo_actividad}</p>{/if}
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="fecha_limite" class="block font-medium text-gray-700 mb-2 text-sm"
        >Fecha Límite *</label
      >
      <input
        id="fecha_limite"
        type="date"
        bind:value={formData.fecha_limite}
        class="{inputClass} {errors.fecha_limite ? 'border-red-400' : 'border-gray-300'}"
        required
      />
      {#if errors.fecha_limite}<p class="mt-1 text-xs text-red-600">{errors.fecha_limite}</p>{/if}
    </div>

    <div class="mb-4">
      <label for="tipo_entrega" class="block font-medium text-gray-700 mb-2 text-sm"
        >Tipo de Entrega *</label
      >
      <select
        id="tipo_entrega"
        bind:value={formData.tipo_entrega}
        class="{inputClass} border-gray-300"
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
      <label for="ponderacion" class="block font-medium text-gray-700 mb-2 text-sm">
        Ponderación (%) *
        <span class="text-gray-400 font-normal text-xs ml-1">Debe sumar 100 en el componente</span>
      </label>
      <input
        id="ponderacion"
        type="number"
        min="0"
        max="100"
        bind:value={formData.ponderacion}
        class="{inputClass} {errors.ponderacion ? 'border-red-400' : 'border-gray-300'}"
        placeholder="Ej: 30"
        required
      />
      {#if errors.ponderacion}<p class="mt-1 text-xs text-red-600">{errors.ponderacion}</p>{/if}
    </div>

    <div class="mb-4">
      <label for="exigencia" class="block font-medium text-gray-700 mb-2 text-sm">
        Exigencia (%) *
        <span class="text-gray-400 font-normal text-xs ml-1">Umbral de aprobación</span>
      </label>
      <input
        id="exigencia"
        type="number"
        min="0"
        max="100"
        bind:value={formData.exigencia}
        class="{inputClass} {errors.exigencia ? 'border-red-400' : 'border-gray-300'}"
        placeholder="Ej: 60"
        required
      />
      {#if errors.exigencia}<p class="mt-1 text-xs text-red-600">{errors.exigencia}</p>{/if}
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="id_componente" class="block font-medium text-gray-700 mb-2 text-sm"
        >Componente *</label
      >
      <select
        id="id_componente"
        bind:value={formData.id_componente}
        class="{inputClass} {errors.id_componente || clientErrors.id_componente
          ? 'border-red-400'
          : 'border-gray-300'}"
        required
      >
        <option value={0}>Seleccione un componente</option>
        {#each componentes as comp}
          <option value={comp.id_componente}>
            {comp.tipo_componente?.tipo ?? `Componente ${comp.id_componente}`}
          </option>
        {/each}
      </select>
      {#if componentes.length === 0}
        <p class="mt-1 text-xs text-amber-600">Este curso no tiene componentes registrados.</p>
      {:else if errors.id_componente || clientErrors.id_componente}
        <p class="mt-1 text-xs text-red-600">
          {errors.id_componente ?? clientErrors.id_componente}
        </p>
      {/if}
    </div>

    <div class="mb-4">
      <label for="id_unidad" class="block font-medium text-gray-700 mb-2 text-sm">Unidad *</label>
      <select
        id="id_unidad"
        bind:value={formData.id_unidad}
        class="{inputClass} {errors.id_unidad || clientErrors.id_unidad
          ? 'border-red-400'
          : 'border-gray-300'}"
        required
      >
        <option value={0}>Seleccione una unidad</option>
        {#each unidades as unidad}
          <option value={unidad.id_unidad}>
            {unidad.nombre}
          </option>
        {/each}
      </select>
      {#if unidades.length === 0}
        <p class="mt-1 text-xs text-amber-600">Este curso no tiene unidades registradas.</p>
      {:else if errors.id_unidad || clientErrors.id_unidad}
        <p class="mt-1 text-xs text-red-600">{errors.id_unidad ?? clientErrors.id_unidad}</p>
      {/if}
    </div>
  </div>

  <div class="mb-4 flex items-center gap-2">
    <Checkbox
      id="es_grupal"
      checked={Boolean(formData.es_grupal)}
      onCheckedChange={(v) => {
        // Grupal exige al menos 2 integrantes; volver a individual fuerza 1.
        formData.es_grupal = v;
        if (v && (formData.max_integrantes ?? 1) < 2) {
          formData.max_integrantes = 2;
        } else if (!v) {
          formData.max_integrantes = 1;
        }
      }}
    />
    <Label for="es_grupal" class="text-sm text-gray-700 cursor-pointer font-normal">
      Es una actividad grupal
    </Label>
  </div>

  {#if formData.es_grupal}
    <div class="mb-4">
      <label for="max_integrantes" class="block font-medium text-gray-700 mb-2 text-sm"
        >Máximo de Integrantes</label
      >
      <input
        id="max_integrantes"
        type="number"
        min="2"
        max="10"
        bind:value={formData.max_integrantes}
        class="{inputClass} border-gray-300"
      />
    </div>
  {/if}

  <div class="mb-4 flex items-center gap-2">
    <Checkbox
      id="visible"
      checked={Boolean(formData.visible)}
      onCheckedChange={(v) => {
        formData.visible = v;
      }}
    />
    <Label for="visible" class="text-sm text-gray-700 cursor-pointer font-normal">
      Visible para estudiantes
    </Label>
  </div>
</FormModal>
