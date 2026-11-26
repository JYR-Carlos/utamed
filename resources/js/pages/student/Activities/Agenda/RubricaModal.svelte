<script lang="ts">
  /**
   * La rúbrica de una actividad en una ventana, desde el ícono que va al lado
   * de su nombre (lista del curso y página de la actividad): se mira sin salir
   * de donde se está. Se pide al abrirla: la lista no la trae.
   */
  import { untrack } from 'svelte';
  import ModalAlumno from '@/components/student/dm/ModalAlumno.svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import RubricaAlumno from './RubricaAlumno.svelte';
  import { csrfFetch } from '@/lib/csrf';
  import { btnPrimario } from '@/lib/dm';
  import { titleCase } from '@/lib/utils';
  import type { Rubrica } from '@/types/rubrica';
  import { formatDiaCorto } from '@/utils/formatters';

  interface RubricaDeActividad {
    rubrica: Rubrica | null;
    resultado: Record<string, string> | null;
    puntaje_obtenido: number | string | null;
    nota: number | null;
    exigencia: number;
    evaluador: string | null;
    fecha: string | null;
    retroalimentacion: string | null;
  }

  interface Props {
    open: boolean;
    idCurso: number;
    idActividad: number | string;
    nombre: string;
    curso?: string;
    /** Variables --curso* (estiloCurso). */
    estilo?: string;
  }

  let { open = $bindable(), idCurso, idActividad, nombre, curso = '', estilo = '' }: Props = $props();

  let datos = $state<RubricaDeActividad | null>(null);
  let error = $state(false);

  async function cargar(id: number | string) {
    datos = null;
    error = false;
    try {
      const r = await csrfFetch(`/estudiante/cursos/${idCurso}/actividades/${id}/rubrica`, { headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error(String(r.status));
      const recibidos = (await r.json()) as RubricaDeActividad;
      // Se cerró y se abrió la de otra actividad mientras llegaba ésta.
      if (id === idActividad) datos = recibidos;
    } catch {
      if (id === idActividad) error = true;
    }
  }

  // Sólo al abrirla: los refrescos de la página cambian las props sin cambiar de actividad.
  $effect(() => {
    if (open) untrack(() => cargar(idActividad));
  });

  const subtitulo = $derived.by(() => {
    const fecha = datos?.fecha ? new Date(datos.fecha) : null;
    return [datos?.evaluador ? titleCase(datos.evaluador) : '', fecha && !isNaN(fecha.getTime()) ? formatDiaCorto(fecha) : '']
      .filter(Boolean)
      .join(' · ');
  });
</script>

<ModalAlumno
  bind:open
  variante="completa"
  etiqueta={curso ? `Rúbrica · ${curso}` : 'Rúbrica'}
  titulo={nombre}
  {estilo}
  rotuloCerrar="Cerrar la rúbrica"
>
  <div class="min-h-0 flex-1 overflow-y-auto p-4 lg:p-6">
    {#if datos}
      <RubricaAlumno
        rubrica={datos.rubrica}
        resultado={datos.resultado}
        puntajeObtenido={datos.puntaje_obtenido}
        retroalimentacion={datos.retroalimentacion}
        {subtitulo}
        nota={datos.nota}
        exigencia={datos.exigencia}
        enVentana
      />
    {:else if error}
      <EstadoVacio tipo="error" titulo="No se pudo cargar la rúbrica" texto="Intenta de nuevo. Si vuelve a fallar, revisa tu conexión.">
        {#snippet accion()}
          <button type="button" class={btnPrimario} onclick={() => cargar(idActividad)}>Reintentar</button>
        {/snippet}
      </EstadoVacio>
    {:else}
      <EstadoVacio tipo="cargando" titulo="Cargando la rúbrica…" />
    {/if}
  </div>
</ModalAlumno>
