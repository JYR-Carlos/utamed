<script lang="ts">
  /**
   * Entrega formal de la actividad: subir el archivo ES la entrega, en un
   * solo paso atómico (AgendaController::storeEntrega ya lo envuelve en una
   * transacción). No hay intentos ni recibo — esos nacen de la fecha (más
   * holgura), no de un contador — y una entrega no se anula, sólo se
   * reemplaza mientras la actividad siga activa.
   */
  import { router } from '@inertiajs/svelte';
  import { UploadCloud, X, Check, Loader, CheckCircle2, AlertCircle, RotateCcw } from 'lucide-svelte';

  interface Props {
    onCerrar: () => void;
    onEntregaCompletada: () => void;
    onAvisarDocente?: () => void;
    id_actividad_asignada_grupo: number;
    cod_curso: string;
    nombre_actividad: string;
    entrega_obligatoria: boolean;
    esReemplazo: boolean;
  }

  let {
    onCerrar,
    onEntregaCompletada,
    onAvisarDocente,
    id_actividad_asignada_grupo,
    cod_curso,
    nombre_actividad,
    entrega_obligatoria,
    esReemplazo,
  }: Props = $props();

  type Paso = 'seleccion' | 'confirmando' | 'subiendo' | 'exito' | 'error';
  let paso = $state<Paso>('seleccion');

  let archivo: File | undefined = $state();
  let descripcion = $state('');
  let confirmado = $state(false);
  let progreso = $state(0);
  let mensajeError = $state('');
  let dragOver = $state(false);
  let inputFile: HTMLInputElement | undefined = $state();

  function formatearTamano(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const unidades = ['B', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${Math.round((bytes / Math.pow(k, i)) * 10) / 10} ${unidades[i]}`;
  }

  function elegirArchivo(f: File | undefined) {
    if (!f) return;
    archivo = f;
  }

  function manejarSeleccionArchivo(event: Event) {
    const input = event.target as HTMLInputElement;
    elegirArchivo(input.files?.[0]);
  }

  function manejarDrop(event: DragEvent) {
    event.preventDefault();
    dragOver = false;
    elegirArchivo(event.dataTransfer?.files?.[0]);
  }

  function irAConfirmar() {
    if (!archivo) return;
    if (entrega_obligatoria && descripcion.trim().length === 0) return;
    paso = 'confirmando';
  }

  function confirmarEntrega() {
    if (!archivo || !confirmado) return;

    paso = 'subiendo';
    progreso = 0;
    mensajeError = '';

    router.post(
      `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/entregas`,
      { mensaje: descripcion.trim(), archivo },
      {
        forceFormData: true,
        onProgress: (event) => {
          progreso = event?.percentage ?? progreso;
        },
        onSuccess: () => {
          progreso = 100;
          paso = 'exito';
          setTimeout(onEntregaCompletada, 900);
        },
        onError: (errors) => {
          mensajeError = errors.archivo || errors.error_general || 'No se pudo registrar la entrega.';
          paso = 'error';
        },
      },
    );
  }

  function reintentar() {
    paso = 'confirmando';
    mensajeError = '';
  }
</script>

<div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
  {#if paso === 'seleccion'}
    <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
      <div class="flex flex-col">
        <span class="text-[15px] font-semibold text-[#1A1A24]">{esReemplazo ? 'Reemplazar entrega' : 'Entregar la actividad'}</span>
        <span class="text-xs text-[#5A5E6E]">{cod_curso} · {nombre_actividad}</span>
      </div>
      <button class="rounded-full p-1.5 text-[#5A5E6E] transition-colors hover:bg-[#F8FAFC]" onclick={onCerrar} aria-label="cerrar">
        <X class="h-5 w-5" />
      </button>
    </div>

    <div class="flex flex-col gap-4 p-5">
      {#if !archivo}
        <label
          class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors {dragOver
            ? 'border-[#002F6C] bg-[#E8EDF5]'
            : 'border-[#C9D6E6] bg-[#F8FAFC] hover:bg-[#F1F5F9]'} cursor-pointer"
          ondragover={(e) => {
            e.preventDefault();
            dragOver = true;
          }}
          ondragleave={() => (dragOver = false)}
          ondrop={manejarDrop}
        >
          <UploadCloud class="h-7 w-7 text-[#002F6C]" />
          <span class="text-sm font-semibold text-[#1A1A24]">Arrastra tu archivo aquí o haz clic para seleccionar</span>
          <span class="text-xs text-[#5A5E6E]">Hasta 25 MB</span>
          <input type="file" class="hidden" onchange={manejarSeleccionArchivo} bind:this={inputFile} />
        </label>
      {:else}
        <div class="flex items-center gap-3 rounded-lg border border-[#E5E7EB] p-3">
          <span class="flex h-[34px] w-[30px] shrink-0 items-center justify-center rounded-[5px] border border-[#E5E7EB] bg-[#F5F1EA] font-mono text-[9.5px] font-bold text-[#5A5E6E]">
            {(archivo.name.split('.').pop() ?? 'ARC').slice(0, 3).toUpperCase()}
          </span>
          <div class="flex min-w-0 flex-col">
            <span class="truncate text-[12.5px] font-semibold text-[#1A1A24]">{archivo.name}</span>
            <span class="font-mono text-[11px] text-[#5A5E6E]">{formatearTamano(archivo.size)}</span>
          </div>
          <button class="ml-auto shrink-0 text-[#5A5E6E] hover:text-[#B91C1C]" onclick={() => (archivo = undefined)} aria-label="quitar archivo">
            <X class="h-4 w-4" />
          </button>
        </div>
      {/if}

      <div class="flex flex-col gap-1.5">
        <label for="entrega-descripcion" class="text-[13px] font-semibold text-[#1A1A24]">
          Descripción de tu entrega {#if entrega_obligatoria}<span class="text-[#DC2626]">*</span>{/if}
        </label>
        <textarea
          id="entrega-descripcion"
          bind:value={descripcion}
          rows="3"
          maxlength="500"
          placeholder="Qué incluye tu entrega, cambios realizados, comentarios importantes…"
          class="resize-none rounded-lg border border-[#D6D9E0] px-3 py-2.5 text-[13.5px] text-[#1A1A24] outline-none focus:border-[#002F6C]"
        ></textarea>
        <span class="self-end font-mono text-[11px] text-[#5A5E6E]">{descripcion.length} / 500</span>
      </div>
    </div>

    <div class="flex items-center gap-2 border-t border-[#E5E7EB] px-5 py-3.5">
      <button class="rounded-lg px-3 py-2 text-[13.5px] font-medium text-[#002F6C] transition-colors hover:bg-[#F8FAFC]" onclick={onCerrar}>
        Cancelar
      </button>
      <button
        class="ml-auto flex items-center gap-1.5 rounded-lg bg-[#002F6C] px-4 py-2 text-[13.5px] font-semibold text-white transition-colors hover:bg-[#00214d] disabled:cursor-not-allowed disabled:opacity-50"
        disabled={!archivo || (entrega_obligatoria && descripcion.trim().length === 0)}
        onclick={irAConfirmar}
      >
        Continuar
      </button>
    </div>
  {:else if paso === 'confirmando' && archivo}
    <div class="flex items-center gap-2.5 border-b border-[#E5E7EB] px-5 py-4">
      <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E8EDF5]">
        <UploadCloud class="h-4 w-4 text-[#002F6C]" />
      </div>
      <div class="flex flex-col">
        <span class="text-[15px] font-semibold text-[#1A1A24]">Confirmar entrega</span>
        <span class="text-[11.5px] text-[#5A5E6E]">Queda sellada la hora de este envío</span>
      </div>
    </div>
    <div class="flex flex-col gap-3 p-5">
      <div class="flex items-center gap-2.5 rounded-lg border border-[#E5E7EB] p-2.5">
        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[5px] border border-[#E5E7EB] bg-[#F5F1EA] font-mono text-[9.5px] font-bold text-[#5A5E6E]">
          {(archivo.name.split('.').pop() ?? 'ARC').slice(0, 3).toUpperCase()}
        </span>
        <div class="flex min-w-0 flex-col">
          <span class="truncate text-[12.5px] font-semibold text-[#1A1A24]">{archivo.name}</span>
          <span class="font-mono text-[11px] text-[#5A5E6E]">{formatearTamano(archivo.size)}</span>
        </div>
      </div>
      <div class="flex flex-col gap-1.5 rounded-lg border border-[#E5E7EB] bg-[#F8FAFC] p-3">
        <span class="text-xs font-semibold text-[#1A1A24]">Al confirmar:</span>
        <span class="flex items-start gap-1.5 text-xs text-[#5A5E6E]">
          <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#059669]" />
          Se registra la hora de este envío como tu entrega.
        </span>
        {#if esReemplazo}
          <span class="flex items-start gap-1.5 text-xs text-[#5A5E6E]">
            <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#059669]" />
            Reemplaza la entrega anterior; la anterior deja de contar.
          </span>
        {/if}
        <span class="flex items-start gap-1.5 text-xs text-[#5A5E6E]">
          <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#059669]" />
          Mientras la actividad siga activa, puedes volver a reemplazarla.
        </span>
      </div>
      <label class="flex cursor-pointer items-start gap-2 text-[12.5px] text-[#1A1A24]">
        <input type="checkbox" bind:checked={confirmado} class="mt-0.5 h-[15px] w-[15px] accent-[#002F6C]" />
        <span>Confirmo que este archivo es mi entrega de la actividad.</span>
      </label>
    </div>
    <div class="flex items-center gap-2 border-t border-[#E5E7EB] px-5 py-3.5">
      <button class="rounded-lg px-3 py-2 text-[13.5px] font-medium text-[#002F6C] transition-colors hover:bg-[#F8FAFC]" onclick={() => (paso = 'seleccion')}>
        Volver
      </button>
      <button
        class="ml-auto flex items-center gap-1.5 rounded-lg bg-[#002F6C] px-4.5 py-2.5 text-[13.5px] font-semibold text-white shadow-sm transition-colors hover:bg-[#00214d] disabled:cursor-not-allowed disabled:opacity-50"
        disabled={!confirmado}
        onclick={confirmarEntrega}
      >
        <UploadCloud class="h-4 w-4" />
        Entregar ahora
      </button>
    </div>
  {:else if paso === 'subiendo'}
    <div class="flex flex-col gap-3.5 p-6">
      <div class="flex items-center gap-2">
        <Loader class="h-4 w-4 animate-spin text-[#002F6C]" />
        <span class="text-sm font-semibold text-[#1A1A24]">Entregando la actividad</span>
        <span class="ml-auto font-mono text-xs font-semibold text-[#002F6C]">{progreso}%</span>
      </div>
      <div class="h-2 overflow-hidden rounded-full bg-[#EEF1F5]">
        <div class="h-2 rounded-full bg-[#002F6C] transition-all" style="width:{progreso}%"></div>
      </div>
      <span class="text-[11.5px] text-[#5A5E6E]">No cierres esta ventana mientras se sube el archivo.</span>
    </div>
  {:else if paso === 'exito' && archivo}
    <div class="flex flex-col gap-3 p-6">
      <div class="flex items-center gap-2">
        <CheckCircle2 class="h-4 w-4 text-[#059669]" />
        <span class="text-sm font-semibold text-[#1A1A24]">Entrega registrada</span>
      </div>
      <div class="flex flex-col gap-1.5 rounded-lg border border-[#A7F3D0] bg-[#F8FAFC] p-3">
        <div class="flex items-baseline gap-2">
          <span class="w-14 shrink-0 text-[11.5px] text-[#5A5E6E]">Archivo</span>
          <span class="truncate text-[12.5px] font-semibold text-[#1A1A24]">{archivo.name}</span>
        </div>
        <div class="flex items-baseline gap-2">
          <span class="w-14 shrink-0 text-[11.5px] text-[#5A5E6E]">Peso</span>
          <span class="font-mono text-xs">{formatearTamano(archivo.size)}</span>
        </div>
      </div>
    </div>
  {:else if paso === 'error'}
    <div class="flex flex-col gap-3 p-6">
      <div class="flex items-center gap-2">
        <AlertCircle class="h-4 w-4 text-[#B91C1C]" />
        <span class="text-sm font-semibold text-[#1A1A24]">La entrega no se completó</span>
      </div>
      <p class="text-[12.5px] text-[#1A1A24]">{mensajeError}</p>
      <p class="text-[12px] text-[#5A5E6E]">No se registró ninguna entrega parcial. Tu archivo no se perdió: sigue seleccionado.</p>
      <div class="flex gap-2 pt-1">
        <button
          class="flex items-center gap-1.5 rounded-lg bg-[#002F6C] px-3.5 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-[#00214d]"
          onclick={reintentar}
        >
          <RotateCcw class="h-3.5 w-3.5" />
          Reintentar
        </button>
        {#if onAvisarDocente}
          <button
            class="rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2 text-[13px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F8FAFC]"
            onclick={onAvisarDocente}
          >
            Avisar al docente
          </button>
        {/if}
      </div>
    </div>
  {/if}
</div>
