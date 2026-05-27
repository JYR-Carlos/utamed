<script lang="ts">

  interface Props {
    es_sumativa: boolean;
    ultima_nota?: number | null;
    ultimo_estado?: string | null;
  }

  let { es_sumativa, ultima_nota, ultimo_estado }: Props = $props();

  function getColor(): string {
    // revisa si es sumativa y tiene nota para determinar el color de fondo
    if (es_sumativa && ultima_nota) {
      if (ultima_nota >= 4.0) return 'bg-green-100 text-green-900 border-green-300';
      if (ultima_nota < 4.0) return 'bg-red-100 text-red-900 border-red-300';
      return 'bg-red-100 text-red-900 border-red-300';
    }
    // si no es sumativa revisa los estados de retroalimentación
    const valorEstado = ultimo_estado?.toLowerCase();
    if (valorEstado === 'disponible')
      return 'bg-purple-100 text-purple-900 border-purple-300';
    if (valorEstado === 'pendiente' ||valorEstado === 'descargado' || valorEstado === 'enviado')
      return 'bg-blue-100 text-blue-900 border-blue-300';
    if (valorEstado === 'completado' || valorEstado === 'aprobado')
      return 'bg-green-100 text-green-900 border-green-300';
    if (valorEstado === 'reprobado')
      return 'bg-red-100 text-red-900 border-red-300';
    
    return 'bg-primary text-secondary border-primary'; // color por defecto
  }
</script>

<div
  class="mx-auto sm:w-[50%] size-50 md:size-60 lg:size-80 border-4 rounded-2xl flex items-center justify-center font-bold mt-6 text-8xl {getColor()}"
>
  {#if es_sumativa && ultima_nota}
    {ultima_nota?.toPrecision(2) ?? ''}

  {:else}
    {#if ultimo_estado === 'reprobado'}
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="size-40"
      >
        <!--ICONO REPROBADO-->
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="m4.5 12.75 6 6 9-13.5"
        />
      </svg>
    {:else if ultimo_estado === 'pendiente'}
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-40">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
      </svg>
    {:else if ultimo_estado === 'descargado'}
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-40">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
      </svg>
    {:else}
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="size-40"
      >
        <!--ICONO APROBADO/COMPLETADO-->
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
    {/if}
  {/if}
</div>
