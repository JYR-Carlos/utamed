<script lang="ts">

  interface Props {
    usuarios: Array<{
        id_estudiante: number,
        nombre1: string,
        nombre2: string,
        apellido1: string,
        apellido2: string
    }>;
  }

  let { usuarios } = $props();

  const ocultar = $derived(usuarios.length === 0)
</script>

{#if !ocultar}
<div class="flex flex-col w-full  gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-left text-sm font-semibold text-slate-700 shadow-sm transition-all">
  <p class="text-sm">Otros Integrantes</p>
  {#each usuarios as u}
    <div class="flex justify-between items-center">
        <p class="font-normal text-wrap wrap-break-word">{u.nombre1 + " " + u.nombre2 + " " + u.apellido1 + " " + u.apellido2}</p>
        
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
        </svg>

    </div>
    
  {/each}
</div>
{/if}