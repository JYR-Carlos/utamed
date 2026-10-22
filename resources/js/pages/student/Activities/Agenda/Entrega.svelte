<script lang="ts">
  /**
   * Cuerpo de la ventana «Entregar la actividad» (guía dm): Archivo →
   * Confirmar → Listo. Subir el archivo ES la entrega, en un solo paso
   * atómico (AgendaController::storeEntrega ya lo envuelve en una
   * transacción). No hay intentos ni recibo — esos nacen de la fecha (más
   * holgura), no de un contador — y una entrega no se anula, sólo se
   * reemplaza mientras la actividad siga activa.
   */
  import { router } from '@inertiajs/svelte';
  import { onDestroy } from 'svelte';
  import { Check, CircleAlert, CircleCheck, LoaderCircle, RotateCcw, Upload, UploadCloud, X } from 'lucide-svelte';
  import { formatBytes } from '@/utils/formatters';

  interface Props {
    id_actividad_asignada_grupo: number;
    entrega_obligatoria: boolean;
    esReemplazo: boolean;
    /** En una actividad grupal la entrega es del grupo, no sólo de quien la sube. */
    esGrupal?: boolean;
    /** Nombres de pila del resto del grupo («Benjamín y Josefa la ven…»). */
    companeros?: string[];
    onCerrar: () => void;
    onEntregaCompletada: () => void;
    onAvisarDocente?: () => void;
    /** Mientras sube, la ventana no se puede cerrar. */
    subiendo?: boolean;
  }

  let {
    id_actividad_asignada_grupo,
    entrega_obligatoria,
    esReemplazo,
    esGrupal = false,
    companeros = [],
    onCerrar,
    onEntregaCompletada,
    onAvisarDocente,
    subiendo = $bindable(false),
  }: Props = $props();

  type Paso = 'seleccion' | 'confirmando' | 'subiendo' | 'exito' | 'error';
  let paso = $state<Paso>('seleccion');

  let archivo: File | undefined = $state();
  let descripcion = $state('');
  let confirmado = $state(false);
  let progreso = $state(0);
  let mensajeError = $state('');
  let arrastrando = $state(false);
  let selector: HTMLInputElement | undefined = $state();
  let horaRegistro = $state('');
  let temporizadorCierre: ReturnType<typeof setTimeout> | undefined;
  let completada = false;

  $effect(() => {
    subiendo = paso === 'subiendo';
  });

  const MAX_DESCRIPCION = 500;
  const faltaDescripcion = $derived(entrega_obligatoria && descripcion.trim().length === 0);
  const listaParaConfirmar = $derived(!!archivo && !faltaDescripcion);

  const extension = (nombre: string) => (nombre.split('.').pop() ?? 'ARC').slice(0, 4).toUpperCase();
  const peso = (f: File) => formatBytes(f.size) || '0 B';

  // Pasos del encabezado: Archivo · Confirmar · Listo.
  const indicePaso = $derived(paso === 'seleccion' ? 0 : paso === 'exito' ? 2 : 1);
  const PASOS = ['Archivo', 'Confirmar', 'Listo'];

  const nombresGrupo = $derived(
    companeros.length === 0
      ? ''
      : companeros.length === 1
        ? companeros[0]
        : `${companeros.slice(0, -1).join(', ')} y ${companeros[companeros.length - 1]}`,
  );
  const alConfirmar = $derived(
    [
      esGrupal
        ? 'Queda registrada la hora de este envío como la entrega del grupo.'
        : 'Queda registrada la hora de este envío como tu entrega.',
      esReemplazo ? 'Reemplaza la entrega anterior: la anterior deja de contar.' : null,
      esGrupal && nombresGrupo
        ? `${nombresGrupo} ${companeros.length === 1 ? 'la ve' : 'la ven'} en la agenda de la actividad.`
        : 'Tu equipo docente la ve en la agenda de la actividad.',
      esGrupal
        ? 'Mientras el plazo siga abierto, cualquiera del grupo puede reemplazarla.'
        : 'Mientras el plazo siga abierto, puedes reemplazarla.',
    ].filter((t): t is string => !!t),
  );

  function elegirArchivo(f: File | undefined) {
    if (f) archivo = f;
  }

  function alSoltar(e: DragEvent) {
    e.preventDefault();
    arrastrando = false;
    elegirArchivo(e.dataTransfer?.files?.[0]);
  }

  function quitarArchivo() {
    archivo = undefined;
    confirmado = false;
    if (selector) selector.value = '';
  }

  function irAConfirmar() {
    if (!listaParaConfirmar) return;
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
          progreso = Math.round(event?.percentage ?? progreso);
        },
        onSuccess: () => {
          progreso = 100;
          paso = 'exito';
          // Se deja el tiempo de leer la hora sellada; «Listo» cierra al instante.
          horaRegistro = new Date().toLocaleString('es-CL', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
          });
          temporizadorCierre = setTimeout(completar, 3000);
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

  /** Una sola vez, la cierre quien la cierre (temporizador, «Listo» o la X). */
  function completar() {
    if (completada) return;
    completada = true;
    clearTimeout(temporizadorCierre);
    onEntregaCompletada();
  }

  // Cerrada con la X o Escape después de entregar: igual hay que recargar.
  onDestroy(() => {
    clearTimeout(temporizadorCierre);
    if (paso === 'exito') completar();
  });

  /** «Reintentar» reenvía el mismo archivo; «Cambiar archivo» vuelve a elegir. */
  function cambiarArchivo() {
    quitarArchivo();
    mensajeError = '';
    paso = 'seleccion';
  }

  function reintentar() {
    mensajeError = '';
    confirmarEntrega();
  }

  const PRIMARIO =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-dm-accion px-5 text-[15px] font-bold text-dm-sobre-accion transition-colors hover:bg-dm-accion-hover disabled:cursor-not-allowed disabled:border disabled:border-dm-linea disabled:bg-dm-off-s disabled:text-dm-off';
  const TEXTO = 'inline-flex min-h-11 cursor-pointer items-center px-3 text-[15px] font-semibold text-dm-enlace hover:text-dm-enlace-hover hover:underline';
  const SECUNDARIO =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-[10px] border border-dm-control bg-dm-tarjeta px-4 text-[15px] font-semibold text-dm-ink transition-colors hover:bg-dm-sutil';
  const PIE = 'flex shrink-0 flex-wrap items-center justify-end gap-3 border-t border-dm-linea bg-dm-sutil px-4 py-3.5 md:px-6';
  const CUERPO = 'flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4 py-5 md:px-6';
