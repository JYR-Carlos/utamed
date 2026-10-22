<script lang="ts">
  /**
   * Página de una actividad para el alumno (guía dm): cabecera noche y, según
   * el momento, por entregar (entrega, agenda, enunciado; al lado la nota que
   * falta, el grupo y la rúbrica) o calificada (nota y retroalimentación,
   * rúbrica evaluada, la entrega y la agenda; al lado los detalles y lo que
   * sigue en el curso). En el móvil todo va en una columna, en el orden de
   * lectura de cada momento.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import type { Rubrica, RubricaResponse } from '@/types/rubrica';
  import type { InteraccionItem, RubricaDetalleEvent } from '@/types/agenda';
  import { ChevronRight, CircleAlert, Download, FileText, X } from 'lucide-svelte';
  import { ConfirmDialog } from '@/components/custom/common';
  import Agenda from './Agenda/Agenda.svelte';
  import RubricaAlumno from './Agenda/RubricaAlumno.svelte';
  import Entrega from './Agenda/Entrega.svelte';
  import Enunciado from './Agenda/Enunciado.svelte';
  import ActividadEncabezado from './components/ActividadEncabezado.svelte';
  import ActividadEntrega from './components/ActividadEntrega.svelte';
  import ActividadNota from './components/ActividadNota.svelte';
  import ActividadRubricaEvaluada from './components/ActividadRubricaEvaluada.svelte';
  import ActividadAgendaResumen from './components/ActividadAgendaResumen.svelte';
  import ModalAlumno from '@/components/student/dm/ModalAlumno.svelte';
  import { Link, router, page } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import { etiqueta, pildora, tarjeta, tono } from '@/lib/dm';
  import { estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import { fechaLimiteTexto, porEntregar, type ActividadAlumno } from '@/lib/actividadAlumno';
  import { criteriosDe, puntajeTotal } from '@/lib/rubricaAlumno';
  import { claveDia, formatBytes, formatDiaCorto, initials } from '@/utils/formatters';
  import { titleCase } from '@/lib/utils';

  interface Props {
    id_curso: number;
    cod_curso: string;
    cod_asignatura?: string | null;
    nombre_curso: string;
    componente_tipo?: string | null;
    actividades_curso?: ActividadAlumno[];
    cod_actividad: string;
    nombre_actividad: string;
    fecha_limite: string;
    es_sumativa: boolean;
    /** % del puntaje que da el 4,0. */
    exigencia?: number;
    es_grupal: boolean;
    entrega_obligatoria: boolean;
    ultima_nota?: number | null;
    /** Décimas que el docente sumó o restó a la nota del grupo para este alumno. */
    ajuste_decimas?: number;
    nota_grupo?: number | null;
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
    estado?: string | null;
    listado_interacciones?: InteraccionItem[];
    rubrica?: RubricaResponse | null;
    ultima_evaluacion?: {
      id_evaluacion: number;
      fecha_emision?: string | null;
      emisor?: string | null;
      puntaje_obtenido: number | null;
      resultado: Record<string, string> | null;
      evaluacion_obtenida?: string | null;
      retroalimentacion?: string | null;
      rubrica?: Rubrica | null;
    } | null;
    id_actividad_asignada_grupo?: number | null;
    resto_integrantes: Array<{
      id_estudiante: number;
      nombre1: string;
      nombre2: string;
      apellido1: string;
      apellido2: string;
    }>;
    archivo_enunciado?: {
      nombre_original: string;
      mime_type: string | null;
      peso_bytes: number | null;
    } | null;
    equipo_docente?: Array<{ nombre: string; es_titular: boolean }>;
  }

  let {
    id_curso,
    cod_curso,
    cod_asignatura = null,
    nombre_curso,
    componente_tipo = null,
    actividades_curso = [],
    cod_actividad,
    nombre_actividad,
    fecha_limite,
    es_sumativa,
    exigencia = 60,
    es_grupal,
    entrega_obligatoria,
    ultima_nota,
    ajuste_decimas = 0,
    nota_grupo = null,
    ultima_evaluacion = null,
    ultima_entrega = null,
    estado,
    listado_interacciones = [],
    rubrica,
    id_actividad_asignada_grupo,
    resto_integrantes,
    archivo_enunciado = null,
    equipo_docente = [],
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis cursos', href: '/estudiante/cursos' },
    { title: nombre_curso, href: `/estudiante/cursos/${id_curso}` },
    { title: nombre_actividad, href: '' },
  ]);

  // El mismo color que el curso tiene en el menú y en «Mis cursos».
  const colorCurso = $derived(
    estiloCurso(numeroColorCurso(id_curso, ($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? [])),
  );

  let showRubricaModal = $state(false);
  let showAgendaModal = $state(false);
  let showEntregaModal = $state(false);
  let showEnunciadoModal = $state(false);
  let mensajesOptimistas = $state<InteraccionItem[]>([]);
  // Abrió la agenda en esta visita: lo «Nuevo» ya lo vio.
  let agendaVista = $state(false);

  // Avisos en línea y confirmación propia: antes eran alert() y confirm() del
  // navegador, que bloquean la página y no siguen el diseño del producto.
  let avisoError = $state<string | null>(null);
  let temporizadorAviso: ReturnType<typeof setTimeout> | undefined;
  function mostrarError(mensaje: string) {
    avisoError = mensaje;
    clearTimeout(temporizadorAviso);
    temporizadorAviso = setTimeout(() => (avisoError = null), 8000);
  }
  let confirmandoBorrado = $state(false);

  // En una actividad grupal el docente aún puede no haber asignado al alumno a
  // un grupo: sin grupo no hay dónde colgar entregas ni mensajes.
  const sinGrupo = $derived(!id_actividad_asignada_grupo);

  const interaccionesCombinadas = $derived.by(() => {
    if (mensajesOptimistas.length === 0) return listado_interacciones ?? [];
    const delServidor = listado_interacciones ?? [];
    const pendientes = mensajesOptimistas.filter((opt) => {
      const optTime = new Date(opt.fecha_emision).getTime();
      return !delServidor.some((s) => {
        if (!s.es_propio || s.mensaje !== opt.mensaje) return false;
        const sTime = new Date(s.fecha_emision).getTime();
        return Math.abs(sTime - optTime) < 60000;
      });
    });
    return [...delServidor, ...pendientes];
  });

  // «Visto» (T07): leer la agenda es abrirla, no cargar la actividad. Se avisa al
  // servidor cada vez que el modal pasa a abierto; el docente lo ve en su hilo.
  $effect(() => {
    if (!showAgendaModal || !id_actividad_asignada_grupo) return;
    agendaVista = true;
    fetch(`/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/agenda/leida`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        'X-Requested-With': 'XMLHttpRequest',
      },
    }).catch(() => {
      // Sin red no pasa nada grave: se reintenta la próxima vez que abra la agenda.
    });
  });

  // Desde el dashboard («Notas y retroalimentaciones recientes») se llega con
  // ?abrir=agenda (o #agenda) para aterrizar directo en la conversación.
  onMount(() => {
    const abrir = new URLSearchParams(window.location.search).get('abrir');
    if (abrir === 'agenda' || window.location.hash === '#agenda') {
      showAgendaModal = true;
    }
  });

  // El backend ya calcula 'estado' (ACTIVA/CERRADA) considerando la holgura
  // de la actividad Y la holgura personal del grupo — ver
  // ActividadAsignadaGrupo::calcularEstadoGrupo.
  const tieneEntregaRegistrada = $derived(ultima_entrega !== null);

  // Última interacción de tipo evaluación, por si la evaluación llegó sólo
  // por la agenda.
  const ultimaEvaluacion = $derived.by(() => {
    const interacciones = listado_interacciones ?? [];
    for (let i = interacciones.length - 1; i >= 0; i--) {
      if (interacciones[i].tipo_interaccion === 'Evaluación') return interacciones[i];
    }
    return null;
  });

  const yaEvaluada = $derived(
    (ultima_nota !== null && ultima_nota !== undefined) || Boolean(ultima_evaluacion) || Boolean(ultimaEvaluacion),
  );

  const puedeSubirArchivo = $derived(estado === 'ACTIVA' && entrega_obligatoria && !yaEvaluada);

  // El momento de la página: con nota (sumativa) o evaluación (formativa) la
  // nota pasa arriba; si no, manda la entrega.
  const calificada = $derived(es_sumativa ? ultima_nota != null : ultima_evaluacion !== null);

  // La rúbrica que se usó al evaluar; si no hay evaluación, la vigente.
  const rubricaVista = $derived(ultima_evaluacion?.rubrica ?? ultimaEvaluacion?.rubrica ?? rubrica?.rubrica ?? null);
  const resultado = $derived(ultima_evaluacion?.resultado ?? ultimaEvaluacion?.resultado ?? null);
  const criterios = $derived(criteriosDe(rubricaVista, resultado));
  // Como antes: la rúbrica es cosa de las sumativas.
  const conRubrica = $derived(es_sumativa && criterios.length > 0);
  const rubricaEvaluada = $derived(
    conRubrica && calificada && criterios.some((c) => c.obtenido !== null),
  );
  const rubricaDeLaEvaluacion = $derived(ultima_evaluacion?.rubrica ?? ultimaEvaluacion?.rubrica ?? null);

  const enunciadoEsPdf = $derived(archivo_enunciado?.mime_type === 'application/pdf');
  const urlEnunciado = $derived(`/estudiante/cursos/${id_curso}/actividades/${cod_actividad}/enunciado/descargar`);
  const detalleEnunciado = $derived.by(() => {
    if (!archivo_enunciado) return '';
    const ext = archivo_enunciado.nombre_original.split('.').pop()?.toUpperCase() ?? '';
    return [ext ? `Archivo ${ext}` : '', archivo_enunciado.peso_bytes ? formatBytes(archivo_enunciado.peso_bytes) : '']
      .filter(Boolean)
      .join(' · ');
  });

  const modalidad = $derived(
    es_grupal
      ? sinGrupo
        ? 'Grupal'
        : `Grupal · ${resto_integrantes.length + 1} integrantes`
      : 'Individual',
  );
  const chips = $derived(
    [es_sumativa ? 'Sumativa' : 'Formativa', modalidad, componente_tipo, entrega_obligatoria ? null : 'Sin entrega'].filter(
      (c): c is string => !!c,
    ),
  );

  // El grupo completo, con el alumno primero.
  const integrantes = $derived.by(() => {
    const yo = $page.props.auth?.user;
    const corto = (nombre1?: string | null, apellido1?: string | null) => titleCase([nombre1, apellido1].filter(Boolean).join(' '));
    const propio = yo ? [{ id: 'yo', nombre: corto(yo.nombre1, yo.apellido1), tu: true }] : [];
    return [
      ...propio,
      ...resto_integrantes.map((u) => ({ id: String(u.id_estudiante), nombre: corto(u.nombre1, u.apellido1), tu: false })),
    ];
  });

  const hoy = claveDia();
  const fechaLimiteDetalle = $derived(fecha_limite ? fechaLimiteTexto(fecha_limite, hoy) : 'Sin fecha límite');

  // «Lo que sigue en el curso»: la próxima por entregar, si esta ya no lo está.
  const estaPorEntregar = $derived(puedeSubirArchivo && !ultima_entrega);
  const siguiente = $derived(
    estaPorEntregar
      ? null
      : (actividades_curso.find((a) => porEntregar(a) && String(a.id_actividad) !== String(cod_actividad)) ?? null),
  );

  function plazoDe(fecha: string | null | undefined): { texto: string; tono: 'warn' | 'off' } {
    const dia = fecha?.slice(0, 10) ?? '';
    if (!dia) return { texto: 'Sin fecha límite', tono: 'off' };
    if (dia < hoy) return { texto: 'Atrasada · aún se recibe', tono: 'warn' };
    const t = fechaLimiteTexto(dia, hoy);
    const minuscula = `${t.charAt(0).toLowerCase()}${t.slice(1)}`;
    return {
      texto: /^(Hoy|Mañana)/.test(t) ? `Vence ${minuscula}` : `Vence el ${minuscula}`,
      tono: dia === hoy ? 'warn' : 'off',
    };
  }

  // Orden de lectura en el móvil, donde las dos columnas se vuelven una.
  const orden = $derived(
    calificada
      ? { nota: 'max-lg:order-1', evaluada: 'max-lg:order-2', entrega: 'max-lg:order-3', agenda: 'max-lg:order-4', detalles: 'max-lg:order-5', integrantes: 'max-lg:order-6', rubrica: 'max-lg:order-7', siguiente: 'max-lg:order-8', enunciado: 'max-lg:order-9' }
      : { entrega: 'max-lg:order-1', agenda: 'max-lg:order-2', nota: 'max-lg:order-3', integrantes: 'max-lg:order-4', enunciado: 'max-lg:order-5', rubrica: 'max-lg:order-6', detalles: 'max-lg:order-7', siguiente: 'max-lg:order-8', evaluada: 'max-lg:order-9' },
  );

  // Las ventanas se abren desde la página. La rúbrica, además, desde una
  // evaluación de la agenda: entonces se abre encima, con esa evaluación.
  let rubricaDeAgenda = $state<RubricaDetalleEvent | null>(null);
  function abrirRubrica(detalle: RubricaDetalleEvent | null = null) {
    rubricaDeAgenda = detalle;
    showRubricaModal = true;
  }
  const abrirAgenda = () => (showAgendaModal = true);
  const abrirEntrega = () => (showEntregaModal = true);
  const abrirEnunciado = () => (showEnunciadoModal = true);

  const rubricaPanel = $derived<RubricaDetalleEvent>(
    rubricaDeAgenda ?? {
      rubrica: rubricaVista,
      resultado,
      puntaje_obtenido: ultima_evaluacion?.puntaje_obtenido ?? ultimaEvaluacion?.puntaje_obtenido ?? null,
      retroalimentacion: ultima_evaluacion?.retroalimentacion ?? ultimaEvaluacion?.mensaje ?? undefined,
      evaluador: ultima_evaluacion?.emisor ?? ultimaEvaluacion?.emisor ?? undefined,
      fecha: ultima_evaluacion?.fecha_emision ?? ultimaEvaluacion?.fecha_emision ?? undefined,
    },
  );
  const rubricaPanelEvaluada = $derived(Object.keys(rubricaPanel.resultado ?? {}).length > 0);
  const subtituloRubrica = $derived.by(() => {
    if (!rubricaPanelEvaluada) return 'Así se evaluará tu entrega';
    const quien = rubricaPanel.evaluador ? `Evaluada por ${titleCase(rubricaPanel.evaluador)}` : 'Evaluada';
    const fecha = rubricaPanel.fecha ? new Date(rubricaPanel.fecha) : null;
    return fecha && !isNaN(fecha.getTime()) ? `${quien} · ${formatDiaCorto(fecha)}` : quien;
  });

  const subtituloAgenda = $derived(
    `${[nombre_curso, componente_tipo].filter(Boolean).join(' · ')} · ${es_grupal ? 'tu grupo' : 'tú'} y tu equipo docente`,
  );
  const subtituloEntrega = $derived.by(() => {
    const plazo = plazoDe(fecha_limite).texto;
    return `${es_grupal ? 'Entrega del grupo' : 'Tu entrega'} · ${plazo.charAt(0).toLowerCase()}${plazo.slice(1)}`;
  });
  // «Benjamín y Josefa la ven en la agenda»: el resto del grupo, por su nombre de pila.
  const companeros = $derived(resto_integrantes.map((u) => titleCase(u.nombre1 ?? '')).filter(Boolean));

  let entregaSubiendo = $state(false);

  function handleGuardarEntrada(data: { tipo: string; mensaje: string }) {
    if (!id_actividad_asignada_grupo) {
      console.error('[handleGuardarEntrada] id_actividad_asignada_grupo es null/undefined.', {
        id_actividad_asignada_grupo,
        cod_actividad,
        cod_curso,
        data_tipo: data.tipo,
      });
      mostrarError('Todavía no formas parte de un grupo en esta actividad, así que no puedes enviar mensajes.');
      return;
    }

    const authUser = $page.props.auth?.user;
    const nombreEmisor = authUser
      ? [authUser.nombre1, authUser.apellido1, authUser.apellido2].filter(Boolean).join(' ')
      : 'Estudiante';
    const optimisticItem: InteraccionItem = {
      id_interaccion: -Date.now(),
      fecha_emision: new Date().toISOString(),
      tipo_interaccion: 'Mensaje al profesor',
      emisor: nombreEmisor,
      mensaje: data.mensaje,
      es_de_docente: false,
      es_propio: true,
    };
    mensajesOptimistas = [...mensajesOptimistas, optimisticItem];

    router.post(
      `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/agenda`,
      { tipo: data.tipo, mensaje: data.mensaje },
      {
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
        onSuccess: () => {
          mensajesOptimistas = mensajesOptimistas.filter(
            (i) => i.id_interaccion !== optimisticItem.id_interaccion,
          );
        },
        onError: (errors) => {
          mensajesOptimistas = mensajesOptimistas.filter(
            (i) => i.id_interaccion !== optimisticItem.id_interaccion,
          );
          mostrarError(errors.error || 'No se pudo enviar el mensaje. Inténtalo nuevamente.');
        },
      },
    );
  }

  function handleBorrarEntrega() {
    if (!ultima_entrega?.id_interaccion || !id_actividad_asignada_grupo) return;
    confirmandoBorrado = true;
  }

  function borrarEntregaConfirmado() {
    confirmandoBorrado = false;
    if (!ultima_entrega?.id_interaccion || !id_actividad_asignada_grupo) return;

    router.delete(
      `/estudiante/grupos-asignados/${id_actividad_asignada_grupo}/entregas/${ultima_entrega.id_interaccion}`,
      {
        preserveScroll: true,
        onSuccess: () => router.reload(),
        onError: (errors) => mostrarError(errors.error_general || 'No se pudo eliminar la entrega.'),
      },
    );
  }
