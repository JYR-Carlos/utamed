<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
    cod_actividad: string;
    nombre_actividad: string;
    nombre_curso: string;
    descripcion: string;
    fecha_limite: string;
    es_sumativa: boolean;
    entrega_obligatoria: boolean;
  }

  let {
    cod_actividad,
    nombre_actividad,
    nombre_curso,
    descripcion,
    fecha_limite,
    es_sumativa,
    entrega_obligatoria,
  }: Props = $props();
</script>

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
  <div class="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-500">
    <span class="font-mono text-slate-700">{cod_actividad}</span>
    <span class="text-slate-300">·</span>
    <span>{nombre_curso}</span>
  </div>

  <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
    {nombre_actividad}
  </h1>

  <div class="mt-4 flex flex-wrap gap-2">
    {#if es_sumativa}
      <span
        class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 border border-amber-100"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Sumativa
      </span>
    {:else}
      <span
        class="inline-flex items-center gap-1.5 rounded-md bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 border border-sky-100"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>Formativa
      </span>
    {/if}
    {#if entrega_obligatoria}
      <span
        class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-800 border border-rose-100"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Entrega obligatoria
      </span>
    {/if}
  </div>

  {#if descripcion}
    <div class="mt-6">
      <span class="text-xs font-bold tracking-wider text-slate-400 uppercase"
        >Descripción</span
      >
      <p class="mt-1 text-sm leading-relaxed text-slate-600">{descripcion}</p>
    </div>
  {/if}

  <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3 border-t border-slate-100 pt-6">
    <div class="space-y-1">
      <span class="text-xs font-medium text-slate-400">Fecha límite</span>
      <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
        <svg
          width="13"
          height="13"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        {fecha_limite}
      </span>
    </div>
    <div class="space-y-1">
      <span class="text-xs font-medium text-slate-400">Tipo</span>
      <span class="block text-sm font-semibold text-slate-700"
        >{es_sumativa ? 'Sumativa' : 'Formativa'}</span
      >
    </div>
    <div class="space-y-1">
      <span class="text-xs font-medium text-slate-400">Entrega</span>
      <span class="block text-sm font-semibold text-slate-700"
        >{entrega_obligatoria ? 'Obligatoria' : 'Opcional'}</span
      >
    </div>
  </div>
</div>
