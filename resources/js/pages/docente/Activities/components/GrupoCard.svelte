<script lang="ts">
  /**
   * GrupoCard — Tarjeta de un grupo dentro de la gestión de una actividad.
   *
   * Muestra: número de grupo + estado, nota grupal, integrantes (con opción de
   * quitar), notas individuales con ajuste de décimas/recálculo, alta de
   * estudiante a grupo existente y botones de acción (Ver Entregas / Ver Agenda).
   *
   * Todo el estado vive en el padre (Index.svelte); aquí solo se reciben datos y
   * callbacks para mantener el comportamiento idéntico al original.
   */
  import {
    Trash2,
    X,
    UserPlus,
    RefreshCw,
    Plus,
    Minus,
    FileText,
    Calendar,
  } from 'lucide-svelte';

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
    integrantes: IntegranteData[];
  };

  type EstudianteInscrito = {
    id_estudiante: number;
    nombre_completo: string;
  };

  interface Props {
    grupo: GrupoData;
    esTitular: boolean;
    traeArchivo: boolean;
    /** Estado del estudiante que se está guardando (id_asignado_actividad) o null. */
    savingDecimas: number | null;
    /** Grupo cuyo formulario de "agregar estudiante" está abierto, o null. */
    addingToGrupo: number | null;
    addingEstudianteId: number;
    addingLoading: boolean;
    addingError: string | null;
    /** Estudiantes libres disponibles para agregar a este grupo. */
    estudiantesParaGrupo: EstudianteInscrito[];
    getEstadoColor: (estado: string) => string;
    formatDecimas: (d: number | undefined) => string;
    onEliminarGrupo: (grupoId: number) => void;
    onQuitarEstudiante: (grupoId: number, estudianteId: number) => void;
    onAjustarDecimas: (grupoId: number, integrante: IntegranteData, delta: number) => void;
    onRecalcularNotas: (grupoId: number) => void;
    onAbrirAddForm: (grupoId: number) => void;
    onCerrarAddForm: () => void;
    onAgregarAGrupo: (grupoId: number) => void;
    onVerEntregas: (grupo: GrupoData) => void;
    onVerAgenda: (grupo: GrupoData) => void;
  }

  let {
    grupo,
    esTitular,
    traeArchivo,
    savingDecimas,
    addingToGrupo,
    addingEstudianteId = $bindable(),
    addingLoading,
    addingError,
    estudiantesParaGrupo,
    getEstadoColor,
    formatDecimas,
    onEliminarGrupo,
    onQuitarEstudiante,
    onAjustarDecimas,
    onRecalcularNotas,
    onAbrirAddForm,
    onCerrarAddForm,
    onAgregarAGrupo,
    onVerEntregas,
    onVerAgenda,
  }: Props = $props();

  function handleOnEstadoChange() {

  }
  
</script>

<div
  class="flex flex-col w-full text-sm font-semibold text-slate-800 px-4 sm:px-6 py-4 rounded-2xl bg-white border border-gray-200 shadow-sm gap-3"
