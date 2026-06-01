<script lang="ts">
  import type { Nivel, Rubrica } from '@/types/rubrica';

  interface Props {
    rubrica: Rubrica;
    puntaje_obtenido?: number;
    retroalimentacion?: string;
    modoLectura?: boolean;
  }

  let {
    rubrica,
    puntaje_obtenido = 0,
    retroalimentacion,
    modoLectura: modoRevision = false,
  }: Props = $props();

  function getEvaluacion(puntos: number) {
    if (!rubrica?.detalles_evaluacion?.escala_evaluacion) return 'Sin evaluar';
    const escalas = [...rubrica.detalles_evaluacion.escala_evaluacion].reverse();
    return escalas.find((e) => puntos >= e.puntaje_minimo)?.evaluacion || 'Sin evaluar';
  }

  const maxEscalas = rubrica?.niveles?.[0]?.escalas.length || 0;

  // para evitar que la tabla se rompa horizontalmente.
  const mostrarVertical = maxEscalas >= 4;
</script>

<div class="w-fullspace-y-8 p-1">
  {#if modoRevision}
    <div
      class="flex justify-center sm:gap-20 text-center p-6 rounded-3xl border-2 border-primary/10"
    >
      <div class="text-center col-span-5 md:text-left">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Puntaje Máximo</p>
        <p class="text-3xl font-black text-primary">
          {rubrica?.detalles_evaluacion?.puntaje_total || 0} pts
        </p>
      </div>
      <div class="text-center col-span-5 md:text-left">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
          Porcentaje de Exigencia
        </p>
        <p class="text-3xl font-black text-primary">60%</p>
      </div>
    </div>
  {:else}
    <div
      class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-primary/5 p-6 rounded-3xl border-2 border-primary/10"
    >
      <div class="text-center md:text-left">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Puntaje Máximo</p>
        <p class="text-3xl font-black text-primary">
          {rubrica?.detalles_evaluacion?.puntaje_total || 0} pts
        </p>
      </div>
      <div class="text-center border-y md:border-y-0 md:border-x border-primary/10 py-4 md:py-0">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Puntaje Obtenido</p>
        <p class="text-3xl font-black {puntaje_obtenido > 0 ? 'text-blue-600' : 'text-gray-300'}">
          {puntaje_obtenido} pts
        </p>
      </div>
      <div class="text-center md:text-right">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Calificación</p>
        <p class="text-xl font-bold text-primary">{getEvaluacion(puntaje_obtenido)}</p>
      </div>
    </div>
  {/if}

  <div class="space-y-6">
    {#if rubrica?.niveles}
      <div class="{mostrarVertical ? 'block' : 'block md:hidden'} space-y-6">
        {#each rubrica.niveles as nivel}
          <div class="border-2 border-gray-100 rounded-3xl overflow-hidden bg-white shadow-sm">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
              <div>
                <h3 class="text-lg font-bold text-gray-800">{nivel.nombre}</h3>
                <p class="text-sm text-gray-500 italic">{nivel.descripcion}</p>
              </div>
              <span
                class="bg-primary text-secondary px-3 py-1 rounded-full text-xs font-bold shrink-0 ml-2"
              >
                {nivel.puntaje_total} pts
              </span>
            </div>

            <div class="grid grid-cols-1 divide-y divide-gray-100">
              {#each nivel.escalas as escala}
                <div class="p-5 flex flex-col gap-2">
                  <span
                    class="text-xs font-black px-2 py-1 bg-gray-200 rounded text-gray-600 w-fit"
                  >
                    {escala.puntos} pts
                  </span>
                  <p class="text-sm text-gray-600 leading-relaxed">{escala.criterio}</p>
                </div>
              {/each}
            </div>
          </div>
        {/each}
      </div>

      <div
        class="{mostrarVertical
          ? 'hidden'
          : 'hidden md:block'} overflow-hidden border-2 border-gray-100 rounded-3xl bg-white shadow-sm"
      >
        <table class="w-full border-collapse table-fixed">
          <thead>
            <tr class="bg-gray-50 border-b-2 border-gray-100">
              <th
                class="p-6 text-left text-sm font-black text-gray-500 uppercase tracking-wider w-1/4"
              >
                Criterio de Evaluación
              </th>
              <th
                colspan={maxEscalas}
                class="p-6 text-center text-sm font-black text-gray-500 uppercase tracking-wider"
              >
                Niveles de Desempeño
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            {#each rubrica.niveles as nivel}
              <tr class="hover:bg-gray-50/30 transition-colors">
                <td class="p-6 align-top">
                  <div class="flex flex-col gap-1">
                    <p class="font-bold text-gray-800 leading-tight">{nivel.nombre}</p>
                    <p class="text-xs text-gray-500 italic leading-relaxed">{nivel.descripcion}</p>
                    <span
                      class="mt-2 text-[10px] font-bold text-primary uppercase tracking-tighter"
                    >
                      Máximo: {nivel.puntaje_total} pts
                    </span>
                  </div>
                </td>
                {#each nivel.escalas as escala}
                  <td class="p-5 align-top border-l border-gray-50">
                    <div class="flex flex-col gap-3">
                      <span
                        class="text-[10px] font-black px-2 py-0.5 bg-primary-100 text-primary rounded-md self-start uppercase"
                      >
                        {escala.puntos} pts
                      </span>
                      <p class="text-sm text-gray-600 leading-snug">
                        {escala.criterio}
                      </p>
                    </div>
                  </td>
                {/each}
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  </div>

  {#if retroalimentacion && !modoRevision}
    <div class="mt-8 bg-gray-50/50 p-6 rounded-3xl border border-gray-100">
      <p class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">
        Retroalimentación del Docente:
      </p>
      <div class="flex flex-wrap gap-3">
        <p>{retroalimentacion}</p>
      </div>
    </div>
  {/if}

  <div class="mt-8 bg-gray-50/50 p-6 rounded-3xl border border-gray-100">
    <p class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">
      Escala de calificación aplicada:
    </p>
    <div class="flex flex-wrap gap-3">
      {#if rubrica?.detalles_evaluacion?.escala_evaluacion}
        {#each rubrica.detalles_evaluacion.escala_evaluacion as esc}
          <div
            class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-200 shadow-sm"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
            <span class="text-xs font-bold text-gray-700">{esc.evaluacion}</span>
            <span class="text-xs text-gray-400 ml-1">+{esc.puntaje_minimo} pts</span>
          </div>
        {/each}
      {/if}
    </div>
  </div>
</div>
