<script lang="ts">
  import {
    CalendarClock,
    AlertTriangle,
    Lock,
    CheckCircle2,
    ExternalLink,
    Upload,
    Info,
    RotateCcw,
    Eye,
    Trash2
  } from 'lucide-svelte';
  import { formatBytes, formatFechaHora, parseFechaSoloDia } from '@/utils/formatters';

  interface Props {
    fecha_limite: string;
    dias_holgura: number;
    dias_holgura_personal?: number;
    estado?: string | null;
    entrega_obligatoria: boolean;
    esGrupal: boolean;
    yaEvaluada: boolean;
    puedeSubirArchivo: boolean;
    ultima_entrega?: {
      id_interaccion: number;
      fecha_emision: string;
      archivo?: {
        nombre_original: string | null;
        peso_bytes: number | null;
        mime_type?: string | null;
        visualizable?: boolean;
      } | null;
    } | null;
    urlDescarga?: string | null;
    onSubirClick: () => void;
    onReemplazarClick: () => void;
    onBorrarClick?: () => void;
  }

  let {
    fecha_limite,
    dias_holgura,
    dias_holgura_personal = 0,
    estado,
    entrega_obligatoria,
    esGrupal,
    yaEvaluada,
    puedeSubirArchivo,
    ultima_entrega = null,
    urlDescarga = null,
    onSubirClick,
    onReemplazarClick,
    onBorrarClick,
  }: Props = $props();

  function addDays(date: Date, days: number): Date {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
  }

  const fechaBase = $derived.by(() => parseFechaSoloDia(fecha_limite));
  const fechaEfectiva = $derived.by(() =>
    addDays(fechaBase, (dias_holgura || 0) + (dias_holgura_personal || 0)),
  );

  const vencioBase = $derived(
    new Date() > new Date(fechaBase.getFullYear(), fechaBase.getMonth(), fechaBase.getDate(), 23, 59, 59),
  );

  const estadoVisual = $derived.by((): 'en_plazo' | 'fuera_de_plazo' | 'cerrada' => {
    if (estado === 'CERRADA') return 'cerrada';
    return vencioBase ? 'fuera_de_plazo' : 'en_plazo';
  });

  const diasRestantes = $derived.by(() => {
    const ms = fechaEfectiva.getTime() - new Date().getTime();
    return Math.max(0, Math.ceil(ms / 86_400_000));
  });

  const hayHolguraPersonal = $derived((dias_holgura_personal || 0) > 0);
  const tieneEntrega = $derived(ultima_entrega !== null);

  // Formato DD/MM sin año
  const fechaCortaDDMM = $derived.by(() => {
    const d = fechaEfectiva;
    const dia = String(d.getDate()).padStart(2, '0');
    const mes = String(d.getMonth() + 1).padStart(2, '0');
    return `${dia}/${mes}`;
  });

  // Tipo de archivo legible para la esquina superior izquierda
  const tipoArchivoTexto = $derived.by(() => {
    const ext = ultima_entrega?.archivo?.nombre_original?.split('.').pop()?.toUpperCase();
    const mime = ultima_entrega?.archivo?.mime_type?.toLowerCase() || '';
    if (ext) return `Archivo ${ext}`;
    if (mime.includes('pdf')) return 'Archivo PDF';
    if (mime.includes('image')) return 'Imagen';
    if (mime.includes('zip') || mime.includes('compressed')) return 'Archivo comprimido';
    return 'Archivo adjunto';
  });

  // Estilos del contenedor según estado (borde izquierdo grueso de 8px y fondo ligeramente tintado)
  const estilos = {
    en_plazo: {
      card: 'border-l-8 border-l-emerald-600 border-[#D1F2D9] bg-[#F2FAF4]',
      badge: 'bg-emerald-100/80 text-emerald-800 border-emerald-300',
      dot: 'bg-emerald-600',
      icon: 'text-emerald-700',
      label: 'En plazo',
    },
    fuera_de_plazo: {
      card: 'border-l-8 border-l-amber-500 border-[#FDE8C3] bg-[#FEF9EE]',
      badge: 'bg-amber-100 text-amber-800 border-amber-300',
      dot: 'bg-amber-600',
      icon: 'text-amber-700',
      label: 'Fuera de plazo',
    },
    cerrada: {
      card: 'border-l-8 border-l-slate-400 border-[#E2E8F0] bg-[#F8FAFC]',
      badge: 'bg-slate-200/80 text-slate-700 border-slate-300',
      dot: 'bg-slate-500',
      icon: 'text-slate-600',
      label: 'Cerrada',
    },
  } as const;

  const s = $derived(estilos[estadoVisual]);

  const esVisualizable = $derived.by(() => {
    if (ultima_entrega?.archivo?.visualizable !== undefined) {
      return Boolean(ultima_entrega.archivo.visualizable);
    }
    const mime = ultima_entrega?.archivo?.mime_type?.toLowerCase() || '';
    return mime === 'application/pdf' || mime.startsWith('image/');
  });