>
  <!-- Número de grupo + estado + eliminar -->
  <div class="flex items-center justify-between gap-2">
    <p class="font-bold text-base">Grupo #{grupo.grupo}</p>
    <div class="flex items-center gap-2">
      {#if grupo.estado_actividad_asignada}
        <span
          class="text-[11px] font-bold px-3 py-1 rounded-full border {getEstadoColor(
            grupo.estado_actividad_asignada,
          )}"
        >
          {grupo.estado_actividad_asignada.toUpperCase()}
        </span>
      {:else}
        <span
          class="text-[11px] font-bold px-3 py-1 rounded-full border bg-gray-100 text-gray-800 border-gray-300"
        >
          SIN ESTADO
        </span>
      {/if}
      {#if esTitular}
        <button
          onclick={() => onEliminarGrupo(grupo.grupo)}
          class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition"
          title="Eliminar grupo"
        >
          <Trash2 class="w-3.5 h-3.5" />
        </button>
      {/if}
    </div>
  </div>

  <!-- Nota grupal -->
  <div class="flex items-center gap-2">
    <span class="text-xs text-gray-600 font-normal">Nota grupal:</span>
    {#if grupo.nota !== null}
      <span
        class="font-bold text-lg {Number(grupo.nota) >= 4 ? 'text-green-700' : 'text-red-700'}"
      >
        {Number(grupo.nota).toPrecision(2)}
      </span>
    {:else}
      <span class="font-normal text-gray-400 italic text-xs">Sin calificar</span>
    {/if}
  </div>

  <!-- Integrantes -->
  <div>
    <p class="text-xs text-gray-600 font-normal mb-1">Integrantes:</p>
    <div class="flex flex-wrap gap-2">
      {#each grupo.integrantes as integrante}
        <span
          class="inline-flex items-center gap-1 text-xs bg-gray-50 border border-uta-blue/20 px-2 py-1 rounded-full text-slate-700"
        >
          {integrante.nombre_completo}
          {#if esTitular}
            <button
              onclick={() => onQuitarEstudiante(grupo.grupo, integrante.id_estudiante)}
              class="ml-0.5 text-gray-400 hover:text-red-500 transition"
              title="Quitar del grupo"
            >
              <X class="w-3 h-3" />
            </button>
          {/if}
        </span>
      {/each}
      {#if grupo.integrantes.length === 0}
        <span class="text-xs text-gray-400 italic">Sin integrantes</span>
      {/if}
    </div>
  </div>

  <!-- Notas individuales (nota grupal + décimas por estudiante) -->
  {#if grupo.nota !== null && grupo.integrantes.length > 0}
    <div class="border-t border-gray-100 pt-3">
      <div class="flex items-center justify-between mb-2">
        <p class="text-xs text-gray-600 font-normal">Notas individuales</p>
        {#if esTitular}
          <button
            onclick={() => onRecalcularNotas(grupo.grupo)}
            class="inline-flex items-center gap-1 text-[11px] font-semibold text-uta-blue/70 hover:text-uta-blue transition-colors"
            title="Recalcular notas individuales desde la nota grupal"
          >
            <RefreshCw class="w-3 h-3" />
            Recalcular
          </button>
        {/if}
      </div>

      <!-- Cabecera de columnas -->
      <div class="flex items-center gap-2 px-1 mb-1 text-[10px] uppercase tracking-wider text-gray-400 font-semibold">
        <span class="flex-1">Estudiante</span>
        <span class="w-[88px] text-center">Décimas</span>
        <span class="w-10 text-right">Nota</span>
      </div>

      <div class="flex flex-col gap-1.5">
        {#each grupo.integrantes as integrante}
          <div class="flex items-center gap-2 text-xs">
            <span class="flex-1 text-slate-700 truncate">{integrante.nombre_completo}</span>

            <!-- Ajuste de décimas -->
            {#if esTitular}
              <div class="w-[88px] flex items-center justify-center gap-1">
                <button
                  onclick={() => onAjustarDecimas(grupo.grupo, integrante, -0.1)}
                  disabled={savingDecimas === integrante.id_asignado_actividad || (integrante.diferencia_decimas ?? 0) <= -9.9}
                  class="w-5 h-5 flex items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-100 transition disabled:opacity-30"
                  title="Restar una décima"
                >
                  <Minus class="w-3 h-3" />
                </button>
                <span
                  class="w-9 text-center font-mono font-semibold {(integrante.diferencia_decimas ?? 0) === 0
                    ? 'text-gray-400'
                    : (integrante.diferencia_decimas ?? 0) > 0
                      ? 'text-emerald-600'
                      : 'text-red-600'}"
                >
                  {formatDecimas(integrante.diferencia_decimas)}
                </span>
                <button
                  onclick={() => onAjustarDecimas(grupo.grupo, integrante, 0.1)}
                  disabled={savingDecimas === integrante.id_asignado_actividad || (integrante.diferencia_decimas ?? 0) >= 9.9}
                  class="w-5 h-5 flex items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-100 transition disabled:opacity-30"
                  title="Sumar una décima"
                >
                  <Plus class="w-3 h-3" />
                </button>
              </div>
            {:else}
              <span class="w-[88px] text-center font-mono text-gray-500">
                {formatDecimas(integrante.diferencia_decimas)}
              </span>
            {/if}

            <!-- Nota individual resultante -->
            <span
              class="w-10 text-right font-bold {integrante.nota_individual != null && integrante.nota_individual >= 4
                ? 'text-green-700'
                : 'text-red-700'}"
            >
              {integrante.nota_individual != null ? integrante.nota_individual.toFixed(1) : '—'}
            </span>
          </div>
        {/each}
      </div>
    </div>
  {/if}

  <!-- Agregar estudiante a grupo existente (titular) -->
  {#if esTitular}
    {#if addingToGrupo === grupo.grupo}
      <div class="flex items-center gap-2 mt-1">
        <select
          bind:value={addingEstudianteId}
          class="flex-1 text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white text-gray-700 focus:outline-none focus:border-uta-blue focus:ring-2 focus:ring-uta-blue/20 transition-shadow"
        >
          <option value={0}>Seleccionar estudiante…</option>
          {#each estudiantesParaGrupo as e}
            <option value={e.id_estudiante}>{e.nombre_completo}</option>
          {/each}
        </select>
        <button
          onclick={() => onAgregarAGrupo(grupo.grupo)}
          disabled={!addingEstudianteId || addingLoading}
          class="px-3 py-1.5 text-xs font-semibold bg-uta-blue text-white rounded-lg hover:bg-uta-blue-hover transition-colors disabled:opacity-50"
        >
          {addingLoading ? '…' : 'Agregar'}
        </button>
        <button
          onclick={onCerrarAddForm}
          class="p-1.5 text-gray-400 hover:text-gray-600 transition"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>
      {#if addingError}
        <p class="text-xs text-red-600">{addingError}</p>
      {/if}
    {:else}
      <button
        onclick={() => onAbrirAddForm(grupo.grupo)}
        class="inline-flex items-center gap-1.5 text-xs font-medium text-uta-blue/60 hover:text-uta-blue transition-colors"
      >
        <UserPlus class="w-3.5 h-3.5" />
        Agregar estudiante
      </button>
    {/if}
  {/if}

  <!-- Botones de acción del grupo -->
  <div
    class="grid gap-2 mt-1"
    class:grid-cols-2={traeArchivo}
    class:grid-cols-1={!traeArchivo}
  >
    {#if traeArchivo}
      <button
        class="w-full px-3 py-2 rounded-lg border border-uta-blue/20 transition-all bg-uta-blue text-white hover:bg-uta-blue-hover flex items-center justify-between gap-2 text-xs font-semibold"
        onclick={() => onVerEntregas(grupo)}
      >
        <p>Ver Entregas</p>
        <FileText class="size-4 shrink-0" />
      </button>
    {/if}

    <button
      class="w-full px-3 py-2 rounded-lg border border-uta-blue/20 transition-all bg-uta-blue text-white hover:bg-uta-blue-hover flex items-center justify-between gap-2 text-xs font-semibold"
      onclick={() => onVerAgenda(grupo)}
    >
      <p>Ver Agenda</p>
      <Calendar class="size-4 shrink-0" />
    </button>
  </div>
</div>
