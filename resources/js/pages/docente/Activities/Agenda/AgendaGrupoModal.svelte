<script lang="ts">
  /**
   * La agenda de un estudiante (o grupo) abierta sobre otra pantalla, como el
   * Inicio (revisión «Mi UTAmed»: «Al cerrarla se sigue en el inicio»).
   *
   * Pide sus datos al endpoint JSON de la agenda del grupo y guarda con las
   * mismas peticiones que la actividad desplegada (guardarAgenda.ts). Tras
   * cada guardado recarga sus datos y las props de la página que se indiquen.
   */
  import { onMount } from 'svelte';
  import { page } from '@inertiajs/svelte';
  import ModalInst from '@/components/docente/ModalInst.svelte';
  import type { Rubrica } from '@/types/rubrica';
  import type { InteraccionItem } from '@/types/agenda';
  import { aviso, avisoTono } from '@/lib/inst';
  import { estadoEntrega } from '@/lib/estadoEntrega';
  import type { ActividadAbierta, GrupoData } from '../components/ActividadDetalle.svelte';
  import AgendaDocente, { type EnvioAgenda } from './AgendaDocente.svelte';
  import { conOptimistas, guardarEvaluacion, mensajeOptimista, pedirHilo, primerError, urlFeedback, visitaInertia } from './guardarAgenda';

  interface Props {
    idCurso: number;
    idActividad: number;
    idGrupo: number;
    /** Quién escribió (individual: el estudiante; grupal: el integrante o el grupo). */
    nombre: string;
    pestana?: 'escribir' | 'evaluar';
    pieCerrar: string;
    /** Props de la página que cambian al guardar (p. ej. la agenda pendiente). */
    recargar?: string[];
    onCerrar: () => void;
  }

  let { idCurso, idActividad, idGrupo, nombre, pestana = 'escribir', pieCerrar, recargar = [], onCerrar }: Props = $props();

  type Datos = {
    curso: { id_curso: number; nombre: string };
    actividad: ActividadAbierta;
    grupo: GrupoData;
    rubrica: Rubrica | null;
    rubrica_id: number | null;
    interacciones: InteraccionItem[];
  };

  const url = $derived(`/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${idGrupo}`);
  let datos = $state<Datos | null>(null);
  let error = $state<string | null>(null);
  let optimistas = $state<InteraccionItem[]>([]);
  let guardando = $state(false);

  async function cargar() {
    const r = await fetch(`${url}/agenda`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!r.ok) throw new Error(String(r.status));
    datos = await r.json();
  }

  async function refrescar() {
    const previos = datos;
    if (!previos) return;
    const interacciones = await pedirHilo(url);
    // Si un guardado recargó los datos, esta respuesta es más vieja.
    if (datos === previos) datos.interacciones = interacciones;
  }

  onMount(() => {
    cargar().catch(() => (error = 'No se pudo abrir la agenda.'));
  });

  const visita = $derived(visitaInertia([...recargar, 'flash']));

  async function enviar(data: EnvioAgenda) {
    if (!datos) return;
    const d = datos;
    error = null;

    if (data.tipo === 'Feedback') {
      const temporal = mensajeOptimista(($page.props as any).auth?.user, data.mensaje);
      optimistas = [...optimistas, temporal];
      try {
        await visita('post', urlFeedback(idCurso, idGrupo), { mensaje: data.mensaje });
        await cargar();
      } catch {
        error = 'No se pudo enviar el mensaje.';
      } finally {
        optimistas = optimistas.filter((o) => o.id_interaccion !== temporal.id_interaccion);
      }
      return;
    }

    guardando = true;
    try {
      await guardarEvaluacion(visita, {
        idCurso,
        idActividad,
        grupo: d.grupo,
        esSumativa: d.actividad.es_sumativa,
        rubricaId: d.rubrica_id,
        envio: data,
      });
      await cargar();
    } catch (e) {
      error = primerError(e, 'No se pudo guardar la evaluación.');
    } finally {
      guardando = false;
    }
  }
</script>

{#if datos}
  {@const a = datos.actividad}
  {@const g = datos.grupo}
  <AgendaDocente
    {nombre}
    subtitulo={[datos.curso.nombre, a.nombre, a.es_sumativa ? 'Sumativa' : 'Formativa', a.es_grupal ? g.etiqueta : null].filter(Boolean).join(' · ')}
    estado={estadoEntrega(g, a.es_sumativa, a.trae_archivo)}
    esSumativa={a.es_sumativa}
    esGrupal={a.es_grupal}
    nombreGrupo={g.etiqueta}
    integrantes={g.integrantes}
    notaActual={g.nota}
    rubrica={datos.rubrica}
    urlEntrega={(id, ver) => `${url}/entregas/${id}/descargar${ver ? '?ver=1' : ''}`}
    listado_interacciones={conOptimistas(datos.interacciones, optimistas)}
    errorMensaje={error}
    pestanaInicial={pestana}
    {pieCerrar}
    {guardando}
    onEnviar={enviar}
    onRefrescar={refrescar}
    {onCerrar}
  />
{:else}
  <ModalInst titulo="Agenda del estudiante · {nombre}" {onCerrar}>
    {#if error}
      <div class="{aviso} {avisoTono.error}" role="alert"><p class="m-0 font-bold">{error}</p></div>
    {:else}
      <p class="m-0 py-6 text-center text-inst-ink-2" role="status">Cargando la agenda…</p>
    {/if}
  </ModalInst>
{/if}
