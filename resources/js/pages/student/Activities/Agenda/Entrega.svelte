<script lang="ts">
  import { formatFechaHora } from '@/utils/formatters';
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
    nombre_curso: string;
    nombre_actividad: string;
    entrega_obligatoria: boolean;
    esReemplazo: boolean;
    /** En una actividad grupal la entrega es del grupo, no sólo de quien la sube. */
    esGrupal?: boolean;
  }

  let {
    onCerrar,
    onEntregaCompletada,
    onAvisarDocente,
    id_actividad_asignada_grupo,
    nombre_curso,
    nombre_actividad,
    entrega_obligatoria,
    esReemplazo,
    esGrupal = false,
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
  let horaRegistro = $state('');
  let temporizadorCierre: ReturnType<typeof setTimeout> | undefined;

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

    // Un 5xx o un 413 (p. ej. memoria agotada con un archivo enorme) no llega
    // como error de validación: Inertia lo pinta en una ventana HTML a pantalla
    // completa sobre el modal y la subida quedaba pegada en «100 %». Se
    // intercepta sólo durante esta subida para mostrar el error en el modal.
    let estadoHttp = null as number | null;
    const soltarInvalid = router.on('invalid', (event) => {
      estadoHttp = event.detail.response.status;
      event.preventDefault();
    });

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
          // Se deja el tiempo de leer la hora sellada (antes 0,9 s y sin hora);
          // «Listo» cierra al instante.
          horaRegistro = formatFechaHora(new Date().toISOString());
          temporizadorCierre = setTimeout(onEntregaCompletada, 3000);
        },
        onError: (errors) => {
          mensajeError = errors.archivo || errors.error_general || 'No se pudo registrar la entrega.';
          paso = 'error';
        },
        onFinish: () => {
          soltarInvalid();
          // Sigue en «subiendo» sólo si la respuesta no fue ni éxito ni error
          // de validación: fallo del servidor o corte de red.
          if (paso !== 'subiendo') return;
          mensajeError =
            estadoHttp === 413
              ? 'El archivo es más grande de lo que acepta el servidor. Elige uno más liviano.'
              : 'El servidor no pudo procesar la entrega. Intenta de nuevo o avisa a tu docente.';
          paso = 'error';
        },
      },
    );
  }

  function cerrarTrasExito() {
    clearTimeout(temporizadorCierre);
    onEntregaCompletada();
  }

  /** Tras un error de validación «Reintentar» reenvía el mismo archivo: esto permite elegir otro. */
  function cambiarArchivo() {
    archivo = undefined;
    confirmado = false;
    mensajeError = '';
    paso = 'seleccion';
  }

  function reintentar() {
    paso = 'confirmando';
    mensajeError = '';
  }
</script>

