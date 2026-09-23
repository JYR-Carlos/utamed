<script lang="ts">
  /**
   * EstudiantesTabla — estudiantes asignados a una actividad INDIVIDUAL.
   *
   * En una actividad individual cada «grupo» es un estudiante, así que
   * `GrupoCard` acababa repitiendo el mismo marco, el mismo rótulo y los mismos
   * dos botones azules una vez por alumno: con veinte inscritos la pantalla es
   * un muro de tarjetas y comparar notas exige recorrerla entera. Aquí los
   * mismos datos son columnas —nombre, estado, nota, décimas, holgura— y el ojo
   * baja por una sola columna para comparar.
   *
   * Mismas reglas que la tarjeta, sin excepción:
   *  - Las décimas sólo se tocan si hay nota puesta (sin nota no hay qué ajustar).
   *  - La holgura personal sólo la ve y edita el titular.
   *  - «Entregas» sólo existe si la actividad pide archivo.
   *
   * Todo el estado vive en el padre (Index.svelte); lo único local es qué fila
   * tiene abierto el editor de holgura.
   */
  import { Calendar, Check, FileText, Minus, Plus, X } from 'lucide-svelte';

  type IntegranteData = {
    id_estudiante: number;
    nombre_completo: string;
    id_asignado_actividad?: number;
    nota_individual?: number | null;
    diferencia_decimas?: number;
  };

  type GrupoData = {
    grupo: number;
    nota: number | null;
    estado_actividad_asignada: string | null;
    nro_dias_adicionales_para_bloqueo_personal: number;
    integrantes: IntegranteData[];
  };

  interface Props {
    grupos: GrupoData[];
    esTitular: boolean;
    traeArchivo: boolean;
    /** Estado del estudiante que se está guardando (id_asignado_actividad) o null. */
    savingDecimas: number | null;
    getEstadoColor: (estado: string) => string;
    formatDecimas: (d: number | undefined) => string;
    onAjustarDecimas: (grupoId: number, integrante: IntegranteData, delta: number) => void;
    onVerEntregas: (grupo: GrupoData) => void;
    onVerAgenda: (grupo: GrupoData) => void;
    onActualizarHolguraPersonal: (grupoId: number, dias: number) => void;
  }

  let {
    grupos,
    esTitular,
    traeArchivo,
    savingDecimas,
    getEstadoColor,
    formatDecimas,
    onAjustarDecimas,
    onVerEntregas,
    onVerAgenda,
    onActualizarHolguraPersonal,
  }: Props = $props();

  /** Grupo (estudiante) cuyo editor de holgura está abierto, o null. */
  let editandoHolgura = $state<number | null>(null);
  let holguraTemp = $state(0);

  function iniciarEdicionHolgura(grupo: GrupoData) {
    holguraTemp = grupo.nro_dias_adicionales_para_bloqueo_personal;
    editandoHolgura = grupo.grupo;
  }

  function guardarHolgura(grupoId: number) {
    onActualizarHolguraPersonal(grupoId, holguraTemp);
    editandoHolgura = null;
  }

  function textoHolgura(dias: number): string {
    if (dias === 0) return 'Sin holgura';
    return `+${dias} día${dias === 1 ? '' : 's'}`;
  }

  const TH =
    'px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500';
  const BTN_FILA =
    'inline-flex items-center gap-1.5 rounded-lg border border-uta-blue/25 px-2.5 py-1.5 text-xs font-semibold text-uta-blue transition-colors hover:bg-uta-blue/5';
  const BTN_DECIMA =
    'w-5 h-5 flex items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-100 transition disabled:opacity-30';
</script>

