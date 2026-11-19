<script lang="ts">
  /**
   * Ficha del estudiante (revisión «Mi UTAmed»): modal sobre la pestaña
   * Estudiantes con el nombre, el RUT con formato y tres pestañas.
   *
   *  - General: promedio (nota del componente) y asistencia.
   *  - Contacto: correo institucional y lo que el alumno declaró en su perfil.
   *  - Evaluación: cada actividad publicada de su componente con su nota o
   *    calificación (o el estado de la entrega) y «Agenda», que abre su
   *    agenda encima de la ficha.
   *
   * «Enviar mensaje» lleva a su conversación en la mensajería del curso.
   */
  import { Link } from '@inertiajs/svelte';
  import { MessageSquare } from 'lucide-svelte';
  import ModalInst from '@/components/docente/ModalInst.svelte';
  import AgendaGrupoModal from '../Activities/Agenda/AgendaGrupoModal.svelte';
  import { aviso, avisoTono, btnSecundario, chico, chip, etiqueta, tarjeta, tono } from '@/lib/inst';
  import { textosEntrega, TONO_ENTREGA, type EstadoEntrega } from '@/lib/estadoEntrega';
  import { formatNota } from '@/utils/formatters';
  import { formatRut } from '@/lib/validators';

  interface Estudiante {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
    asistencia?: { presentes: number; total: number };
    estudiante: {
      id_estudiante: number;
      id_usuario?: number;
      nombre: string;
      username: string;
      rut?: string | null;
      email?: string | null;
      /** Contacto personal que declara el alumno (T10). Sólo lectura. */
      contacto?: {
        correo_personal: string | null;
        celular: string | null;
        redes_sociales: Record<'youtube' | 'x' | 'instagram' | 'linkedin', string | null>;
      };
    };
  }

  interface Props {
    abierto: boolean;
    estudiante: Estudiante;
    idCurso: number;
    /** «DM069 · Grupo A», para el subtítulo. */
    cursoEtiqueta?: string;
    onCerrar: () => void;
  }

  let { abierto, estudiante, idCurso, cursoEtiqueta, onCerrar }: Props = $props();

  type Pestana = 'general' | 'contacto' | 'evaluacion';
  let pestana = $state<Pestana>('evaluacion');

  type FilaEvaluacion = {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    grupo: number;
    nota: number | null;
    calificacion: string | null;
    estado: EstadoEntrega;
  };

  let filas = $state<FilaEvaluacion[] | null>(null);
  let error = $state<string | null>(null);
  let agendaDe = $state<FilaEvaluacion | null>(null);

  const url = $derived(
    `/docente/cursos/${idCurso}/componentes/${estudiante.id_componente}/estudiantes/${estudiante.estudiante.id_estudiante}/evaluacion`,
  );

  async function cargar() {
    error = null;
    try {
      const r = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      if (!r.ok) throw new Error(String(r.status));
      filas = await r.json();
    } catch {
      error = 'No se pudo cargar la evaluación del estudiante.';
    }
  }

  // Al abrir la ficha de otro estudiante se vuelve a «Evaluación» y se pide lo suyo.
  let cargadaPara = $state<number | null>(null);
  $effect(() => {
    if (abierto && cargadaPara !== estudiante.id_inscripcion_componente) {
      cargadaPara = estudiante.id_inscripcion_componente;
      pestana = 'evaluacion';
      filas = null;
      cargar();
    }
  });

  const e = $derived(estudiante.estudiante);
  const rut = $derived(e.rut ? formatRut(e.rut) : null);
  const asistencia = $derived(
    estudiante.asistencia && estudiante.asistencia.total > 0
      ? Math.round((estudiante.asistencia.presentes / estudiante.asistencia.total) * 100)
      : null,
  );
  const notasSumativas = $derived((filas ?? []).filter((f) => f.es_sumativa && f.nota !== null).length);

  const NOMBRE_RED: Record<string, string> = { youtube: 'YouTube', x: 'X', instagram: 'Instagram', linkedin: 'LinkedIn' };
  const redes = $derived(
    Object.entries(e.contacto?.redes_sociales ?? {}).filter(([, v]) => !!v) as [string, string][],
  );
  const contacto = $derived([
    ['Correo institucional', e.email ?? null],
    ['Correo personal', e.contacto?.correo_personal ?? null],
    ['Teléfono', e.contacto?.celular ?? null],
  ] as const);

  const TONO_CALIFICACION: Record<string, string> = { Bueno: tono.ok, Regular: tono.warn, Malo: tono.error };

  const PESTANAS: Array<[Pestana, string]> = [
    ['general', 'General'],
    ['contacto', 'Contacto'],
    ['evaluacion', 'Evaluación'],
  ];
  const fila = 'grid grid-cols-1 gap-1 border-b border-inst-linea py-3 last:border-b-0 sm:grid-cols-[200px_minmax(0,1fr)] sm:gap-4';
</script>

