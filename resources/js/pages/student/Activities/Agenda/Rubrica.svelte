<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
    rubrica?: Rubrica;
    estadoRubrica?: string;
  }

  let { rubrica, estadoRubrica }: Props = $props();

  const totalCriterios = rubrica?.niveles?.length ?? 0;
  const puntajeMaximo =
    rubrica?.detalles_evaluacion?.puntaje_total ??
    rubrica?.niveles?.reduce((acc, nivel) => acc + nivel.puntaje_total, 0) ??
    0;

  const maxEscalas = Math.max(
    ...(rubrica?.niveles?.map((n) => n.escalas.length) ?? [0])
  );

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
      class="grid grid-cols-1 md:grid-cols-3 gap-4 p-6 rounded-3xl border-2 border-primary/10"
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

      <div class="text-center">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
          Estado
        </p>
        <p class="text-lg font-semibold">
          {estadoRubrica ?? '—'}
        </p>
      </div>
    </div>

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
                    <div
                      class="rounded-xl border border-base-300 p-3 h-full"
                    >
                      <div class="font-bold text-primary">
                        {nivel.escalas[index].puntos} pts
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