<div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
  <table class="w-full min-w-[780px] border-collapse text-sm">
    <caption class="sr-only">Estudiantes asignados a la actividad</caption>
    <thead>
      <tr class="border-b border-gray-200 bg-gray-50">
        <th class="{TH} w-10 pl-4">#</th>
        <th class={TH}>Estudiante</th>
        <th class="{TH} w-[116px]">Estado</th>
        <th class="{TH} w-[76px] text-center">Nota</th>
        <th class="{TH} w-[108px] text-center">Décimas</th>
        <th class="{TH} w-[76px] text-center">Final</th>
        {#if esTitular}
          <th class="{TH} w-[142px]">Holgura</th>
        {/if}
        <th class="{TH} w-[200px] pr-4 text-right">Acciones</th>
      </tr>
    </thead>
    <tbody>
      {#if grupos.length === 0}
        <tr class="border-t border-gray-100">
          <td colspan={esTitular ? 8 : 7} class="px-4 py-10 text-center">
            <p class="text-sm font-medium text-gray-500">
              No hay estudiantes asignados a esta actividad
            </p>
          </td>
        </tr>
      {/if}
      {#each grupos as grupo, i (grupo.grupo)}
        {@const integrante = grupo.integrantes[0]}
        {@const decimas = integrante?.diferencia_decimas ?? 0}
        {@const calificado = grupo.nota !== null && integrante != null}
        <tr class="border-t border-gray-100 transition-colors hover:bg-gray-50/70">
          <td class="px-3 py-2.5 pl-4 font-mono text-xs tabular-nums text-gray-400">
            {String(i + 1).padStart(2, '0')}
          </td>

          <td class="px-3 py-2.5 font-semibold text-slate-800">
            {integrante?.nombre_completo ?? 'Estudiante'}
          </td>

          <td class="px-3 py-2.5">
            <span
              class="inline-block rounded-full border px-2 py-0.5 text-[10px] font-bold {grupo.estado_actividad_asignada
                ? getEstadoColor(grupo.estado_actividad_asignada)
                : 'border-gray-300 bg-gray-100 text-gray-800'}"
            >
              {grupo.estado_actividad_asignada?.toUpperCase() ?? 'SIN ESTADO'}
            </span>
          </td>

          <!-- Nota base: la que registra la evaluación, antes de décimas. -->
          <td class="px-3 py-2.5 text-center">
            {#if grupo.nota !== null}
              <span
                class="font-bold tabular-nums {Number(grupo.nota) >= 4
                  ? 'text-green-700'
                  : 'text-red-700'}"
              >
                {Number(grupo.nota).toPrecision(2)}
              </span>
            {:else}
              <span class="text-xs text-gray-400 italic">Sin calificar</span>
            {/if}
          </td>

          <!-- Ajuste de décimas: sin nota puesta no hay nada que ajustar. -->
          <td class="px-3 py-2.5">
            {#if !calificado}
              <span class="block text-center text-gray-300">—</span>
            {:else if esTitular}
              <div class="flex items-center justify-center gap-1">
                <button
                  onclick={() => onAjustarDecimas(grupo.grupo, integrante, -0.1)}
                  disabled={savingDecimas === integrante.id_asignado_actividad || decimas <= -9.9}
                  class={BTN_DECIMA}
                  title="Restar una décima"
                >
                  <Minus class="w-3 h-3" />
                </button>
                <span
                  class="w-9 text-center font-mono text-xs font-semibold {decimas === 0
                    ? 'text-gray-400'
                    : decimas > 0
                      ? 'text-emerald-600'
                      : 'text-red-600'}"
                >
                  {formatDecimas(decimas)}
                </span>
                <button
                  onclick={() => onAjustarDecimas(grupo.grupo, integrante, 0.1)}
                  disabled={savingDecimas === integrante.id_asignado_actividad || decimas >= 9.9}
                  class={BTN_DECIMA}
                  title="Sumar una décima"
                >
                  <Plus class="w-3 h-3" />
                </button>
              </div>
            {:else}
              <span class="block text-center font-mono text-xs text-gray-500">
                {formatDecimas(decimas)}
              </span>
            {/if}
          </td>

          <!-- Nota final = nota base + décimas. -->
          <td class="px-3 py-2.5 text-center">
            {#if calificado && integrante.nota_individual != null}
              <span
                class="font-bold tabular-nums {integrante.nota_individual >= 4
                  ? 'text-green-700'
                  : 'text-red-700'}"
              >
                {integrante.nota_individual.toFixed(1)}
              </span>
            {:else}
              <span class="text-gray-300">—</span>
            {/if}
          </td>

          {#if esTitular}
            <td class="px-3 py-2.5">
              {#if editandoHolgura === grupo.grupo}
                <div class="flex items-center gap-1">
                  <input
                    type="number"
                    min="0"
                    bind:value={holguraTemp}
                    class="w-14 rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 transition-shadow focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 focus:outline-none"
                  />
                  <span class="text-[10px] text-gray-400">días</span>
                  <button
                    onclick={() => guardarHolgura(grupo.grupo)}
                    class="rounded-lg bg-uta-blue p-1 text-white transition-colors hover:bg-uta-blue-hover"
                    title="Guardar holgura"
                  >
                    <Check class="w-3 h-3" />
                  </button>
                  <button
                    onclick={() => (editandoHolgura = null)}
                    class="p-1 text-gray-400 transition hover:text-gray-600"
                    title="Cancelar"
                  >
                    <X class="w-3 h-3" />
                  </button>
                </div>
              {:else}
                <button
                  onclick={() => iniciarEdicionHolgura(grupo)}
                  class="rounded-lg px-2 py-1 text-xs font-semibold transition-colors hover:bg-uta-blue/5 {grupo.nro_dias_adicionales_para_bloqueo_personal ===
                  0
                    ? 'text-gray-400'
                    : 'text-uta-blue'}"
                  title="Editar días adicionales de holgura"
                >
                  {textoHolgura(grupo.nro_dias_adicionales_para_bloqueo_personal)}
                </button>
              {/if}
            </td>
          {/if}

          <td class="px-3 py-2.5 pr-4 text-right whitespace-nowrap">
            {#if traeArchivo}
              <button class={BTN_FILA} onclick={() => onVerEntregas(grupo)}>
                <FileText class="w-3.5 h-3.5 shrink-0" />
                Entregas
              </button>
            {/if}
            <button class="{BTN_FILA} ml-1.5" onclick={() => onVerAgenda(grupo)}>
              <Calendar class="w-3.5 h-3.5 shrink-0" />
              Agenda
            </button>
          </td>
        </tr>
      {/each}
    </tbody>
  </table>
</div>
