<script lang="ts">
  /**
   * Entregas de un estudiante o grupo (revisión «Mi UTAmed»): cada versión con
   * su archivo, quién la subió y cuándo, y si es la vigente. Se califica en la
   * agenda, no por versión: el pie lleva «Calificar en la agenda».
   *
   * D-03: las entregas se cargan con un fetch GET al abrir, mientras el
   * controlador no las exponga como prop diferida de Inertia.
   */
  import { ClipboardCheck, Download, Eye, FileText } from 'lucide-svelte';
  import ModalInst from '@/components/docente/ModalInst.svelte';
  import { btnPrimario, btnSecundario, chico, chip, tarjeta, tono } from '@/lib/inst';
  import { formatFechaHora } from '@/utils/formatters';

  // El mismo objeto que la lista entrega y que vuelve por onCalificar.
  type GrupoData = {
    grupo: number;
    etiqueta: string;
    integrantes: { id_estudiante: number; nombre_completo: string }[];
    [clave: string]: unknown;
  };

  type EntregaGrupo = {
    id_agenda: number;
    /** La última entrega del grupo; las anteriores quedaron reemplazadas. */
    vigente: boolean;
    /** Llegó después de la fecha límite original (puede estar dentro de la holgura). */
    fuera_de_plazo: boolean;
    fecha_envio: string;
    mensaje: string | null;
    tipo_registro: string | null;
    archivo: {
      uuid: string;
      nombre_original: string;
      extension: string | null;
      /** PDF o imagen: se puede abrir en el navegador con `?ver=1`. */
      visualizable?: boolean;
    } | null;
    usuario_emisor: { nombre: string; rut: string | null };
    evaluada: boolean;
  };

  interface Props {
    idCurso: number;
    idActividad: number;
    nombreActividad: string;
    grupo: GrupoData;
    esGrupal?: boolean;
    onCerrar: () => void;
    /** Abre la agenda del estudiante o grupo en «Evaluar». */
    onCalificar: (grupo: GrupoData) => void;
  }

  let { idCurso, idActividad, nombreActividad, grupo, esGrupal = true, onCerrar, onCalificar }: Props = $props();

  let entregas = $state<EntregaGrupo[]>([]);
  let loading = $state(false);
  let error = $state<string | null>(null);

  /** Más reciente arriba; la versión se numera desde la primera. */
  const versiones = $derived(
    entregas
      .filter((e) => e.archivo)
      .sort((a, b) => new Date(b.fecha_envio).getTime() - new Date(a.fecha_envio).getTime()),
  );

  const subtitulo = $derived(
    [nombreActividad, esGrupal ? grupo.integrantes.map((i) => i.nombre_completo).join(', ') : null]
      .filter(Boolean)
      .join(' · '),
  );

  function urlArchivo(agendaId: number): string {
    return `/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${grupo.grupo}/entregas/${agendaId}/descargar`;
  }

  async function cargarEntregas() {
    entregas = [];
    error = null;
    loading = true;
    try {
      const res = await fetch(`/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${grupo.grupo}/entregas`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(`Error ${res.status}`);
      entregas = await res.json();
    } catch (e: any) {
      error = e?.message ?? 'No se pudieron cargar las entregas.';
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    grupo.grupo;
    cargarEntregas();
  });
</script>

<ModalInst titulo="Entregas · {grupo.etiqueta}" {subtitulo} ancho={820} {onCerrar}>
  {#if loading}
    <div class="flex flex-col gap-3" aria-busy="true">
      {#each [1, 2] as k (k)}<div class="h-20 animate-pulse rounded-lg bg-inst-sutil"></div>{/each}
    </div>
  {:else if error}
    <p class="m-0 py-6 text-center text-inst-error" role="alert">{error}</p>
  {:else if versiones.length === 0}
    <div class="flex flex-col items-center gap-2 py-8 text-center">
      <FileText size={32} class="text-inst-ink-3" aria-hidden="true" />
      <p class="m-0 font-bold">Sin archivos entregados</p>
      <p class="m-0 text-inst-ink-2">
        {esGrupal ? 'El grupo aún no ha subido ningún archivo.' : 'El estudiante aún no ha subido ningún archivo.'}
      </p>
    </div>
  {:else}
    <ul class="{tarjeta} m-0 list-none p-0">
      {#each versiones as e, i (e.id_agenda)}
        <li class="flex flex-wrap items-center gap-3 border-b border-inst-linea px-4.5 py-3.5 last:border-b-0 sm:flex-nowrap">
          <span
            aria-hidden="true"
            class="flex size-10 shrink-0 items-center justify-center rounded-md bg-inst-error-s text-inst-error"
          >
            <FileText size={20} />
          </span>
          <span class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="flex flex-wrap items-center gap-2">
              <span class="font-bold">Versión {versiones.length - i}</span>
              {#if e.vigente}
                <span class="{chip} {tono.ok}">Vigente</span>
              {:else}
                <span class="{chip} {tono.off}">Reemplazada</span>
              {/if}
              {#if e.fuera_de_plazo}<span class="{chip} {tono.warn}">Fuera de plazo</span>{/if}
            </span>
            <span class="break-all">{e.archivo!.nombre_original}</span>
            <span class="text-[13px] text-inst-ink-2">
              {formatFechaHora(e.fecha_envio)} · subió {e.usuario_emisor.nombre}
            </span>
          </span>
          {#if e.archivo!.visualizable}
            <a href="{urlArchivo(e.id_agenda)}?ver=1" target="_blank" rel="noopener noreferrer" class="{btnSecundario} {chico}">
              <Eye size={16} aria-hidden="true" />
              Ver
            </a>
          {/if}
          <a href={urlArchivo(e.id_agenda)} target="_blank" rel="noopener noreferrer" class="{btnSecundario} {chico}">
            <Download size={16} aria-hidden="true" />
            Descargar
          </a>
        </li>
      {/each}
    </ul>
  {/if}

  {#snippet pie()}
    <button type="button" class="{btnSecundario} ml-auto" onclick={onCerrar}>Cerrar</button>
    <button type="button" class={btnPrimario} onclick={() => onCalificar(grupo)}>
      <ClipboardCheck size={16} aria-hidden="true" />
      Calificar en la agenda
    </button>
  {/snippet}
</ModalInst>
