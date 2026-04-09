<script lang="ts">
  // Importamos únicamente la constante (el objeto)
  import { UserType } from '@/types/usuarios/tipos';

  interface Props {
    // Extraemos el tipo dinámicamente de los valores de la constante
    tipo: typeof UserType[keyof typeof UserType];
    file?: File | null;
  }


  let { tipo, file = $bindable(null) }: Props = $props();

  // Mapeo dinámico usando la misma técnica para asegurar las claves
  const labelMapping: Record<typeof UserType[keyof typeof UserType], string> = {
    [UserType.STUDENT]: 'Estudiantes',
    [UserType.TEACHER]: 'Docentes',
    [UserType.ADMIN]: 'Administradores'
  };

  let currentLabel = $derived(labelMapping[tipo]);
  let fileInput: HTMLInputElement;

  function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
      file = target.files[0];
    } else {
      file = null;
    }
  }
</script>

<div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
  <h3 class="text-lg font-medium text-gray-900 mb-4">
    Importación Masiva de {currentLabel}
  </h3>

  <div class="mb-4">
    <p class="block text-sm font-medium text-gray-700 mb-2">
      Selecciona un archivo (.xlsx o .csv)
    </p>
    <div class="flex items-center">
      <input
        type="file"
        accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
        bind:this={fileInput}
        onchange={handleFileChange}
        class="block w-full text-md text-gray-500
            file:mr-4 file:py-2 file:px-4
            file:rounded-md file:border-0
            file:text-sm file:font-semibold
            file:bg-green-50 file:text-green-700
            hover:file:bg-green-100 transition-all"
      />
      
      {#if file}
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-green-600 ml-2 shrink-0">
          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>
      {/if}
    </div>
  </div>
</div>