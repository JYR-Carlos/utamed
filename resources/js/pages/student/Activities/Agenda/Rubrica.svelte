<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';
  import { puntajeMinimoAprobacion } from '@/lib/notas';

  interface Props {
    rubrica?: Rubrica;
    estadoRubrica?: string;
    resultado?: Record<string, string> | null;
    puntaje_obtenido?: number | null;
    retroalimentacion?: string | null;
    modoLectura?: boolean;
    /**
     * Sumativa → se explica el corte de la nota 4,0 y no se muestra escala
     * cualitativa. Formativa → al revés.
     *
     * `undefined` cuando quien dibuja la rúbrica no sabe de qué actividad es
     * (una rúbrica adjunta a un mensaje suelto de la agenda, por ejemplo). En
     * ese caso se cae al comportamiento de siempre: se muestra lo que la
     * rúbrica traiga guardado, sin afirmar nada sobre su tipo.
     */
    esSumativa?: boolean;
  }

  let {
    rubrica,
    estadoRubrica,
    resultado,
    puntaje_obtenido,
    retroalimentacion,
    modoLectura = false,
    esSumativa = undefined,
  }: Props = $props();

  const totalCriterios = $derived(rubrica?.niveles?.length ?? 0);
  const puntajeMaximo = $derived(
    rubrica?.detalles_evaluacion?.puntaje_total ??
      rubrica?.niveles?.reduce((acc, nivel) => acc + nivel.puntaje_total, 0) ??
      0,
  );

  const maxEscalas = $derived(Math.max(...(rubrica?.niveles?.map((n) => n.escalas.length) ?? [0])));

  /**
   * Ponderación acumulada de todos los criterios.
   */
  const ponderacionTotal = $derived(
    Math.round(
      (rubrica?.niveles ?? []).reduce((sum, n) => {
        const val = typeof n.ponderacion === 'number' ? n.ponderacion : parseFloat(String(n.ponderacion));
        return sum + (isNaN(val) ? 0 : val);
      }, 0) * 100,
    ) / 100,
  );

  const tienePonderaciones = $derived(
    (rubrica?.niveles ?? []).some((n) => n.ponderacion != null && Number(n.ponderacion) > 0),
  );

  /**
   * Rótulos de las columnas.
   *
   * Las rúbricas nuevas traen `columnas` con el nombre que escribió el docente
   * («Insuficiente», «Destacado»…). Las guardadas antes de esa versión no la
   * traen, y para ellas se mantiene el rótulo genérico de siempre: no hay de
   * dónde sacar un nombre, y numerarlas es más honesto que inventarlo.
   */
  const columnas = $derived(
    Array.from({ length: maxEscalas }, (_, i) => ({
      nombre: rubrica?.columnas?.[i]?.nombre?.trim() || `Nivel ${i + 1}`,
      puntos: rubrica?.columnas?.[i]?.puntos ?? null,
    })),
  );

  /**
   * Si el puntaje viene declarado por columna se muestra una vez en la
   * cabecera. Repetirlo dentro de cada celda sería decir el mismo número
   * tantas veces como criterios tenga la rúbrica. En las rúbricas viejas, en
   * cambio, cada celda podía valer distinto y ese número es el único dato real.
   */
  const puntajePorColumna = $derived(!!rubrica?.columnas?.length);

  const tieneResultado = $derived(!!resultado && Object.keys(resultado).length > 0);

  /** Puntaje que hay que alcanzar para el 4,0; sólo se muestra en sumativas. */
  const puntajeParaCuatro = $derived(puntajeMinimoAprobacion(puntajeMaximo));

  /**
   * La escala cualitativa se dibuja si la rúbrica la trae, salvo que la
   * actividad sea sumativa: las rúbricas guardadas antes de esta separación
   * conservan su «Aprobado / Reprobado», y mostrarlo junto a una nota es
   * justamente lo que se quiso separar.
   */
  const mostrarEscalaCualitativa = $derived(
    esSumativa !== true && !!rubrica?.detalles_evaluacion?.escala_evaluacion?.length,
  );

  function esSeleccionada(nivelId: string, escalaId: string): boolean {
    return (
      tieneResultado &&
      (String(resultado?.[nivelId]) === String(escalaId) ||
        String(resultado?.[String(nivelId)]) === String(escalaId))
    );
  }
