<script lang="ts">
  /**
   * EntregasModal — Modal con las entregas (archivos) de un grupo para una
   * actividad. Carga los datos de forma diferida (lazy) vía fetch GET cuando se
   * abre y permite descargar, evaluar con rúbrica o saltar a la agenda del grupo.
   *
   * D-03: el fetch GET es temporal mientras el controlador no exponga las
   * entregas como prop lazy de Inertia (ver TODO en cargarEntregas).
   */
  import { X, FileText, CheckCircle2, Clock, User, Download, MessageSquare } from 'lucide-svelte';
  import { formatBytes, formatFechaHora } from '@/utils/formatters';
  import type { Rubrica } from '@/types/rubrica';

  type GrupoData = {
    grupo: number;
    nota: number | null;
    estado_actividad_asignada: string | null;
    integrantes: { id_estudiante: number; nombre_completo: string }[];
  };

  type EntregaGrupo = {
    id_agenda: number;
    fecha_envio: string;
    mensaje: string | null;
    tipo_registro: string | null;
    archivo: {
      uuid: string;
      nombre_original: string;
      extension: string | null;
      mime_type: string | null;
      peso_bytes: number | null;
      fecha_creacion: string | null;
    } | null;
    usuario_emisor: { nombre: string; rut: string | null };
    evaluada: boolean;
  };

  interface Props {
    idCurso: number;
    idActividad: number;
    nombreActividad: string;
    grupo: GrupoData;
    rubrica?: Rubrica | null;
    rubricaId?: number | null;
    onCerrar: () => void;
    onEvaluar: (idAgenda: number | null) => void;
    onVerAgenda: (grupo: GrupoData) => void;
  }

  let {
    idCurso,
    idActividad,
    nombreActividad,
    grupo,
    rubrica = null,
    rubricaId = null,
    onCerrar,
    onEvaluar,
    onVerAgenda,
  }: Props = $props();

  let entregas = $state<EntregaGrupo[]>([]);
  let loading = $state(false);
  let error = $state<string | null>(null);

  const entregasConArchivo = $derived(entregas.filter((e) => e.archivo));

  function downloadUrl(agendaId: number): string {
    return `/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${grupo.grupo}/entregas/${agendaId}/descargar`;
  }

  // TODO(D-03): migrar a router.reload({ only:['entregas'] }) cuando el
  // controlador exponga la prop lazy. Por ahora se usa fetch GET (sin mutaciones
  // ni CSRF) con manejo de errores unificado en `error`.
  async function cargarEntregas() {
    entregas = [];
    error = null;
    loading = true;
    try {
      const res = await fetch(
        `/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${grupo.grupo}/entregas`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error(`Error ${res.status}`);
      entregas = await res.json();
    } catch (e: any) {
      error = e?.message ?? 'No se pudieron cargar las entregas.';
    } finally {
      loading = false;
    }
  }

  // Recarga cuando cambia el grupo abierto (incluida la apertura inicial).
  $effect(() => {
    grupo.grupo;
    cargarEntregas();
  });
</script>

<div
  class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50"
  role="presentation"
  onclick={(e) => e.target === e.currentTarget && onCerrar()}
>
  <div
    class="bg-white w-full sm:max-w-2xl rounded-t-3xl sm:rounded-2xl shadow-2xl flex flex-col max-h-[90vh]"
  >
    <!-- Encabezado -->
    <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
      <div>
        <h3 class="text-base font-bold text-gray-900">
          Entregas — Grupo #{grupo.grupo}
        </h3>
        <p class="text-xs text-gray-500 mt-0.5">{nombreActividad}</p>
      </div>
      <button
        onclick={onCerrar}
        class="p-1.5 rounded-full hover:bg-gray-100 transition text-gray-400 hover:text-gray-700"
        aria-label="Cerrar"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <!-- Cuerpo -->
    <div class="flex-1 overflow-y-auto px-6 py-4">
      {#if loading}
        <div class="flex flex-col gap-3 animate-pulse py-4">
          {#each [1, 2, 3] as _}
            <div class="h-20 rounded-xl bg-gray-100"></div>
          {/each}
        </div>
      {:else if error}
        <div class="py-8 text-center text-sm text-red-500">{error}</div>
      {:else if entregasConArchivo.length === 0}
        <div class="py-12 flex flex-col items-center gap-3 text-center">
          <FileText class="w-10 h-10 text-gray-300" />
          <p class="text-sm font-semibold text-gray-500">Sin archivos entregados</p>
          <p class="text-xs text-gray-400">El grupo aún no ha subido ningún archivo.</p>
        </div>
      {:else}
        <div class="flex flex-col gap-3">
          {#each entregasConArchivo as entrega (entrega.id_agenda)}
            <div
              class="rounded-xl border {entrega.evaluada
                ? 'border-emerald-200 bg-emerald-50/40'
                : 'border-gray-200 bg-white'} p-4"
            >
              <!-- Cabecera de la entrega -->
              <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <!-- Badge tipo -->
                  <span
                    class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full
                      {entrega.tipo_registro === 'Entrega de archivo'
                      ? 'bg-uta-blue/10 text-uta-blue'
                      : 'bg-gray-100 text-gray-600'}"
                  >
                    <FileText class="w-3 h-3" />
                    {entrega.tipo_registro ?? 'Entrega'}
                  </span>
                  <!-- Badge estado evaluación -->
                  {#if entrega.evaluada}
                    <span
                      class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"
                    >
                      <CheckCircle2 class="w-3 h-3" />
                      Evaluada
                    </span>
                  {:else}
                    <span
                      class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700"
                    >
                      <Clock class="w-3 h-3" />
                      Pendiente
                    </span>
                  {/if}
                </div>
                <span class="text-[10px] text-gray-400 shrink-0">
                  {formatFechaHora(entrega.fecha_envio)}
                </span>
              </div>

              <!-- Emisor -->
              <div class="flex items-center gap-1.5 text-xs text-gray-600 mb-2">
                <User class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                <span class="font-medium">{entrega.usuario_emisor.nombre}</span>
                {#if entrega.usuario_emisor.rut}
                  <span class="text-gray-400">· {entrega.usuario_emisor.rut}</span>
                {/if}
              </div>

              <!-- Mensaje -->
              {#if entrega.mensaje}
                <p class="text-sm text-gray-700 mb-3 leading-relaxed">{entrega.mensaje}</p>
              {/if}

              <!-- Archivo -->
              {#if entrega.archivo}
                <div
                  class="flex items-center justify-between gap-3 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5"
                >
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div
                      class="w-8 h-8 rounded-lg bg-uta-blue/10 flex items-center justify-center shrink-0"
                    >
                      <FileText class="w-4 h-4 text-uta-blue" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-semibold text-gray-800 truncate">
                        {entrega.archivo.nombre_original}
                      </p>
                      <p class="text-[10px] text-gray-400">
                        {#if entrega.archivo.extension}
                          {entrega.archivo.extension.toUpperCase()}
                          {#if entrega.archivo.peso_bytes}&nbsp;·&nbsp;{/if}
                        {/if}
                        {formatBytes(entrega.archivo.peso_bytes)}
                      </p>
                    </div>
                  </div>
                  <div class="flex flex-col gap-1.5 shrink-0">
                    <a
                      href={downloadUrl(entrega.id_agenda)}
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-uta-blue text-white text-xs font-semibold rounded-lg hover:bg-uta-blue-hover transition-colors"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <Download class="w-3.5 h-3.5" />
                      Descargar
                    </a>
                    {#if rubrica && rubricaId && !entrega.evaluada}
                      <button
                        onclick={() => onEvaluar(entrega.id_agenda)}
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors"
                      >
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        Evaluar
                      </button>
                    {/if}
                  </div>
                </div>
              {/if}
            </div>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Footer -->
    <div class="px-6 py-3 border-t shrink-0 flex items-center justify-between gap-3 flex-wrap">
      <span class="text-xs text-gray-500">
        {entregasConArchivo.length} archivo{entregasConArchivo.length !== 1 ? 's' : ''}
        entregado{entregasConArchivo.length !== 1 ? 's' : ''}
      </span>
      <div class="flex items-center gap-2">
        <button
          onclick={onCerrar}
          class="px-4 py-2 text-sm border border-gray-200 rounded-xl text-gray-600 hover:bg-gray-50 transition"
        >
          Cerrar
        </button>
        {#if rubrica && rubricaId}
          <button
            onclick={() => onEvaluar(null)}
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition-colors"
          >
            <CheckCircle2 class="w-4 h-4" />
            Evaluar con Rúbrica
          </button>
        {/if}
        <button
          onclick={() => onVerAgenda(grupo)}
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-uta-blue text-white rounded-xl hover:bg-uta-blue-hover transition-colors"
        >
          <MessageSquare class="w-4 h-4" />
          Ver Agenda
        </button>
      </div>
    </div>
  </div>
</div>