<div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
  {#if paso === 'seleccion'}
    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
      <div class="flex flex-col">
        <span class="text-[15px] font-semibold text-ink">{esReemplazo ? 'Reemplazar entrega' : 'Entregar la actividad'}</span>
        <span class="text-xs text-ink-2">{nombre_curso} · {nombre_actividad}</span>
      </div>
      <button class="rounded-full p-1.5 text-ink-2 transition-colors hover:bg-slate-50" onclick={onCerrar} aria-label="cerrar">
        <X class="h-5 w-5" />
      </button>
    </div>

    <div class="flex flex-col gap-4 p-5">
      {#if !archivo}
        <label
          class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors {dragOver
            ? 'border-navy bg-uta-blue-light'
            : 'border-slate-300 bg-slate-50 hover:bg-slate-100'} cursor-pointer"
          ondragover={(e) => {
            e.preventDefault();
            dragOver = true;
          }}
          ondragleave={() => (dragOver = false)}
          ondrop={manejarDrop}
        >
          <UploadCloud class="h-7 w-7 text-navy" />
          <span class="text-sm font-semibold text-ink">Arrastra tu archivo aquí o haz clic para seleccionar</span>
          <span class="text-xs text-ink-2">Hasta 100 MB, según el tipo de archivo</span>
          <input type="file" class="hidden" onchange={manejarSeleccionArchivo} bind:this={inputFile} />
        </label>
      {:else}
        <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-3">
          <span class="flex h-[34px] w-[30px] shrink-0 items-center justify-center rounded-[5px] border border-gray-200 bg-sand font-mono text-[9.5px] font-bold text-ink-2">
            {(archivo.name.split('.').pop() ?? 'ARC').slice(0, 3).toUpperCase()}
          </span>
          <div class="flex min-w-0 flex-col">
            <span class="truncate text-[12.5px] font-semibold text-ink">{archivo.name}</span>
            <span class="font-mono text-[11px] text-ink-2">{formatearTamano(archivo.size)}</span>
          </div>
          <button class="ml-auto shrink-0 text-ink-2 hover:text-red-700" onclick={() => (archivo = undefined)} aria-label="quitar archivo">
            <X class="h-4 w-4" />
          </button>
        </div>
      {/if}

      <div class="flex flex-col gap-1.5">
        <label for="entrega-descripcion" class="text-[13px] font-semibold text-ink">
          Descripción de tu entrega {#if entrega_obligatoria}<span class="text-red-600">*</span>{/if}
        </label>
        <textarea
          id="entrega-descripcion"
          bind:value={descripcion}
          rows="3"
          maxlength="500"
          placeholder="Qué incluye tu entrega, cambios realizados, comentarios importantes…"
          class="resize-none rounded-lg border border-line px-3 py-2.5 text-[13.5px] text-ink outline-none focus:border-navy"
        ></textarea>
        <span class="self-end font-mono text-[11px] text-ink-2">{descripcion.length} / 500</span>
      </div>
    </div>

    <div class="flex items-center gap-2 border-t border-gray-200 px-5 py-3.5">
      <button class="rounded-lg px-3 py-2 text-[13.5px] font-medium text-navy transition-colors hover:bg-slate-50" onclick={onCerrar}>
        Cancelar
      </button>
      <button
        class="ml-auto flex items-center gap-1.5 rounded-lg bg-navy px-4 py-2 text-[13.5px] font-semibold text-white transition-colors hover:bg-navy-hover disabled:cursor-not-allowed disabled:opacity-50"
        disabled={!archivo || (entrega_obligatoria && descripcion.trim().length === 0)}
        onclick={irAConfirmar}
      >
        Continuar
      </button>
    </div>
  {:else if paso === 'confirmando' && archivo}
    <div class="flex items-center gap-2.5 border-b border-gray-200 px-5 py-4">
      <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light">
        <UploadCloud class="h-4 w-4 text-navy" />
      </div>
      <div class="flex flex-col">
        <span class="text-[15px] font-semibold text-ink">Confirmar entrega</span>
        <span class="text-[11.5px] text-ink-2">Queda sellada la hora de este envío</span>
      </div>
    </div>
    <div class="flex flex-col gap-3 p-5">
      <div class="flex items-center gap-2.5 rounded-lg border border-gray-200 p-2.5">
        <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[5px] border border-gray-200 bg-sand font-mono text-[9.5px] font-bold text-ink-2">
          {(archivo.name.split('.').pop() ?? 'ARC').slice(0, 3).toUpperCase()}
        </span>
        <div class="flex min-w-0 flex-col">
          <span class="truncate text-[12.5px] font-semibold text-ink">{archivo.name}</span>
          <span class="font-mono text-[11px] text-ink-2">{formatearTamano(archivo.size)}</span>
        </div>
      </div>
      <div class="flex flex-col gap-1.5 rounded-lg border border-gray-200 bg-slate-50 p-3">
        <span class="text-xs font-semibold text-ink">Al confirmar:</span>
        <span class="flex items-start gap-1.5 text-xs text-ink-2">
          <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" />
          Se registra la hora de este envío como tu entrega.
        </span>
        {#if esReemplazo}
          <span class="flex items-start gap-1.5 text-xs text-ink-2">
            <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" />
            Reemplaza la entrega anterior; la anterior deja de contar.
          </span>
        {/if}
        <span class="flex items-start gap-1.5 text-xs text-ink-2">
          <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" />
          Mientras la actividad siga activa, puedes volver a reemplazarla.
        </span>
      </div>
      <label class="flex cursor-pointer items-start gap-2 text-[12.5px] text-ink">
        <input type="checkbox" bind:checked={confirmado} class="mt-0.5 h-[15px] w-[15px] accent-navy" />
        <span>
          {esGrupal
            ? 'Confirmo que este archivo es la entrega de mi grupo en la actividad.'
            : 'Confirmo que este archivo es mi entrega de la actividad.'}
        </span>
      </label>
    </div>
    <div class="flex items-center gap-2 border-t border-gray-200 px-5 py-3.5">
      <button class="rounded-lg px-3 py-2 text-[13.5px] font-medium text-navy transition-colors hover:bg-slate-50" onclick={() => (paso = 'seleccion')}>
        Volver
      </button>
      <button
        class="ml-auto flex items-center gap-1.5 rounded-lg bg-navy px-4.5 py-2.5 text-[13.5px] font-semibold text-white shadow-sm transition-colors hover:bg-navy-hover disabled:cursor-not-allowed disabled:opacity-50"
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
        <Loader class="h-4 w-4 animate-spin text-navy" />
        <span class="text-sm font-semibold text-ink">Entregando la actividad</span>
        <span class="ml-auto font-mono text-xs font-semibold text-navy">{progreso}%</span>
      </div>
      <div class="h-2 overflow-hidden rounded-full bg-gray-100">
        <div class="h-2 rounded-full bg-navy transition-all" style="width:{progreso}%"></div>
      </div>
      <span class="text-[11.5px] text-ink-2">No cierres esta ventana mientras se sube el archivo.</span>
    </div>
  {:else if paso === 'exito' && archivo}
    <div class="flex flex-col gap-3 p-6">
      <div class="flex items-center gap-2">
        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
        <span class="text-sm font-semibold text-ink">Entrega registrada</span>
      </div>
      <div class="flex flex-col gap-1.5 rounded-lg border border-emerald-200 bg-slate-50 p-3">
        <div class="flex items-baseline gap-2">
          <span class="w-14 shrink-0 text-[11.5px] text-ink-2">Archivo</span>
          <span class="truncate text-[12.5px] font-semibold text-ink">{archivo.name}</span>
        </div>
        <div class="flex items-baseline gap-2">
          <span class="w-14 shrink-0 text-[11.5px] text-ink-2">Peso</span>
          <span class="font-mono text-xs">{formatearTamano(archivo.size)}</span>
        </div>
        <div class="flex items-baseline gap-2">
          <span class="w-14 shrink-0 text-[11.5px] text-ink-2">Hora</span>
          <span class="text-xs font-medium text-ink">{horaRegistro} hrs</span>
        </div>
      </div>
      <button
        type="button"
        class="self-start rounded-lg bg-navy px-3.5 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-navy-hover"
        onclick={cerrarTrasExito}
      >
        Listo
      </button>
    </div>
  {:else if paso === 'error'}
    <div class="flex flex-col gap-3 p-6">
      <div class="flex items-center gap-2">
        <AlertCircle class="h-4 w-4 text-red-700" />
        <span class="text-sm font-semibold text-ink">La entrega no se completó</span>
      </div>
      <p class="text-[12.5px] text-ink">{mensajeError}</p>
      <p class="text-[12px] text-ink-2">No se registró ninguna entrega parcial. Tu archivo no se perdió: sigue seleccionado.</p>
      <div class="flex gap-2 pt-1">
        <button
          class="flex items-center gap-1.5 rounded-lg bg-navy px-3.5 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-navy-hover"
          onclick={reintentar}
        >
          <RotateCcw class="h-3.5 w-3.5" />
          Reintentar
        </button>
        <button
          class="rounded-lg border border-line bg-white px-3.5 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-slate-50"
          onclick={cambiarArchivo}
        >
          Cambiar archivo
        </button>
        {#if onAvisarDocente}
          <button
            class="rounded-lg border border-line bg-white px-3.5 py-2 text-[13px] font-medium text-ink transition-colors hover:bg-slate-50"
            onclick={onAvisarDocente}
          >
            Avisar al docente
          </button>
        {/if}
      </div>
    </div>
  {/if}
</div>
