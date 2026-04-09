<script lang="ts">
  /**
   * Componente de formulario genérico para usuarios.
   *
   * Renderiza campos diferentes según el tipo de usuario:
   * - Estudiante: nombre, rut, carrera, año ingreso
   * - Docente: nombre, rut, título, grado, cargo
   * - Administrador: nombre, rut
   */
  import type {
    EstudianteFormData,
    DocenteFormData,
    AdministradorFormData,
    Carrera,
  } from '@/types/admin.types';

  type UserFormData = EstudianteFormData | DocenteFormData | AdministradorFormData;

  interface Props {
    formData: UserFormData;
    deshabilitarCampos: boolean;
    tipo: 'estudiante' | 'docente' | 'administrador';
    isEditing: boolean;
    carreras?: Carrera[];
  }

  let { formData, deshabilitarCampos,tipo, isEditing, carreras = [] }: Props = $props();

  const inputClass =
    'w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';

  // Formatea el RUT al formato 00000000-0 (sin puntos)
  function formatRut(rut: string): string {
    // Eliminar puntos y guiones
    const cleanRut = rut.replace(/[.\-]/g, '');

    // Validar formato básico (7-8 dígitos + dígito verificador o K)
    if (!/^\d{7,8}[0-9kK]?$/.test(cleanRut)) {
      return rut; // Retornar sin formatear si no es válido
    }

    // Si tiene menos de 8 dígitos antes del verificador, no formatear aún
    if (cleanRut.length < 9) {
      return cleanRut;
    }

    // Extraer cuerpo y dígito verificador
    const body = cleanRut.slice(0, -1);
    const dv = cleanRut.slice(-1).toUpperCase();

    // Formatear como 00000000-0 (sin puntos)
    return `${body}-${dv}`;
  }

  // Valida que el RUT tenga el formato correcto
  function isValidRutFormat(rut: string): boolean {
    const cleanRut = rut.replace(/[.\-]/g, '');
    return /^\d{7}[0-9kK]$/.test(cleanRut);
  }

  // Manejar cambios en el input de RUT
  function handleRutInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const formatted = formatRut(target.value);
    if (formatted !== target.value) {
      target.value = formatted;
      formData.rut = formatted;
    }
  }

  let rutValid = $derived(isValidRutFormat(formData.rut));
</script>