{#if abierto}
  <ModalInst titulo={e.nombre} subtitulo={[rut ? `RUT ${rut}` : null, cursoEtiqueta].filter(Boolean).join(' · ')} ancho={900} {onCerrar}>
    <div class="-mx-5 -mt-4 flex border-b border-inst-linea bg-inst-tarjeta px-5" role="tablist" aria-label="Ficha del estudiante">
      {#each PESTANAS as [id, texto] (id)}
        <button
          type="button"
          role="tab"
          aria-selected={pestana === id}
          class="-mb-px min-h-11 border-b-2 px-4 font-bold {pestana === id
            ? 'border-inst-accion text-inst-accion-t'
            : 'border-transparent text-inst-ink-2 hover:text-inst-ink'}"
          onclick={() => (pestana = id)}
        >
          {texto}
        </button>
      {/each}
    </div>

    {#if pestana === 'general'}
      <div class="{tarjeta} grid grid-cols-1 sm:grid-cols-2">
        <div class="flex flex-col gap-1 border-inst-linea px-5 py-4 max-sm:border-b sm:border-r">
          <span class={etiqueta}>Promedio</span>
          <span class="font-inst-titulo text-[28px] leading-8 font-bold">
            {estudiante.nota_componente !== null ? formatNota(estudiante.nota_componente) : '—'}
          </span>
          {#if filas !== null}
            <span class="text-[13px] text-inst-ink-2">
              {notasSumativas}
              {notasSumativas === 1 ? 'nota sumativa' : 'notas sumativas'}
            </span>
          {/if}
        </div>
        <div class="flex flex-col gap-1 px-5 py-4">
          <span class={etiqueta}>Asistencia</span>
          <span class="font-inst-titulo text-[28px] leading-8 font-bold">{asistencia !== null ? `${asistencia} %` : '—'}</span>
          <span class="text-[13px] text-inst-ink-2">
            {estudiante.asistencia && estudiante.asistencia.total > 0
              ? `${estudiante.asistencia.presentes} de ${estudiante.asistencia.total} sesiones`
              : 'Sin sesiones registradas'}
          </span>
        </div>
      </div>
    {:else if pestana === 'contacto'}
      <p class="m-0 text-[13px] text-inst-ink-2">Datos que el estudiante ingresó en su perfil.</p>
      <dl class="{tarjeta} m-0 px-4.5 py-1">
        {#each contacto as [clave, valor] (clave)}
          <div class={fila}>
            <dt class="{etiqueta} leading-5">{clave}</dt>
            <dd class="m-0 font-semibold break-words {valor ? '' : 'text-inst-ink-3'}">{valor || 'Sin registro'}</dd>
          </div>
        {/each}
        {#each redes as [red, enlace] (red)}
          <div class={fila}>
            <dt class="{etiqueta} leading-5">{NOMBRE_RED[red] ?? red}</dt>
            <dd class="m-0 font-semibold break-words">
              <a href={enlace} target="_blank" rel="noopener noreferrer" class="text-inst-enlace">{enlace}</a>
            </dd>
          </div>
        {/each}
      </dl>
    {:else if error}
      <div class="{aviso} {avisoTono.error}" role="alert"><p class="m-0 font-bold">{error}</p></div>
    {:else if filas === null}
      <p class="m-0 py-6 text-center text-inst-ink-2" role="status">Cargando la evaluación…</p>
    {:else if filas.length === 0}
      <p class="{tarjeta} m-0 px-5 py-4 text-inst-ink-2">Todavía no hay actividades publicadas en su componente.</p>
    {:else}
      <div class="{tarjeta} overflow-x-auto">
        <table class="w-full border-collapse text-left">
          <thead>
            <tr class="bg-inst-cabecera text-xs font-extrabold tracking-[0.04em] text-inst-ink-2 uppercase">
              <th scope="col" class="px-4.5 py-3">Actividad</th>
              <th scope="col" class="px-3 py-3">Tipo</th>
              <th scope="col" class="px-3 py-3">Nota o calificación</th>
              <th scope="col" class="px-4.5 py-3"><span class="sr-only">Agenda</span></th>
            </tr>
          </thead>
          <tbody>
            {#each filas as f (f.id_actividad)}
              <tr class="border-t border-inst-linea">
                <td class="px-4.5 py-3 font-bold">{f.nombre}</td>
                <td class="px-3 py-3">
                  <span class="{chip} {f.es_sumativa ? tono.accion : tono.info}">{f.es_sumativa ? 'Sumativa' : 'Formativa'}</span>
                </td>
                <td class="px-3 py-3 whitespace-nowrap">
                  {#if f.es_sumativa && f.nota !== null}
                    <span class="font-inst-titulo text-lg leading-[22px] font-bold">{formatNota(f.nota)}</span>
                  {:else if !f.es_sumativa && f.calificacion}
                    <span class="{chip} {TONO_CALIFICACION[f.calificacion] ?? tono.off}">{f.calificacion}</span>
                  {:else}
                    <span class="{chip} {tono[TONO_ENTREGA[f.estado]]}">{textosEntrega(f.es_sumativa)[f.estado]}</span>
                  {/if}
                </td>
                <td class="px-4.5 py-3 text-right">
                  <button type="button" class="{btnSecundario} {chico}" onclick={() => (agendaDe = f)}>
                    <MessageSquare size={16} aria-hidden="true" />Agenda
                  </button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}

    {#snippet pie()}
      {#if e.id_usuario}
        <Link
          href="/docente/cursos/{idCurso}/mensajeria?componente_id={estudiante.id_componente}&alumno_id={e.id_usuario}"
          class={btnSecundario}
        >
          <MessageSquare size={16} aria-hidden="true" />Enviar mensaje
        </Link>
      {/if}
      <button type="button" class="{btnSecundario} ml-auto" onclick={onCerrar}>Cerrar</button>
    {/snippet}
  </ModalInst>
{/if}

{#if agendaDe}
  <AgendaGrupoModal
    {idCurso}
    idActividad={agendaDe.id_actividad}
    idGrupo={agendaDe.grupo}
    nombre={e.nombre}
    pieCerrar=""
    onCerrar={() => {
      agendaDe = null;
      cargar();
    }}
  />
{/if}
