<script lang="ts">
  /**
   * Página de una actividad para el alumno (guía dm): una tarjeta con la
   * cabecera noche y, debajo, pestañas. En escritorio, Enunciado · Rúbrica ·
   * Agenda, con la entrega, la nota y el grupo fijos en la columna derecha;
   * en el móvil, Enunciado · Rúbrica · Entrega · Agenda. La pestaña viaja en
   * ?seccion= (como en la ficha del curso). Por entregar se abre en el
   * enunciado; calificada, en la rúbrica evaluada.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import type { Rubrica, RubricaResponse } from '@/types/rubrica';
  import type { InteraccionItem, RubricaDetalleEvent } from '@/types/agenda';
  import {
    Award,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    ClipboardList,
    Clock,
    Download,
    Eye,
    FileText,
    MessagesSquare,
    Upload,
    X,
  } from 'lucide-svelte';
  import { ConfirmDialog } from '@/components/custom/common';
  import Agenda from './Agenda/Agenda.svelte';
  import RubricaAlumno from './Agenda/RubricaAlumno.svelte';
  import Entrega from './Agenda/Entrega.svelte';
  import Enunciado from './Agenda/Enunciado.svelte';
  import ActividadEncabezado, { type PestanaActividad } from './components/ActividadEncabezado.svelte';
  import ActividadEntrega from './components/ActividadEntrega.svelte';
  import ActividadNota from './components/ActividadNota.svelte';
  import ActividadAgendaResumen from './components/ActividadAgendaResumen.svelte';
  import ModalAlumno from '@/components/student/dm/ModalAlumno.svelte';
  import { Link, router, page } from '@inertiajs/svelte';
  import { onMount, tick } from 'svelte';
  import { MediaQuery } from 'svelte/reactivity';
  import { btnSecundario, btnSuave, etiqueta, pildora, tarjeta, tono } from '@/lib/dm';
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

  // ─── Pestañas ───────────────────────────────────────────────────────────────
  type Seccion = 'enunciado' | 'rubrica' | 'entrega' | 'agenda';

  // En escritorio la entrega va en la columna derecha y no tiene pestaña.
  const escritorio = new MediaQuery('min-width: 64rem');

  // La rúbrica que se mira: la de una evaluación elegida en la agenda o, si
  // no, la de la última evaluación (o la vigente).
  let rubricaDeAgenda = $state<RubricaDetalleEvent | null>(null);
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
  // «Paula Fuentes · lun 19 oct», junto al título de la rúbrica evaluada.
  const subtituloRubrica = $derived.by(() => {
    const fecha = rubricaPanel.fecha ? new Date(rubricaPanel.fecha) : null;
    return [
      rubricaPanel.evaluador ? titleCase(rubricaPanel.evaluador) : '',
      fecha && !isNaN(fecha.getTime()) ? formatDiaCorto(fecha) : '',
    ]
      .filter(Boolean)
      .join(' · ');
  });

  const totalRubrica = $derived(puntajeTotal(rubricaVista));
  const puntajeEvaluacion = $derived(ultima_evaluacion?.puntaje_obtenido ?? ultimaEvaluacion?.puntaje_obtenido ?? null);
  const mensajesAgenda = $derived(
    interaccionesCombinadas.filter((i) => i.mensaje?.trim() && i.tipo_interaccion !== 'Evaluación').length,
  );
  const nuevosAgenda = $derived(agendaVista ? 0 : interaccionesCombinadas.filter((i) => i.nuevo).length);

  const pestanas = $derived.by(() => {
    const lista: Array<PestanaActividad & { id: Seccion }> = [
      { id: 'enunciado', etiqueta: 'Enunciado', detalle: detalleEnunciado || 'Sin archivo todavía', icono: FileText },
    ];
    if (conRubrica || rubricaDeAgenda) {
      const n = criterios.length;
      lista.push({
        id: 'rubrica',
        etiqueta: 'Rúbrica',
        detalle:
          rubricaEvaluada && puntajeEvaluacion != null
            ? `${String(puntajeEvaluacion).replace('.', ',')} de ${totalRubrica} pts`
            : `${n} ${n === 1 ? 'criterio' : 'criterios'} · ${totalRubrica} pts`,
        icono: ClipboardList,
      });
    }
    if (!escritorio.current) lista.push({ id: 'entrega', etiqueta: 'Entrega', icono: Upload });
    if (id_actividad_asignada_grupo) {
      lista.push({
        id: 'agenda',
        etiqueta: 'Agenda',
        detalle: mensajesAgenda === 0 ? 'Sin mensajes' : `${mensajesAgenda} ${mensajesAgenda === 1 ? 'mensaje' : 'mensajes'}`,
        nuevos: nuevosAgenda,
        icono: MessagesSquare,
      });
    }
    return lista;
  });

  const pedida = $derived.by(() => {
    const url = new URL($page.url, 'http://x');
    // ?abrir=agenda: enlace del dashboard a la conversación.
    return url.searchParams.get('seccion') ?? (url.searchParams.get('abrir') === 'agenda' ? 'agenda' : null);
  });
  // Calificada abre en la rúbrica evaluada; sin ella, donde está la nota.
  const porDefecto = $derived<Seccion>(
    calificada ? (rubricaEvaluada ? 'rubrica' : escritorio.current ? 'enunciado' : 'entrega') : 'enunciado',
  );
  const seccion = $derived<Seccion>(pestanas.find((p) => p.id === pedida)?.id ?? porDefecto);

  function cambiarSeccion(nueva: string) {
    if (nueva === seccion) return;
    const url = new URL($page.url, window.location.origin);
    url.searchParams.set('seccion', nueva);
    url.searchParams.delete('abrir');
    // Visita sólo de cliente: cambia la URL sin pedir nada al servidor.
    router.push({ url: `${url.pathname}${url.search}`, preserveState: true, preserveScroll: true });
  }

  // Elegir la pestaña Rúbrica muestra la última evaluación, no la de la agenda.
  function alElegirPestana(id: string) {
    if (id === 'rubrica') rubricaDeAgenda = null;
    cambiarSeccion(id);
  }

  // Desde un botón de más abajo: el foco (y la vista) sube a la pestaña nueva.
  async function irA(id: Seccion) {
    cambiarSeccion(id);
    await tick();
    document.getElementById(`tab-${id}`)?.focus();
  }

  // «Ver rúbrica» en una evaluación de la agenda: esa evaluación, en su pestaña.
  function verRubricaDesdeAgenda(detalle: RubricaDetalleEvent) {
    rubricaDeAgenda = detalle;
    showAgendaModal = false;
    irA('rubrica');
  }

  const abrirAgenda = () => (showAgendaModal = true);
  const abrirEntrega = () => (showEntregaModal = true);
  const abrirEnunciado = () => (showEnunciadoModal = true);

  // En amarillo en la cabecera: el plazo mientras falta entregar, la nota después.
  const destacado = $derived.by(() => {
    if (calificada) {
      return es_sumativa && ultima_nota != null
        ? { texto: `Nota ${ultima_nota.toFixed(1).replace('.', ',')}`, icono: Award }
        : { texto: 'Revisada', icono: CircleCheck };
    }
    return estaPorEntregar && fecha_limite ? { texto: plazoDe(fecha_limite).texto, icono: Clock } : null;
  });

  const retroalimentacion = $derived(ultima_evaluacion?.retroalimentacion ?? ultimaEvaluacion?.mensaje ?? null);

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

<!-- Un aviso de «siguiente paso» al pie de una pestaña. -->
{#snippet paso(rotulo: string, texto: string, boton: string, destino: Seccion)}
  <div class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border border-dm-linea bg-dm-sutil p-4">
    <span class="flex min-w-0 flex-[1_1_220px] flex-col gap-0.5">
      <span class="{etiqueta} text-dm-ink-3">{rotulo}</span>
      <span class="text-[15px] leading-[22px] font-semibold">{texto}</span>
    </span>
    <button type="button" class="{btnSuave} max-sm:w-full" onclick={() => irA(destino)}>
      {boton}<ChevronRight size={18} aria-hidden="true" />
    </button>
  </div>
{/snippet}

<!-- Columna derecha en escritorio; pestaña Entrega en el móvil. -->
{#snippet lateral()}
  {#if calificada}
    <ActividadNota
      esSumativa={es_sumativa}
      esGrupal={es_grupal}
      nota={es_sumativa ? (ultima_nota ?? null) : null}
      evaluada
      notaGrupo={nota_grupo}
      ajusteDecimas={ajuste_decimas}
      fecha={ultima_evaluacion?.fecha_emision ?? ultimaEvaluacion?.fecha_emision ?? null}
      evaluador={ultima_evaluacion?.emisor ?? ultimaEvaluacion?.emisor ?? null}
      puntaje={puntajeEvaluacion}
      puntajeTotal={puntajeTotal(rubricaDeLaEvaluacion)}
      resultado={es_sumativa ? null : ultima_evaluacion?.evaluacion_obtenida ? String(ultima_evaluacion.evaluacion_obtenida) : null}
      retroalimentacion={rubricaEvaluada ? null : retroalimentacion}
      onVerRubrica={rubricaEvaluada && seccion !== 'rubrica' ? () => irA('rubrica') : undefined}
    />
  {/if}

  {#if entrega_obligatoria || sinGrupo}
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
      hrefMensajeria={`/estudiante/cursos/${id_curso}?seccion=mensajeria`}
      onSubir={abrirEntrega}
      onBorrar={handleBorrarEntrega}
    />
  {/if}

  {#if !calificada}
    <ActividadNota esSumativa={es_sumativa} esGrupal={es_grupal} nota={null} evaluada={false} />
  {/if}

  {#if es_grupal && !sinGrupo}
    <section aria-labelledby="t-grupo" class="{tarjeta} overflow-hidden">
      <h2 id="t-grupo" class="border-b border-dm-linea px-4 py-3.5 text-base leading-[22px] font-bold lg:px-5">Integrantes</h2>
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

  {#if calificada || !entrega_obligatoria}
    <section aria-labelledby="t-detalles" class="{tarjeta} overflow-hidden">
      <h2 id="t-detalles" class="border-b border-dm-linea px-4 py-3.5 text-base leading-[22px] font-bold lg:px-5">Detalles</h2>
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

  {#if siguiente}
    {@const plazo = plazoDe(siguiente.fecha_limite)}
    <section aria-labelledby="t-siguiente" class="{tarjeta} flex flex-col gap-2.5 p-4 lg:p-5">
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
{/snippet}

<StudentLayout {breadcrumbs}>
  <!-- Más ancha que el resto en pantallas grandes: la agenda y la rúbrica comparten fila con la columna derecha. -->
  <div style={colorCurso} class="mx-auto flex w-full max-w-280 flex-col gap-4 max-lg:min-h-full lg:px-10 lg:pt-8 lg:pb-12 2xl:max-w-360">
    {#if avisoError}
      <div role="alert" class="mx-4 mt-4 flex items-start gap-2.5 rounded-xl border border-dm-error bg-dm-error-s py-1 pr-1 pl-4 text-dm-error lg:mx-0 lg:mt-0">
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

    <article aria-labelledby="t-actividad" class="flex flex-col bg-dm-tarjeta max-lg:flex-1 lg:overflow-hidden lg:rounded-xl lg:border lg:border-dm-linea">
      <ActividadEncabezado
        idCurso={id_curso}
        codigo={cod_asignatura ?? ''}
        nombreCurso={nombre_curso}
        nombre={nombre_actividad}
        {chips}
        {destacado}
        {pestanas}
        seleccion={seccion}
        panel="panel-actividad"
        onCambiar={alElegirPestana}
      />

      <div class="flex flex-col gap-8 px-4 pt-6 pb-10 lg:grid lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start lg:px-10 lg:pt-8">
        <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
        <div
          role="tabpanel"
          id="panel-actividad"
          aria-labelledby="tab-{seccion}"
          tabindex="0"
          class="flex min-w-0 flex-col gap-5 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4"
        >
          {#if seccion === 'enunciado'}
            <section aria-labelledby="t-enunciado" class="flex flex-col gap-4">
              <h2 id="t-enunciado" class="text-xl leading-7 font-bold lg:text-[22px]">
                {calificada ? 'Qué había que entregar' : 'Qué hay que entregar'}
              </h2>
              {#if archivo_enunciado}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border border-dm-linea bg-dm-sutil p-4">
                  <span class="flex min-w-0 flex-[1_1_220px] items-center gap-3.5">
                    <span aria-hidden="true" class="flex size-12 shrink-0 items-center justify-center rounded-[10px] bg-dm-accion-suave text-dm-accion-texto">
                      <FileText size={22} aria-hidden="true" />
                    </span>
                    <span class="flex min-w-0 flex-col">
                      <span class="truncate text-[15px] leading-5 font-bold">{archivo_enunciado.nombre_original}</span>
                      {#if detalleEnunciado}<span class="text-[13px] leading-[18px] text-dm-ink-3">{detalleEnunciado}</span>{/if}
                    </span>
                  </span>
                  <span class="flex gap-2 max-sm:w-full">
                    {#if enunciadoEsPdf}
                      <button type="button" class="{btnSuave} max-sm:flex-1" onclick={abrirEnunciado}>
                        <Eye size={16} aria-hidden="true" />Ver PDF
                      </button>
                    {/if}
                    <a href={urlEnunciado} download={archivo_enunciado.nombre_original} class="{btnSecundario} max-sm:flex-1">
                      <Download size={16} aria-hidden="true" />Descargar
                    </a>
                  </span>
                </div>
              {:else}
                <p class="text-[15px] leading-[22px] text-dm-ink-2">Tu docente todavía no adjunta el enunciado de esta actividad.</p>
              {/if}
            </section>
            {#if pestanas.some((p) => p.id === 'rubrica')}
              {@render paso(
                'Siguiente paso',
                rubricaEvaluada ? 'Revisa cómo te evaluaron' : 'Revisa cómo se evaluará la entrega',
                rubricaEvaluada ? 'Ver la rúbrica evaluada' : 'Ver la rúbrica',
                'rubrica',
              )}
            {/if}
          {:else if seccion === 'rubrica'}
            <RubricaAlumno
              rubrica={rubricaPanel.rubrica}
              resultado={rubricaPanel.resultado}
              puntajeObtenido={rubricaPanel.puntaje_obtenido}
              retroalimentacion={rubricaPanel.retroalimentacion}
              subtitulo={subtituloRubrica}
              {exigencia}
            />
            {#if id_actividad_asignada_grupo}
              {@render paso(
                calificada ? '¿Dudas sobre la nota?' : '¿Dudas sobre un criterio?',
                calificada ? 'Escríbele a tu docente en la agenda' : 'Pregúntale a tu docente en la agenda',
                'Ir a la agenda',
                'agenda',
              )}
            {/if}
          {:else if seccion === 'entrega'}
            {@render lateral()}
          {:else if seccion === 'agenda'}
            <ActividadAgendaResumen
              interacciones={interaccionesCombinadas}
              esGrupal={es_grupal}
              {calificada}
              vista={agendaVista}
              onAbrir={abrirAgenda}
              onVerRubrica={verRubricaDesdeAgenda}
            />
          {/if}
        </div>

        {#if escritorio.current}
          <aside class="flex min-w-0 flex-col gap-4" aria-label="Tu entrega y tu nota">
            {@render lateral()}
          </aside>
        {/if}
      </div>
    </article>
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
    onVerRubrica={verRubricaDesdeAgenda}
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
