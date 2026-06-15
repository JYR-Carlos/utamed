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

  const totalCriterios = rubrica?.niveles?.length ?? 0;
  const puntajeMaximo =
    rubrica?.detalles_evaluacion?.puntaje_total ??
    rubrica?.niveles?.reduce((acc, nivel) => acc + nivel.puntaje_total, 0) ??
    0;

  const maxEscalas = Math.max(
    ...(rubrica?.niveles?.map((n) => n.escalas.length) ?? [0])
  );

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
      <table class="table table-zebra w-full">
        <thead>
          <tr>
            <th class="min-w-72">Criterio</th>

            {#each Array(maxEscalas) as _, i}
              <th class="text-center">
                Nivel {i + 1}
              </th>
            {/each}
          </tr>
        </thead>

        <tbody>
          {#each rubrica.niveles as nivel}
            <tr>
              <td class="align-top">
                <div class="space-y-2">
                  <h4 class="font-semibold">
                    {nivel.nombre}
                  </h4>

                  {#if nivel.descripcion}
                    <p class="text-sm text-base-content/70">
                      {nivel.descripcion}
                    </p>
                  {/if}

                  <div class="text-xs text-base-content/60">
                    Máximo: {nivel.puntaje_total} pts
                  </div>
                </div>
              </td>

              {#each Array(maxEscalas) as _, index}
                <td class="align-top">
                  {#if nivel.escalas[index]}
                    {@const seleccionada = esSeleccionada(nivel.id, nivel.escalas[index].id)}
                    <div
                      class="rounded-xl border-2 p-3 h-full transition-colors
                        {seleccionada
                          ? 'border-emerald-500 bg-emerald-50'
                          : tieneResultado
                            ? 'border-base-200 bg-base-100/50 opacity-60'
                            : 'border-base-300'}"
                    >
                      <div class="font-bold {seleccionada ? 'text-emerald-700' : 'text-primary'} flex items-center gap-1.5">
                        {nivel.escalas[index].puntos} pts
                        {#if seleccionada}
                          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                          </svg>
                        {/if}
                      </div>

                      <p class="mt-2 text-sm">
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