</script>

{#snippet fichaArchivo(f: File, detalle: string, quitar: boolean)}
  <div class="flex min-w-0 items-center gap-3 rounded-xl bg-dm-sutil px-3 py-2.5">
    <span
      aria-hidden="true"
      class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-dm-accion text-xs font-extrabold tracking-[0.04em] text-dm-sobre-accion"
      >{extension(f.name)}</span
    >
    <span class="flex min-w-0 flex-col">
      <span class="truncate text-[15px] leading-5 font-semibold">{f.name}</span>
      <span class="text-[13px] leading-[18px] text-dm-ink-2">{detalle}</span>
    </span>
    {#if quitar}
      <button
        type="button"
        aria-label="Quitar archivo"
        onclick={quitarArchivo}
        class="ml-auto flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-dm-ink-2 hover:bg-dm-tarjeta hover:text-dm-ink"
      >
        <X size={18} aria-hidden="true" />
      </button>
    {/if}
  </div>
{/snippet}

{#if paso !== 'subiendo' && paso !== 'error'}
  <ol aria-label="Pasos de la entrega" class="flex shrink-0 items-center gap-2 border-b border-dm-linea px-4 py-3.5 md:px-6">
    {#each PASOS as etiqueta, i (etiqueta)}
      {@const hecho = i < indicePaso || (i === 2 && paso === 'exito')}
      {@const actual = i === indicePaso && !hecho}
      <li aria-current={actual ? 'step' : undefined} class="flex items-center gap-2 {i < PASOS.length - 1 ? 'flex-1' : 'flex-none'}">
        <span
          class="flex size-7 shrink-0 items-center justify-center rounded-full border-2 text-[13px] font-extrabold {hecho
            ? 'border-dm-accion bg-dm-accion text-dm-sobre-accion'
            : actual
              ? 'border-dm-accion bg-dm-tarjeta text-dm-accion-texto'
              : 'border-dm-control bg-dm-tarjeta text-dm-ink-3'}"
        >
          {#if hecho}<Check size={15} strokeWidth={3} aria-hidden="true" />{:else}{i + 1}{/if}
        </span>
        <span class="text-sm leading-5 whitespace-nowrap {actual ? 'font-bold text-dm-ink' : hecho ? 'font-semibold text-dm-ink' : 'text-dm-ink-3'}">
          {etiqueta}<span class="sr-only">{hecho ? ' (hecho)' : actual ? ' (paso actual)' : ''}</span>
        </span>
        {#if i < PASOS.length - 1}
          <span aria-hidden="true" class="h-0.5 min-w-3 flex-1 rounded-sm {hecho ? 'bg-dm-accion' : 'bg-dm-linea'}"></span>
        {/if}
      </li>
    {/each}
  </ol>
{/if}

{#if paso === 'seleccion'}
  <div class={CUERPO}>
    {#if archivo}
      {@render fichaArchivo(archivo, peso(archivo), true)}
    {:else}
      <button
        type="button"
        onclick={() => selector?.click()}
        ondragover={(e) => {
          e.preventDefault();
          arrastrando = true;
        }}
        ondragleave={() => (arrastrando = false)}
        ondrop={alSoltar}
        class="flex w-full cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed px-4 py-7 text-center transition-colors {arrastrando
          ? 'border-dm-accion bg-dm-accion-suave'
          : 'border-dm-control bg-dm-sutil hover:bg-dm-pagina'}"
      >
        <UploadCloud size={28} aria-hidden="true" class="text-dm-accion-texto" />
        <span class="text-base leading-[22px] font-bold max-md:hidden">Arrastra el archivo aquí</span>
        <span class="inline-flex min-h-11 items-center rounded-[10px] border border-dm-control bg-dm-tarjeta px-4 text-[15px] font-semibold">
          <span class="max-md:hidden">o elige uno de tu equipo</span><span class="md:hidden">Elegir archivo</span>
        </span>
        <span class="text-[13px] leading-[18px] text-dm-ink-2">PDF o archivo comprimido · hasta 100 MB según el tipo</span>
      </button>
    {/if}
    <input
      bind:this={selector}
      type="file"
      class="sr-only"
      tabindex="-1"
      aria-hidden="true"
      onchange={(e) => elegirArchivo((e.currentTarget as HTMLInputElement).files?.[0])}
    />

    <div class="flex flex-col gap-1.5">
      <label for="entrega-descripcion" class="text-sm leading-5 font-semibold">
        Descripción de la entrega {#if entrega_obligatoria}<span class="font-normal text-dm-ink-2">(obligatoria)</span>{/if}
      </label>
      <textarea
        id="entrega-descripcion"
        bind:value={descripcion}
        rows="3"
        maxlength={MAX_DESCRIPCION}
        placeholder="Qué incluye la entrega, cambios realizados, comentarios importantes…"
        class="min-h-22 resize-none rounded-[10px] border border-dm-control bg-dm-tarjeta px-3 py-2.5 font-dm text-base leading-[22px] text-dm-ink placeholder:text-dm-ink-3 focus:border-dm-enlace focus:outline-2 focus:outline-offset-0 focus:outline-dm-enlace md:text-[15px]"
      ></textarea>
      <span class="self-end text-[13px] leading-[18px] text-dm-ink-3 tabular-nums">{descripcion.length} / {MAX_DESCRIPCION}</span>
    </div>
  </div>
  <div class={PIE}>
    {#if !listaParaConfirmar}
      <span class="mr-auto text-[13px] leading-[18px] text-dm-ink-2">
        {archivo ? 'Escribe la descripción para seguir.' : faltaDescripcion ? 'Elige el archivo y escribe la descripción para seguir.' : 'Elige el archivo para seguir.'}
      </span>
    {/if}
    <button type="button" class={TEXTO} onclick={onCerrar}>Cancelar</button>
    <button type="button" class={PRIMARIO} disabled={!listaParaConfirmar} onclick={irAConfirmar}>Continuar</button>
  </div>
{:else if paso === 'confirmando' && archivo}
  <div class={CUERPO}>
    {@render fichaArchivo(archivo, `${peso(archivo)}${descripcion.trim() ? ' · con descripción' : ''}`, false)}
    <div class="flex flex-col gap-2">
      <span class="text-sm leading-5 font-bold">Al confirmar</span>
      <ul class="flex flex-col gap-1.5 text-sm leading-5 text-dm-ink-2">
        {#each alConfirmar as linea (linea)}
          <li class="flex items-start gap-2"><Check size={16} aria-hidden="true" class="mt-0.5 shrink-0 text-dm-ok" />{linea}</li>
        {/each}
      </ul>
    </div>
    <button
      type="button"
      role="checkbox"
      aria-checked={confirmado}
      onclick={() => (confirmado = !confirmado)}
      class="flex min-h-12 w-full cursor-pointer items-start gap-3 rounded-[10px] border px-3.5 py-3 text-left text-[15px] leading-[22px] {confirmado
        ? 'border-dm-accion bg-dm-accion-suave'
        : 'border-dm-control bg-dm-tarjeta hover:bg-dm-sutil'}"
    >
      <span
        aria-hidden="true"
        class="flex size-5.5 shrink-0 items-center justify-center rounded-[5px] border-2 {confirmado
          ? 'border-dm-accion bg-dm-accion text-dm-sobre-accion'
          : 'border-dm-control bg-dm-tarjeta'}"
      >
        {#if confirmado}<Check size={14} strokeWidth={3} />{/if}
      </span>
      <span>
        {esGrupal
          ? 'Confirmo que este archivo es la entrega de mi grupo en la actividad.'
          : 'Confirmo que este archivo es mi entrega de la actividad.'}
      </span>
    </button>
  </div>
  <div class={PIE}>
    <button type="button" class="{SECUNDARIO} mr-auto" onclick={() => (paso = 'seleccion')}>Volver</button>
    <button type="button" class={PRIMARIO} disabled={!confirmado} onclick={confirmarEntrega}>
      <Upload size={18} aria-hidden="true" />Entregar ahora
    </button>
  </div>
{:else if paso === 'subiendo'}
  <div class="flex flex-col gap-3.5 px-4 pt-7 pb-8 md:px-6">
    <div class="flex items-center gap-2.5">
      <LoaderCircle size={20} aria-hidden="true" class="animate-spin text-dm-accion-texto" />
      <span class="text-base leading-[22px] font-bold">Entregando la actividad</span>
      <span class="ml-auto text-[15px] font-bold text-dm-accion-texto tabular-nums">{progreso} %</span>
    </div>
    <div
      role="progressbar"
      aria-label="Avance de la subida"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={progreso}
      class="h-2.5 overflow-hidden rounded-full bg-dm-sutil"
    >
      <div class="h-full rounded-full bg-dm-accion transition-[width]" style="width: {progreso}%"></div>
    </div>
    <span class="text-sm leading-5 text-dm-ink-2">
      {archivo?.name}{archivo ? ` · ${peso(archivo)}` : ''}. No cierres esta ventana mientras se sube.
    </span>
  </div>
{:else if paso === 'exito' && archivo}
  <div role="status" class="flex flex-col gap-4 px-4 py-6 md:px-6">
    <div class="flex items-center gap-3">
      <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-dm-ok-s text-dm-ok">
        <CircleCheck size={24} aria-hidden="true" />
      </span>
      <span class="flex flex-col">
        <span class="text-lg leading-6 font-bold">Entrega registrada</span>
        <span class="text-sm leading-5 text-dm-ink-2">
          {esGrupal ? 'Tu grupo la ve en la agenda de la actividad.' : 'Tu equipo docente la ve en la agenda de la actividad.'}
        </span>
      </span>
    </div>
    <dl class="grid grid-cols-[72px_minmax(0,1fr)] gap-x-3 gap-y-2 rounded-xl bg-dm-sutil px-4 py-3.5 text-sm leading-5">
      <dt class="text-dm-ink-2">Archivo</dt>
      <dd class="truncate font-semibold">{archivo.name}</dd>
      <dt class="text-dm-ink-2">Peso</dt>
      <dd>{peso(archivo)}</dd>
      <dt class="text-dm-ink-2">Hora</dt>
      <dd class="font-semibold">{horaRegistro} hrs</dd>
    </dl>
  </div>
  <div class={PIE}>
    <button type="button" class={PRIMARIO} onclick={completar}>Listo</button>
  </div>
{:else if paso === 'error'}
  <div class="flex flex-col gap-3.5 px-4 py-5 md:px-6">
    <div role="alert" class="flex items-start gap-3 rounded-[10px] border-l-4 border-dm-error bg-dm-error-s px-4 py-3.5">
      <CircleAlert size={20} aria-hidden="true" class="mt-0.5 shrink-0 text-dm-error" />
      <span class="flex flex-col gap-1">
        <span class="text-base leading-[22px] font-bold">La entrega no se completó</span>
        <span class="text-sm leading-5">{mensajeError}</span>
      </span>
    </div>
    <p class="text-sm leading-5 text-dm-ink-2">
      No quedó ninguna entrega a medias.{#if archivo}
        Tu archivo sigue elegido: <strong class="font-semibold break-all text-dm-ink">{archivo.name}</strong>.{/if}
    </p>
  </div>
  <div class={PIE}>
    {#if onAvisarDocente}
      <button type="button" class="{TEXTO} mr-auto" onclick={onAvisarDocente}>Avisar al docente</button>
    {/if}
    <button type="button" class={SECUNDARIO} onclick={cambiarArchivo}>Cambiar archivo</button>
    {#if archivo}
      <button type="button" class={PRIMARIO} onclick={reintentar}><RotateCcw size={18} aria-hidden="true" />Reintentar</button>
    {/if}
  </div>
{/if}
