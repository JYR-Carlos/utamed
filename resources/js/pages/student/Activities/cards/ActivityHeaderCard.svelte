<script lang="ts">
  interface Props {
    nombre_actividad: string;
    nombre_curso: string;
    descripcion: string;
    es_sumativa: boolean;
    entrega_obligatoria: boolean;
    estado?: string | null;
  }

  let {
    nombre_actividad,
    nombre_curso,
    descripcion,
    es_sumativa,
    entrega_obligatoria,
    estado,
  }: Props = $props();

  const estadoInfo = $derived(
    estado === 'CERRADA'
      ? { label: 'Cerrada', badge: 'bg-slate-100 text-slate-600 border-slate-300', dot: 'bg-slate-500' }
      : { label: 'Activa', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-600' },
  );
</script>

<section class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm md:p-6">
  <div class="flex items-start gap-4">
    <div class="flex min-w-0 flex-col gap-1">
      <span class="text-[11.5px] font-medium text-[#5A5E6E]">{nombre_curso}</span>
      <h1 class="text-xl font-semibold leading-tight text-[#1A1A24] md:text-2xl">{nombre_actividad}</h1>
    </div>
    <span
      class="ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {estadoInfo.badge}"
    >
      <span class="h-1.5 w-1.5 rounded-full {estadoInfo.dot}"></span>
      {estadoInfo.label}
    </span>
  </div>

  <div class="flex flex-wrap gap-2">
    <span
      class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold
      {es_sumativa ? 'border-amber-100 bg-amber-50 text-amber-800' : 'border-sky-100 bg-sky-50 text-sky-800'}"
    >
      <span class="h-1.5 w-1.5 rounded-full {es_sumativa ? 'bg-amber-500' : 'bg-sky-500'}"></span>
      {es_sumativa ? 'Sumativa' : 'Formativa'}
    </span>
    {#if entrega_obligatoria}
      <span
        class="inline-flex items-center gap-1.5 rounded-md border border-red-100 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
        Entrega obligatoria
      </span>
    {/if}
  </div>

  {#if descripcion}
    <p class="text-sm leading-relaxed text-[#1A1A24]">{descripcion}</p>
  {/if}
</section>