<!-- Campos Comunes -->
<div class="mb-4">
  <div class="flex justify-between items-baseline mb-2">
    <label for="rut" class="block text-sm font-medium text-gray-700">RUT *</label>
    <span class="text-xs text-gray-500">Formato: 12345678-K (8 dígitos + verificador)</span>
  </div>
  {#if deshabilitarCampos}
    mmmm
  {/if}
  <input
    id="rut"
    type="text"
    bind:value={formData.rut}
    oninput={handleRutInput}
    class={inputClass}
    maxlength="10"
    placeholder="12345678-K"
    required
  />
  {#if formData.rut && !rutValid}
    <p class="text-xs text-red-600 mt-1">⚠ RUT debe tener 8 dígitos + dígito verificador</p>
  {:else if formData.rut && rutValid}
    <p class="text-xs text-green-600 mt-1">✓ Formato válido</p>
  {/if}
</div>

<div class="grid grid-cols-2 gap-4">
  <div class="mb-4">
    <label for="nombre1" class="block text-sm font-medium text-gray-700 mb-2">Primer Nombre *</label
    >
    <input
      id="nombre1"
      type="text"
      bind:value={formData.nombre1}
      class={inputClass}
      placeholder="Ej: Juan"
      readonly={deshabilitarCampos}
      required
    />
  </div>

  <div class="mb-4">
    <label for="nombre2" class="block text-sm font-medium text-gray-700 mb-2">Segundo Nombre</label>
    <input
      id="nombre2"
      type="text"
      bind:value={formData.nombre2}
      class={inputClass}
      readonly={deshabilitarCampos}
      placeholder="Ej: Carlos"
    />
  </div>
</div>

<div class="grid grid-cols-2 gap-4">
  <div class="mb-4">
    <label for="apellido1" class="block text-sm font-medium text-gray-700 mb-2"
      >Primer Apellido *</label
    >
    <input
      id="apellido1"
      type="text"
      bind:value={formData.apellido1}
      class={inputClass}
      placeholder="Ej: González"
      readonly={deshabilitarCampos}
      required
    />
  </div>

  <div class="mb-4">
    <label for="apellido2" class="block text-sm font-medium text-gray-700 mb-2"
      >Segundo Apellido</label
    >
    <input
      id="apellido2"
      type="text"
      bind:value={formData.apellido2}
      class={inputClass}
      readonly={deshabilitarCampos}
      placeholder="Ej: Pérez"
    />
  </div>
</div>

<div class="mb-4">
  <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
  <input
    id="email"
    type="email"
    bind:value={formData.email}
    class={inputClass}
    readonly={deshabilitarCampos}
    placeholder="Ej: juan@ejemplo.com"
  />
</div>

<!-- Campos Específicos por Tipo -->
{#if tipo === 'estudiante'}
  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="agno_ingreso" class="block text-sm font-medium text-gray-700 mb-2"
        >Año de Ingreso</label
      >
      <input
        id="agno_ingreso"
        type="number"
        bind:value={(formData as EstudianteFormData).agno_ingreso}
        class={inputClass}
        min="1900"
        max="2100"
        placeholder="Ej: 2024"
      />
    </div>

    <div class="mb-4">
      <label for="carrera" class="block text-sm font-medium text-gray-700 mb-2">Carrera</label>
      <select
        id="carrera"
        bind:value={(formData as EstudianteFormData).id_carrera}
        class={inputClass}
      >
        <option value={undefined}>Sin carrera</option>
        {#each carreras as carrera}
          <option value={carrera.id_carrera}>{carrera.nombre}</option>
        {/each}
      </select>
    </div>
  </div>
{:else if tipo === 'docente'}
  <div class="mb-4">
    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título</label>
    <input
      id="titulo"
      type="text"
      bind:value={(formData as DocenteFormData).titulo}
      class={inputClass}
      placeholder="Ej: Ingeniero Civil"
    />
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="grado" class="block text-sm font-medium text-gray-700 mb-2">Grado Académico</label
      >
      <input
        id="grado"
        type="text"
        bind:value={(formData as DocenteFormData).grado}
        class={inputClass}
        placeholder="Ej: Doctor en Medicina"
      />
    </div>

    <div class="mb-4">
      <label for="cargo" class="block text-sm font-medium text-gray-700 mb-2">Cargo</label>
      <input
        id="cargo"
        type="text"
        bind:value={(formData as DocenteFormData).cargo}
        class={inputClass}
        placeholder="Ej: Profesor Titular"
      />
    </div>
  </div>
{/if}

<!-- Credenciales (solo crear, no editar) -->
{#if !isEditing}
  <div class="h-px bg-gray-200 my-4 mb-4"></div>
  <p class="text-sm font-semibold text-gray-700 mb-3">Credenciales de Acceso</p>

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Usuario *</label>
      <input
        id="username"
        type="text"
        bind:value={formData.username}
        class={inputClass}
        placeholder={tipo === 'administrador' ? 'Máx. 30 caracteres' : 'Máx. 10 caracteres'}
        maxlength={tipo === 'administrador' ? 30 : 10}
        required
        readonly={deshabilitarCampos}
      />
    </div>

    <div class="mb-4">
      <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña *</label
      >
      <input
        id="password"
        type="password"
        bind:value={formData.password}
        class={inputClass}
        placeholder="Mín. 6 caracteres"
        minlength="6"
        required
      />
    </div>
  </div>
{/if}