</script>

{#if !rubrica}
  <div
    class="rounded-2xl border border-dashed border-gray-200 p-8 text-center text-gray-500"
  >
    No hay rúbrica disponible
  </div>
{:else}
  <div class="space-y-6">
    <!-- Resumen Métrico -->
    <div
      class="flex flex-wrap items-center gap-4 sm:gap-6 p-5 sm:p-6 rounded-3xl bg-primary/5 border border-primary/10 shadow-sm"
    >
      <div class="min-w-[100px]">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
          Criterios
        </p>
        <p class="text-2xl sm:text-3xl font-black text-primary">
          {totalCriterios}
        </p>
      </div>

      <div class="border-l border-primary/10 pl-4 sm:pl-6 min-w-[120px]">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
          Puntaje máximo
        </p>
        <p class="text-2xl sm:text-3xl font-black text-primary">
          {puntajeMaximo} pts
        </p>
      </div>

      {#if tienePonderaciones}
        <div class="border-l border-primary/10 pl-4 sm:pl-6 min-w-[120px]">
          <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
            Ponderación
          </p>
          <p class="text-2xl sm:text-3xl font-black text-emerald-600">
            {ponderacionTotal}%
          </p>
        </div>
      {/if}

      {#if puntaje_obtenido != null}
        <div class="border-l border-primary/10 pl-4 sm:pl-6 min-w-[120px]">
          <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
            Puntaje obtenido
          </p>
          <p class="text-2xl sm:text-3xl font-black text-emerald-600">
            {puntaje_obtenido} pts
          </p>
        </div>
      {/if}

      {#if estadoRubrica}
        <div class="border-l border-primary/10 pl-4 sm:pl-6 min-w-[120px]">
          <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
            Estado
          </p>
          <p class="text-sm sm:text-base font-bold text-gray-700 mt-1">
            {estadoRubrica}
          </p>
        </div>
      {/if}
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

    <!--
      Tabla.

      Las columnas tienen ancho mínimo propio: comprimirlas para que quepan
      todas es lo que dejaba la descripción de cada nivel en una palabra por
      línea. Cuando no caben, la tabla se desplaza en horizontal en vez de
      encogerse, y la columna del criterio queda fija (`sticky left-0`) para que
      al desplazarse no se pierda de vista a qué fila corresponde la celda que
      se está leyendo.

      Los fondos de fila son 100% opacos y las celdas fijas usan colores sólidos
      explícitos (`bg-white` / `bg-gray-50` y `group-hover:bg-gray-100`) para que
      el contenido que pasa por debajo al desplazarse no se transparente a través
      de la columna fija, conservando el efecto hover en toda la fila.
    -->
    <div class="overflow-x-auto scroll-smooth rounded-2xl border border-gray-200 shadow-sm">
      <table
        class="w-full border-collapse table-fixed text-left"
        style="min-width: {200 + columnas.length * 240}px"
      >
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th
              class="sticky left-0 z-20 w-[200px] min-w-[200px] max-w-[200px] shrink-0 bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 py-3.5 px-4 border-r border-gray-200 shadow-[1px_0_0_0_rgba(0,0,0,0.05)]"
            >
              Criterio de Evaluación
            </th>

            {#each columnas as columna}
              <th class="text-center text-xs font-bold text-gray-700 uppercase tracking-wide py-3.5 px-4 w-[240px] min-w-[240px] border-r border-gray-100 last:border-r-0">
                <div class="flex flex-col items-center gap-1.5">
                  <span class="break-words">{columna.nombre}</span>
                  {#if puntajePorColumna && columna.puntos != null}
                    <div class="flex items-center justify-center gap-1 bg-primary/5 border border-primary/20 rounded-xl px-3 py-1">
                      <span class="text-sm font-black text-primary">{columna.puntos}</span>
                      <span class="text-[10px] font-bold uppercase text-primary/60">pts</span>
                    </div>
                  {/if}
                </div>
              </th>
            {/each}
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
          {#each rubrica.niveles as nivel, nivelIdx}
            <tr class="group transition-colors hover:bg-gray-100 {nivelIdx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
              <td class="sticky left-0 z-10 align-top py-4 px-4 w-[200px] min-w-[200px] max-w-[200px] shrink-0 border-r border-gray-200 shadow-[1px_0_0_0_rgba(0,0,0,0.05)] transition-colors {nivelIdx % 2 === 0 ? 'bg-white' : 'bg-gray-50'} group-hover:bg-gray-100">
                <div class="space-y-1.5 overflow-hidden">
                  <h4 class="font-bold text-gray-900 leading-snug text-sm break-words">
                    {nivel.nombre}
                  </h4>

                  {#if nivel.descripcion}
                    <p class="text-xs text-gray-500 leading-relaxed italic whitespace-pre-wrap break-words">
                      {nivel.descripcion}
                    </p>
                  {/if}

                  <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    {#if nivel.ponderacion != null && Number(nivel.ponderacion) > 0}
                      <span class="inline-flex items-center text-[11px] font-bold text-primary bg-primary/10 border border-primary/20 rounded-lg px-2 py-0.5">
                        {nivel.ponderacion}% de la nota
                      </span>
                    {/if}
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500 bg-gray-100 rounded-lg px-2 py-0.5">
                      Máx. {nivel.puntaje_total} pts
                    </span>
                  </div>
                </div>
              </td>

              {#each Array(maxEscalas) as _, index}
                <td class="align-top py-4 px-3 w-[240px] min-w-[240px] border-r border-gray-100 last:border-r-0">
                  {#if nivel.escalas[index]}
                    {@const seleccionada = esSeleccionada(nivel.id, nivel.escalas[index].id)}
                    <div
                      class="rounded-2xl border p-4 h-full transition-all flex flex-col justify-between overflow-hidden
                        {seleccionada
                          ? 'border-emerald-400 bg-emerald-50/70 shadow-sm shadow-emerald-100 ring-1 ring-emerald-400/40'
                          : tieneResultado
                            ? 'border-gray-200 bg-white opacity-50'
                            : 'border-gray-200 bg-white hover:border-gray-300'}"
                    >
                      <div
                        class="flex items-center justify-between gap-2 {puntajePorColumna &&
                        !seleccionada
                          ? ''
                          : 'mb-2'}"
                      >
                        {#if !puntajePorColumna}
                          <span class="text-xs font-bold {seleccionada ? 'text-emerald-700' : 'text-gray-500'} bg-gray-100 px-2 py-0.5 rounded-md">
                            {nivel.escalas[index].puntos} pts
                          </span>
                        {/if}
                        {#if seleccionada}
                          <span class="flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-emerald-700 bg-emerald-100 rounded-full px-2.5 py-0.5 ml-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                            Elegido
                          </span>
                        {/if}
                      </div>

                      <p class="text-xs sm:text-sm leading-relaxed whitespace-pre-wrap break-words {seleccionada ? 'text-emerald-950 font-medium' : 'text-gray-600'}">
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

    <!-- Cómo se traduce el puntaje en resultado -->
    {#if esSumativa}
      <div class="rounded-2xl border border-gray-200 bg-gray-50/50 p-5">
        <h3 class="font-bold text-sm text-gray-800 mb-1.5">Cálculo de nota</h3>
        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
          Escala 1,0 a 7,0 con 60 % de exigencia. Nota <strong class="text-primary font-bold">4,0</strong> con
          <strong class="text-primary font-bold">{puntajeParaCuatro}</strong> de {puntajeMaximo} pts.
        </p>
      </div>
    {/if}

    <!-- Escala de evaluación formativa -->
    {#if mostrarEscalaCualitativa}
      <div class="rounded-2xl border border-gray-200 bg-gray-50/50 p-5">
        <p class="text-xs font-bold uppercase text-gray-500 tracking-widest mb-3">
          Escala de evaluación
        </p>
        <div class="flex flex-wrap gap-2.5">
          {#each rubrica.detalles_evaluacion.escala_evaluacion as escala}
            <div class="flex items-center gap-2 px-3.5 py-1.5 bg-white rounded-xl border border-gray-200 shadow-sm text-xs">
              <span class="w-2 h-2 rounded-full bg-primary shrink-0"></span>
              <span class="font-bold text-gray-800">{escala.evaluacion || '—'}</span>
              <span class="text-gray-400 font-medium">≥ {escala.puntaje_minimo} pts</span>
            </div>
          {/each}
        </div>
      </div>
    {/if}
  </div>
{/if}
