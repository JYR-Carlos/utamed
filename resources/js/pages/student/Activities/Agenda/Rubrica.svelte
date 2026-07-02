<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
    rubrica?: Rubrica;
    estadoRubrica?: string;
    resultado?: Record<string, string> | null;
    puntaje_obtenido?: number | null;
    retroalimentacion?: string | null;
    modoLectura?: boolean;
  }

  let { rubrica, estadoRubrica, resultado, puntaje_obtenido, retroalimentacion, modoLectura = false }: Props = $props();

  const totalCriterios = $derived(rubrica?.niveles?.length ?? 0);
  const puntajeMaximo = $derived(
    rubrica?.detalles_evaluacion?.puntaje_total ??
      rubrica?.niveles?.reduce((acc, nivel) => acc + nivel.puntaje_total, 0) ??
      0,
  );

  const maxEscalas = $derived(Math.max(...(rubrica?.niveles?.map((n) => n.escalas.length) ?? [0])));

  const tieneResultado = $derived(!!resultado && Object.keys(resultado).length > 0);

  function esSeleccionada(nivelId: string, escalaId: string): boolean {
    return tieneResultado && resultado?.[nivelId] === escalaId;
  }
</script>

{#if !rubrica}
  <div
    class="rounded-2xl border border-dashed border-base-300 p-8 text-center text-base-content/60"
  >
    No hay rúbrica disponible
  </div>
{:else}
  <div class="space-y-6">
    <!-- Resumen -->
    <div
      class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 rounded-3xl border-2 border-primary/10"
    >
      <div class="text-center">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
          Criterios
        </p>
        <p class="text-3xl font-black text-primary">
          {totalCriterios}
        </p>
      </div>

      <div class="text-center">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
          Puntaje máximo
        </p>
        <p class="text-3xl font-black text-primary">
          {puntajeMaximo} pts
        </p>
      </div>

      {#if puntaje_obtenido != null}
        <div class="text-center">
          <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
            Puntaje obtenido
          </p>
          <p class="text-3xl font-black text-emerald-600">
            {puntaje_obtenido} pts
          </p>
        </div>
      {/if}

      <div class="text-center">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
          Estado
        </p>
        <p class="text-lg font-semibold">
          {estadoRubrica ?? '—'}
        </p>
      </div>
    </div>

    <!-- Retroalimentación del docente -->
    {#if retroalimentacion}
      <div class="rounded-2xl border border-primary/20 bg-primary/5 p-4">
        <p class="text-xs font-bold uppercase text-primary/70 tracking-widest mb-2">
          Retroalimentación
        </p>
        <p class="text-sm text-gray-700 leading-relaxed">{retroalimentacion}</p>
      </div>
    {/if}

    <!-- Tabla -->
    <div class="overflow-x-auto rounded-2xl border border-base-300">
      <table class="table w-full">
        <thead>
          <tr class="bg-base-200/60">
            <th class="min-w-64 text-sm font-semibold text-base-content/70 py-3 px-4">Criterio</th>

            {#each rubrica.niveles[0]?.escalas ?? [] as escala, i}
              <th class="text-center text-sm font-semibold text-base-content/70 py-3 px-4 min-w-44">
                Nivel {i + 1}
              </th>
            {/each}
          </tr>
        </thead>

        <tbody>
          {#each rubrica.niveles as nivel, nivelIdx}
            <tr class="border-t border-base-200 {nivelIdx % 2 === 0 ? 'bg-white' : 'bg-base-100/40'}">
              <td class="align-top py-4 px-4">
                <div class="space-y-1.5">
                  <h4 class="font-semibold text-base-content leading-snug">
                    {nivel.nombre}
                  </h4>

                  {#if nivel.descripcion}
                    <p class="text-sm text-base-content/60 leading-relaxed">
                      {nivel.descripcion}
                    </p>
                  {/if}

                  <div class="inline-flex items-center gap-1 text-xs font-medium text-base-content/50 bg-base-200 rounded-full px-2 py-0.5 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    Máx. {nivel.puntaje_total} pts
                  </div>
                </div>
              </td>

              {#each Array(maxEscalas) as _, index}
                <td class="align-top py-4 px-3">
                  {#if nivel.escalas[index]}
                    {@const seleccionada = esSeleccionada(nivel.id, nivel.escalas[index].id)}
                    <div
                      class="rounded-xl border-2 p-4 h-full transition-all
                        {seleccionada
                          ? 'border-emerald-400 bg-emerald-50 shadow-sm shadow-emerald-100'
                          : tieneResultado
                            ? 'border-base-200 bg-base-50 opacity-50'
                            : 'border-base-200 bg-base-50'}"
                    >
                      <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-sm font-bold {seleccionada ? 'text-emerald-700' : 'text-base-content/50'}">
                          {nivel.escalas[index].puntos} pts
                        </span>
                        {#if seleccionada}
                          <span class="flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-emerald-700 bg-emerald-100 rounded-full px-2 py-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                            Elegido
                          </span>
                        {/if}
                      </div>

                      <p class="text-sm leading-relaxed {seleccionada ? 'text-emerald-900 font-medium' : 'text-base-content/60'}">
                        {nivel.escalas[index].criterio}
                      </p>
                    </div>
                  {/if}
                </td>
              {/each}
            </tr>
          {/each}
        </tbody>
      </table>
    </div>

    <!-- Escala de evaluación -->
    {#if rubrica.detalles_evaluacion?.escala_evaluacion?.length}
      <div class="rounded-2xl border border-base-300 p-4">
        <h3 class="font-semibold mb-3">
          Escala de evaluación
        </h3>

        <div class="flex flex-wrap gap-3">
          {#each rubrica.detalles_evaluacion.escala_evaluacion as escala}
            <div class="badge badge-outline badge-lg">
              {escala.evaluacion} ({escala.puntaje_minimo}+ pts)
            </div>
          {/each}
        </div>
      </div>
    {/if}
  </div>
{/if}