</script>

<svelte:head>
  <title>{nombre_actividad} | UTAmed</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div style={colorCurso} class="mx-auto flex w-full max-w-280 flex-col gap-5 pb-10 lg:gap-6 lg:px-10 lg:pt-8 lg:pb-12">
    <ActividadEncabezado
      idCurso={id_curso}
      codigo={cod_asignatura ?? ''}
      nombreCurso={nombre_curso}
      nombre={nombre_actividad}
      {chips}
      onVerEnunciado={archivo_enunciado ? abrirEnunciado : undefined}
      onVerRubrica={conRubrica && !rubricaEvaluada ? () => abrirRubrica() : undefined}
    />

    <div class="flex flex-col gap-4 px-4 lg:px-0">
      {#if avisoError}
        <div role="alert" class="flex items-start gap-2.5 rounded-xl border border-dm-error bg-dm-error-s py-1 pr-1 pl-4 text-dm-error">
          <CircleAlert size={18} aria-hidden="true" class="mt-2.5 shrink-0" />
          <p class="flex-1 py-2.5 text-sm leading-5 text-dm-ink">{avisoError}</p>
          <button
            type="button"
            class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg hover:bg-dm-tarjeta"
            aria-label="Cerrar aviso"
            onclick={() => (avisoError = null)}
          >
            <X size={18} aria-hidden="true" />
          </button>
        </div>
      {/if}

      <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start lg:gap-6">
        <div class="flex min-w-0 flex-col gap-6 max-lg:contents">
          {#if calificada}
            <div class={orden.nota}>
              <ActividadNota
                esSumativa={es_sumativa}
                esGrupal={es_grupal}
                nota={es_sumativa ? (ultima_nota ?? null) : null}
                evaluada
                notaGrupo={nota_grupo}
                ajusteDecimas={ajuste_decimas}
                fecha={ultima_evaluacion?.fecha_emision ?? ultimaEvaluacion?.fecha_emision ?? null}
                evaluador={ultima_evaluacion?.emisor ?? ultimaEvaluacion?.emisor ?? null}
                puntaje={ultima_evaluacion?.puntaje_obtenido ?? ultimaEvaluacion?.puntaje_obtenido ?? null}
                puntajeTotal={puntajeTotal(rubricaDeLaEvaluacion)}
                resultado={es_sumativa ? null : ultima_evaluacion?.evaluacion_obtenida ? String(ultima_evaluacion.evaluacion_obtenida) : null}
                retroalimentacion={ultima_evaluacion?.retroalimentacion ?? ultimaEvaluacion?.mensaje ?? null}
              />
            </div>
          {/if}

          {#if rubricaEvaluada}
            <div class={orden.evaluada}>
              <ActividadRubricaEvaluada {criterios} onVerCompleta={() => abrirRubrica()} />
            </div>
          {/if}

          {#if entrega_obligatoria || sinGrupo}
            <div class={orden.entrega}>
              <ActividadEntrega
                fechaLimite={fecha_limite}
                {estado}
                esGrupal={es_grupal}
                {sinGrupo}
                {yaEvaluada}
                puedeSubir={puedeSubirArchivo}
                entrega={ultima_entrega}
                urlDescarga={ultima_entrega
                  ? `/estudiante/cursos/${id_curso}/actividades/${cod_actividad}/entregas/${ultima_entrega.id_interaccion}/descargar`
                  : null}
                onSubir={abrirEntrega}
                onBorrar={handleBorrarEntrega}
              />
            </div>
          {/if}

          {#if id_actividad_asignada_grupo}
            <div class={orden.agenda}>
              <ActividadAgendaResumen
                interacciones={interaccionesCombinadas}
                esGrupal={es_grupal}
                {calificada}
                vista={agendaVista}
                onAbrir={abrirAgenda}
              />
            </div>
          {/if}

          {#if !calificada}
            <section aria-labelledby="t-enunciado" class="{tarjeta} flex flex-col gap-3 p-4 lg:px-6 lg:py-5 {orden.enunciado}">
              <h2 id="t-enunciado" class="text-lg leading-6 font-semibold">Qué hay que entregar</h2>
              {#if archivo_enunciado}
                <a
                  href={urlEnunciado}
                  class="flex min-h-14 items-center gap-3 rounded-[10px] border border-dm-linea px-3 py-2 text-dm-ink no-underline hover:bg-dm-sutil hover:text-dm-ink lg:min-h-13"
                >
                  <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-dm-sutil text-dm-ink-2">
                    <FileText size={18} aria-hidden="true" />
                  </span>
                  <span class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-sm font-semibold">{archivo_enunciado.nombre_original}</span>
                    {#if detalleEnunciado}<span class="text-[13px] leading-[18px] text-dm-ink-3">{detalleEnunciado}</span>{/if}
                  </span>
                  <span class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-dm-enlace">
                    <Download size={16} aria-hidden="true" /><span class="max-sm:sr-only">Descargar</span>
                  </span>
                </a>
              {:else}
                <p class="text-sm leading-5 text-dm-ink-2">Tu docente todavía no adjunta el enunciado de esta actividad.</p>
              {/if}
            </section>
          {/if}
        </div>

        <aside class="flex min-w-0 flex-col gap-4 max-lg:contents" aria-label="Sobre esta actividad">
          {#if !calificada}
            <div class={orden.nota}>
              <ActividadNota esSumativa={es_sumativa} esGrupal={es_grupal} nota={null} evaluada={false} />
            </div>
          {/if}

          {#if calificada || !entrega_obligatoria}
            <section aria-labelledby="t-detalles" class="{tarjeta} overflow-hidden {orden.detalles}">
              <h2 id="t-detalles" class="border-b border-dm-linea px-4 py-3.5 text-lg leading-6 font-semibold lg:px-5">Detalles</h2>
              <dl class="px-4 py-1 lg:px-5">
                {#each [['Fecha límite', fechaLimiteDetalle], ['Tipo', es_sumativa ? 'Sumativa' : 'Formativa'], ['Modalidad', es_grupal ? 'Grupal' : 'Individual'], ['Componente', componente_tipo]] as [rotulo, valor] (rotulo)}
                  {#if valor}
                    <div class="flex justify-between gap-3 border-b border-dm-linea py-3 last:border-b-0">
                      <dt class="text-sm text-dm-ink-2">{rotulo}</dt>
                      <dd class="text-right text-sm font-semibold">{valor}</dd>
                    </div>
                  {/if}
                {/each}
              </dl>
            </section>
          {/if}

          {#if es_grupal && !sinGrupo}
            <section aria-labelledby="t-grupo" class="{tarjeta} overflow-hidden {orden.integrantes}">
              <h2 id="t-grupo" class="border-b border-dm-linea px-4 py-3.5 text-lg leading-6 font-semibold lg:px-5">Integrantes</h2>
              <ul class="px-4 lg:px-5 lg:py-1">
                {#each integrantes as g (g.id)}
                  <li class="flex min-h-13 items-center gap-3 border-b border-dm-linea last:border-b-0 lg:min-h-12">
                    <span
                      aria-hidden="true"
                      class="flex size-8 shrink-0 items-center justify-center rounded-full bg-dm-sutil text-[13px] font-bold text-dm-ink-2"
                      >{initials(g.nombre)}</span
                    >
                    <span class="text-[15px] font-medium lg:text-sm">{g.nombre}</span>
                    {#if g.tu}<span class="text-[13px] leading-[18px] text-dm-ink-3">(tú)</span>{/if}
                  </li>
                {/each}
              </ul>
            </section>
          {/if}

          {#if conRubrica && !rubricaEvaluada}
            <section aria-labelledby="t-rubrica" class="{tarjeta} overflow-hidden {orden.rubrica}">
              <div class="flex items-center justify-between gap-2 border-b border-dm-linea px-4 py-3.5 lg:px-5">
                <h2 id="t-rubrica" class="text-lg leading-6 font-semibold">Rúbrica</h2>
                <span class="text-[13px] leading-[18px] text-dm-ink-3">
                  {criterios.length} {criterios.length === 1 ? 'criterio' : 'criterios'}
                </span>
              </div>
              <ul class="px-4 lg:px-5 lg:py-1">
                {#each criterios as c (c.id)}
                  <li class="flex min-h-11 items-center justify-between gap-3 border-b border-dm-linea py-2 text-sm">
                    <span>{c.nombre}</span><span class="shrink-0 font-bold tabular-nums">{c.peso}</span>
                  </li>
                {/each}
              </ul>
              <button
                type="button"
                class="flex min-h-12 w-full cursor-pointer items-center gap-1 px-4 text-[15px] font-semibold text-dm-enlace hover:text-dm-enlace-hover hover:underline lg:min-h-11 lg:px-5 lg:text-sm"
                onclick={() => abrirRubrica()}
              >
                Ver rúbrica completa<ChevronRight size={16} aria-hidden="true" />
              </button>
            </section>
          {/if}

          {#if siguiente}
            {@const plazo = plazoDe(siguiente.fecha_limite)}
            <section aria-labelledby="t-siguiente" class="{tarjeta} flex flex-col gap-2.5 p-4 lg:p-5 {orden.siguiente}">
              <h2 id="t-siguiente" class="{etiqueta} text-dm-ink-3">Lo que sigue en el curso</h2>
              <Link
                href={`/estudiante/cursos/${id_curso}/actividad/${siguiente.id_actividad}`}
                class="flex flex-col gap-1.5 text-dm-ink no-underline hover:text-dm-ink"
              >
                <span class="text-base leading-[22px] font-semibold hover:underline">{siguiente.nombre}</span>
                <span class="{pildora} {tono[plazo.tono]} self-start">{plazo.texto}</span>
              </Link>
              <Link
                href={`/estudiante/cursos/${id_curso}`}
                class="inline-flex min-h-11 items-center gap-1 self-start text-sm font-semibold text-dm-enlace no-underline hover:text-dm-enlace-hover hover:underline lg:min-h-10"
              >
                Ver todas las actividades<ChevronRight size={16} aria-hidden="true" />
              </Link>
            </section>
          {/if}
        </aside>
      </div>
    </div>
  </div>
</StudentLayout>

<ModalAlumno
  bind:open={showAgendaModal}
  etiqueta="Agenda de la actividad"
  titulo={nombre_actividad}
  subtitulo={subtituloAgenda}
  estilo={colorCurso}
  rotuloCerrar="Cerrar agenda"
>
  <Agenda
    idCurso={id_curso}
    codActividad={cod_actividad}
    listado_interacciones={interaccionesCombinadas}
    esGrupal={es_grupal}
    entregaPendiente={estaPorEntregar}
    onEnviar={(mensaje) => handleGuardarEntrada({ tipo: 'Consulta', mensaje })}
    onVerRubrica={abrirRubrica}
  />
</ModalAlumno>

<ModalAlumno
  bind:open={showRubricaModal}
  variante="lateral"
  etiqueta={rubricaPanelEvaluada ? 'Rúbrica evaluada' : 'Rúbrica de la actividad'}
  titulo={nombre_actividad}
  subtitulo={subtituloRubrica}
  estilo={colorCurso}
  rotuloCerrar="Cerrar rúbrica"
>
  <RubricaAlumno
    rubrica={rubricaPanel.rubrica}
    resultado={rubricaPanel.resultado}
    puntajeObtenido={rubricaPanel.puntaje_obtenido}
    retroalimentacion={rubricaPanel.retroalimentacion}
    evaluador={rubricaPanel.evaluador ? titleCase(rubricaPanel.evaluador) : null}
    {exigencia}
  />
</ModalAlumno>

{#if id_actividad_asignada_grupo}
  <ModalAlumno
    bind:open={showEntregaModal}
    variante="centro"
    etiqueta={tieneEntregaRegistrada ? 'Reemplazar la entrega' : 'Entregar la actividad'}
    titulo={nombre_actividad}
    subtitulo={subtituloEntrega}
    estilo={colorCurso}
    rotuloCerrar="Cerrar entrega"
    bloqueada={entregaSubiendo}
  >
    <Entrega
      {id_actividad_asignada_grupo}
      {entrega_obligatoria}
      esReemplazo={tieneEntregaRegistrada}
      esGrupal={es_grupal}
      {companeros}
      bind:subiendo={entregaSubiendo}
      onCerrar={() => (showEntregaModal = false)}
      onEntregaCompletada={() => {
        showEntregaModal = false;
        router.reload();
      }}
      onAvisarDocente={() => {
        showEntregaModal = false;
        showAgendaModal = true;
      }}
    />
  </ModalAlumno>
{/if}

{#if archivo_enunciado}
  <ModalAlumno
    bind:open={showEnunciadoModal}
    etiqueta="Enunciado"
    titulo={nombre_actividad}
    subtitulo={nombre_curso}
    estilo={colorCurso}
    rotuloCerrar="Cerrar enunciado"
  >
    <Enunciado
      url_archivo={urlEnunciado}
      nombre_archivo={archivo_enunciado.nombre_original}
      detalle={detalleEnunciado}
      esPdf={enunciadoEsPdf}
    />
  </ModalAlumno>
{/if}

<ConfirmDialog
  open={confirmandoBorrado}
  title="Eliminar entrega"
  body="¿Seguro que quieres eliminar la entrega actual? Podrás subir otra mientras la actividad siga abierta."
  confirmLabel="Eliminar"
  onConfirm={borrarEntregaConfirmado}
  onCancel={() => (confirmandoBorrado = false)}
/>