</script>

<section class="flex flex-col gap-4 rounded-xl border p-5 shadow-xs md:p-6 {s.card}">
  <!-- Cabecera: Tiempo restante + Badge de estado -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2.5">
      {#if estadoVisual === 'cerrada'}
        <Lock class="h-5 w-5 {s.icon}" />
        <h2 class="text-lg md:text-xl font-bold tracking-tight text-[#1A1A24]">
          Actividad cerrada
        </h2>
      {:else if estadoVisual === 'fuera_de_plazo'}
        <AlertTriangle class="h-5 w-5 {s.icon}" />
        <h2 class="text-lg md:text-xl font-bold tracking-tight text-amber-900">
          {diasRestantes === 0 ? 'Vence hoy (con holgura)' : `${diasRestantes} ${diasRestantes === 1 ? 'día restante' : 'días restantes'} con holgura`}
        </h2>
      {:else}
        <CalendarClock class="h-5 w-5 {s.icon}" />
        <h2 class="text-lg md:text-xl font-bold tracking-tight text-[#1A1A24]">
          {diasRestantes === 0 ? 'Vence hoy' : `${diasRestantes} ${diasRestantes === 1 ? 'día restante' : 'días restantes'}`}
        </h2>
      {/if}
    </div>

    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold {s.badge}">
      <span class="h-1.5 w-1.5 rounded-full {s.dot}"></span>
      {s.label}
    </span>
  </div>

  <!-- Fila Principal: Fecha grande (izq) | Separador | Contenedor Padre de Entrega (der: Subida + Info) -->
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-center pt-1">
    <!-- Fecha de entrega (Mucho más grande, sin año) -->
    <div class="flex flex-col justify-center md:col-span-4">
      <span class="text-[11px] font-bold uppercase tracking-wider text-[#5A5E6E]">
        Fecha de entrega
      </span>
      <div class="flex items-baseline gap-2.5 mt-1">
        <span class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight text-[#1A1A24] leading-none">
          {fechaCortaDDMM}
        </span>
        <span class="text-xs font-semibold text-[#5A5E6E]">23:59 hrs</span>
      </div>
      {#if hayHolguraPersonal && estadoVisual !== 'cerrada'}
        <span class="mt-1.5 inline-flex items-center text-[11px] font-medium text-emerald-800">
          +{dias_holgura_personal} {dias_holgura_personal === 1 ? 'día' : 'días'} de holgura personal
        </span>
      {/if}
    </div>

    <!-- Separador vertical en desktop -->
    <div class="hidden md:flex md:col-span-1 justify-center items-center">
      <div class="h-20 w-px bg-black/10"></div>
    </div>

    <!-- Contenedor Padre Derecho: Botón de Entrega (arriba) + Info (abajo) (7 cols) -->
    <div class="flex flex-col gap-2.5 md:col-span-7">
      {#if entrega_obligatoria}
        {#if tieneEntrega && ultima_entrega}
          <!-- CASO CON ARCHIVO: Color más claro (#185FA5 en vez del azul oscuro #002855) -->
          <button
            type="button"
            onclick={puedeSubirArchivo ? onReemplazarClick : undefined}
            disabled={!puedeSubirArchivo}
            class="group flex w-full min-h-[76px] md:min-h-[84px] items-center justify-between gap-4 rounded-xl bg-[#185FA5] p-4 text-white shadow-xs transition-all duration-150 {puedeSubirArchivo ? 'hover:bg-[#134D86] hover:shadow active:scale-[0.99] cursor-pointer' : 'opacity-95 cursor-default'}"
            title={puedeSubirArchivo ? 'Haz clic para reemplazar la entrega' : 'Entrega no modificable'}
          >
            <!-- Izquierda: Tipo de archivo arriba, Nombre del archivo abajo -->
            <div class="flex min-w-0 flex-1 flex-col items-start text-left gap-0.5">
              <span class="text-[11px] font-bold uppercase tracking-wider text-blue-100">
                {tipoArchivoTexto}
              </span>
              <span class="text-sm md:text-[15px] font-bold tracking-tight text-white truncate max-w-full">
                {ultima_entrega.archivo?.nombre_original ?? 'Archivo entregado'}
              </span>
              {#if ultima_entrega.archivo?.peso_bytes}
                <span class="font-mono text-[10.5px] text-blue-100/80">
                  {formatBytes(ultima_entrega.archivo.peso_bytes)}
                </span>
              {/if}
            </div>

            <!-- Derecha: Ícono encerrado en círculo de color más claro -->
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 border border-white/25 text-white transition-transform group-hover:scale-105">
              {#if puedeSubirArchivo}
                <RotateCcw class="h-5 w-5" />
              {:else}
                <CheckCircle2 class="h-5 w-5" />
              {/if}
            </div>
          </button>
        {:else}
          <!-- CASO SIN ARCHIVO: Colores Grises -->
          <button
            type="button"
            onclick={puedeSubirArchivo ? onSubirClick : undefined}
            disabled={!puedeSubirArchivo}
            class="group flex w-full min-h-[76px] md:min-h-[84px] items-center justify-between gap-4 rounded-xl border border-slate-300 bg-slate-200/70 p-4 text-slate-700 shadow-2xs transition-all duration-150 {puedeSubirArchivo ? 'hover:bg-slate-200 hover:border-slate-400 active:scale-[0.99] cursor-pointer' : 'opacity-80 cursor-default'}"
            title={puedeSubirArchivo ? 'Haz clic para subir tu entrega' : 'Período cerrado'}
          >
            <!-- Izquierda: Estado cuando no hay nada -->
            <div class="flex min-w-0 flex-1 flex-col items-start text-left gap-0.5">
              <span class="text-[11px] font-bold uppercase italic tracking-wider text-slate-500">
                {esGrupal ? 'Entrega grupal' : 'Entrega individual'}
              </span>
              <span class="text-sm md:text-[15px] font-semibold text-slate-800 leading-tight">
                {esGrupal ? 'No se ha subido un archivo grupal' : 'No se ha subido ningún archivo'}
              </span>
            </div>

            <!-- Derecha: Ícono encerrado en círculo de color más claro -->
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/80 border border-slate-300 text-slate-600 transition-transform group-hover:scale-105">
              <Upload class="h-5 w-5" />
            </div>
          </button>
        {/if}

        <!-- Wrapper de Info y Ver Entrega (Horizontal, sin fondo ni borde, todo el ancho) -->
        <div class="flex w-full items-center justify-between gap-3 px-0.5 text-xs">
          <div class="flex items-center gap-1.5 min-w-0">
            {#if yaEvaluada}
              <Info class="h-3.5 w-3.5 shrink-0 text-slate-500" />
              <span class="truncate text-[11.5px] font-medium text-slate-700">
                Calificada (no modificable)
              </span>
            {:else if estado === 'CERRADA'}
              <AlertTriangle class="h-3.5 w-3.5 shrink-0 text-amber-600" />
              <span class="truncate text-[11.5px] font-medium text-amber-900">
                Plazo cerrado
              </span>
            {:else if tieneEntrega && puedeSubirArchivo}
              <Info class="h-3.5 w-3.5 shrink-0 text-emerald-700" />
              <span class="truncate text-[11.5px] font-medium text-emerald-900">
                Reemplazable hasta el {fechaCortaDDMM}
              </span>
            {:else if !tieneEntrega && puedeSubirArchivo}
              <Info class="h-3.5 w-3.5 shrink-0 text-slate-500" />
              <span class="truncate text-[11.5px] font-medium text-slate-600">
                Haz clic arriba para adjuntar
              </span>
            {/if}
          </div>

          <!-- Acciones de Entrega: Ver (con ícono de ojo) | Basurero (solo ícono) -->
          {#if tieneEntrega}
            <div class="flex items-center gap-2.5 shrink-0">
              {#if urlDescarga}
                <a
                  href={esVisualizable ? `${urlDescarga}?ver=1` : urlDescarga}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex shrink-0 items-center gap-1 text-[11.5px] font-semibold text-[#185FA5] hover:text-[#114275] hover:underline"
                  title={esVisualizable ? 'Ver entrega en línea' : 'Descargar entrega'}
                >
                  <Eye class="h-3.5 w-3.5" />
                  Ver
                </a>
              {/if}

              {#if onBorrarClick}
                <!-- Separador vertical -->
                <div class="h-3.5 w-px bg-slate-300"></div>

                <!-- Botón con solo ícono de basurero para borrar entrega -->
                <button
                  type="button"
                  onclick={puedeSubirArchivo ? onBorrarClick : undefined}
                  disabled={!puedeSubirArchivo}
                  class="inline-flex shrink-0 items-center justify-center p-0.5 text-slate-400 transition-colors {puedeSubirArchivo ? 'hover:text-red-600 cursor-pointer' : 'opacity-40 cursor-not-allowed'}"
                  title={puedeSubirArchivo ? 'Borrar entrega' : 'No es posible borrar esta entrega'}
                  aria-label="Borrar entrega"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              {/if}
            </div>
          {/if}
        </div>
      {/if}
    </div>
  </div>
</section>
