<script lang="ts">
  /**
   * Visor del documento de syllabus — `/docente/cursos/{curso}/programa` y
   * `/admin/cursos/{curso}/programa/revisar` (lámina «Visor de syllabus
   * (documento)»).
   *
   * Docente titular y revisor leen exactamente el mismo documento: mismo índice
   * de 240, misma columna de lectura de 760, mismo historial de 280, mismos
   * banners y el mismo sello. Lo único que se recompone es el pie de acciones y
   * las migas. **El documento se lee; escribir ocurre en el wizard**
   * (`SyllabusWizard`, su propia página), por eso no hay ni un control de
   * edición dentro del papel.
   *
   * Correspondencia con el modelo (no hay estado RECHAZADO en la BD):
   *
   *   BORRADOR + razón de rechazo vigente → «RECHAZADO»
   *   BORRADOR                            → «BORRADOR»
   *   BASICO_COMPLETO                     → «BÁSICO COMPLETO»
   *   COMPLETO / ENVIADO                  → «EN REVISIÓN»
   *   APROBADO / PUBLICADO                → «APROBADO» (sellado, sin acciones)
   *
   * Rechazar devuelve el programa a BORRADOR y deja la razón en
   * `auditoria.programa_historial`; esa tabla es además la única fuente de
   * fechas del documento, porque `curso.programa` no tiene ninguna columna de
   * fecha (`$timestamps = false`).
   *
   * De la lámina quedó fuera lo que el modelo no guarda: el prerrequisito de la
   * sección I (no existe tabla de prerrequisitos), la columna «Horas» por unidad
   * (`UnidadSyllabus` no la tiene) y el marcado en rojo del índice de las
   * secciones citadas en un rechazo (la razón es texto libre, no referencias).
   */
  import { Link, router, page } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import {
    AlertOctagon,
    BadgeCheck,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronRight,
    Clock,
    FilePlus,
    FileText,
    File as FileIcon,
    GitBranch,
    Lock,
    Pencil,
    PenTool,
    Printer,
    Save,
    Send,
    Trash2,
    User,
    X,
    XCircle,
  } from 'lucide-svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Curso, Asignatura, Programa } from '@/types/admin.types';
  import {
    parseFechaSoloDia,
    formatFechaCorta,
    formatFechaHora,
    formatFechaLarga,
  } from '@/utils/formatters';
  import DatePickerCL from '@/components/custom/common/DatePickerCL.svelte';
  import ProgramaDocument from '@/modules/resources/programa/components/ProgramaDocument.svelte';
  import SyllabusIndice from '@/modules/resources/programa/components/SyllabusIndice.svelte';
  import SyllabusHistorial from '@/modules/resources/programa/components/SyllabusHistorial.svelte';
  import SyllabusRechazoDialog from '@/modules/resources/programa/components/SyllabusRechazoDialog.svelte';
  import {
    estadoVisual,
    estaSellado,
    estaEnRevision,
    type EventoHistorial,
  } from '@/modules/resources/programa/utils/syllabusEstado';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';

  interface SeccionSyllabus {
    numeral_romano?: string;
    nombre_seccion: string;
    contenidos?: Array<{ texto_contenido: string | null }>;
    contenidos_programa?: Array<{ texto_contenido: string | null }>;
    componentes?: any[];
    ponderacion_optativa?: { porcentaje?: number } | null;
  }

  /**
   * Lo que el visor necesita por encima del tipo compartido `Programa`. Se
   * omiten `secciones` (aquí llegan ya aplanadas por `ParsesSyllabus`, sin el
   * `orden` del tipo administrativo) para no chocar con esa declaración.
   */
  interface ProgramaVisor extends Omit<Programa, 'secciones'> {
    secciones?: SeccionSyllabus[];
    secciones_requeridas?: string[];
    tipo_syllabus?: string | null;
    historial?: EventoHistorial[];
    fecha_modificacion?: string | null;
    fecha_aprobacion?: string | null;
    autor?: string | null;
    revisor?: string | null;
    razon_rechazo?: string | null;
    fecha_rechazo?: string | null;
    rechazado_por?: string | null;
  }

  /**
   * Nombres canónicos de las nueve secciones — los mismos que emite
   * `App\Traits\ParsesSyllabus`. Sólo se usan para nombrar una sección exigida
   * que no viene en el JSONB del programa.
   */
  const NOMBRES_SECCION: Record<string, string> = {
    I: 'Identificación',
    II: 'Presentación',
    III: 'Estándares',
    IV: 'Competencias',
    V: 'Evaluación Diagnóstica',
    VI: 'Unidades',
    VII: 'Planificación',
    VIII: 'Recursos',
    IX: 'Aspectos Administrativos',
  };

  interface Props {
    curso: Curso & {
      cod_asignatura?: string | null;
      letra_grupo?: string | null;
      agno_real?: number | null;
      semestre_real?: number | null;
      docente_titular?: string | null;
    };
    asignatura?: Asignatura | null;
    programa: ProgramaVisor | null;
    canApprove?: boolean;
    canEdit?: boolean;
    canCreate?: boolean;
    canDelete?: boolean;
    userPermissions?: Permission[];
    userId?: number;
    layoutType?: 'docente' | 'ayudante' | 'estudiante' | 'admin';
    backUrl?: string;
    breadcrumbs?: BreadcrumbItem[];
    mode?: string;
  }

  let {
    curso,
    asignatura: asignaturaProp = null,
    programa,
    canApprove = false,
    canEdit = false,
    canCreate = false,
    canDelete = false,
    userPermissions = [],
    layoutType = 'docente',
    backUrl,
    breadcrumbs = [],
  }: Props = $props();

  const asignatura = $derived(asignaturaProp ?? curso?.asignatura ?? null);
  const esAdmin = $derived(layoutType === 'admin');

  const resolvedBackUrl = $derived(
    backUrl ??
      (layoutType === 'ayudante'
        ? `/ayudante/cursos/${curso?.id_curso}`
        : layoutType === 'estudiante'
          ? `/estudiante/cursos/${curso?.id_curso}`
          : esAdmin
            ? '/admin/cursos'
            : '/docente/cursos'),
  );

  const codigo = $derived(curso?.cod_asignatura ?? asignatura?.cod_asignatura ?? null);
  const titulo = $derived(asignatura?.nombre ?? curso?.asignatura_nombre ?? curso?.nombre ?? '');
  const rotuloCurso = $derived([codigo, titulo].filter(Boolean).join(' '));

  // ── Estado del documento ──────────────────────────────────────────────────

  const fueRechazado = $derived(
    programa?.estado === 'BORRADOR' && !!programa?.razon_rechazo?.trim(),
  );
  const sellado = $derived(estaSellado(programa?.estado));
  const enRevision = $derived(estaEnRevision(programa?.estado));
  const badge = $derived(estadoVisual(programa?.estado ?? null, fueRechazado));
  const tipoSyllabus = $derived(
    programa?.tipo_syllabus ?? programa?.data_syllabus?.metadata?.tipo_syllabus ?? null,
  );

  const secciones = $derived<SeccionSyllabus[]>(programa?.secciones ?? []);

  /**
   * Numerales que este tipo de syllabus exige para poder entregarse
   * (`ProgramaService::getRequiredSecciones`). Es un criterio de completitud,
   * **no** el índice: hay programas guardados con la estructura antigua de
   * `SyllabusStructure`, cuyos numerales y nombres no coinciden con los nueve
   * del asistente. El documento manda sobre qué secciones se listan.
   */
  const requeridas = $derived(programa?.secciones_requeridas ?? []);

  function tieneContenido(seccion: SeccionSyllabus | undefined): boolean {
    const items = seccion?.contenidos ?? seccion?.contenidos_programa ?? [];
    return items.some((c) => c.texto_contenido?.trim());
  }

  /**
   * Índice del documento: lo que el programa trae, en su propio orden, más las
   * secciones exigidas que ni siquiera existen en el JSONB — que se muestran
   * como pendientes en vez de desaparecer.
   */
  const seccionesDelTipo = $derived.by(() => {
    const presentes = secciones.filter((s) => !!s.numeral_romano);
    const vistos = new Set(presentes.map((s) => s.numeral_romano));

    const faltantes = requeridas
      .filter((numeral) => !vistos.has(numeral))
      .map(
        (numeral): SeccionSyllabus => ({
          numeral_romano: numeral,
          nombre_seccion: NOMBRES_SECCION[numeral] ?? `Sección ${numeral}`,
        }),
      );

    return [...presentes, ...faltantes];
  });

  const pendientes = $derived(
    seccionesDelTipo.filter(
      (s) => requeridas.includes(s.numeral_romano ?? '') && !tieneContenido(s),
    ),
  );

  const entradasIndice = $derived(
    seccionesDelTipo.map((s) => ({
      numeral: s.numeral_romano ?? '',
      nombre: s.nombre_seccion,
      completa: tieneContenido(s),
    })),
  );

  const documentoCompleto = $derived(requeridas.length > 0 && pendientes.length === 0);

  const razonBloqueoEnvio = $derived(
    documentoCompleto
      ? null
      : requeridas.length === 0
        ? 'Este documento no declara qué secciones exige'
        : `Faltan ${pendientes.length === 1 ? 'la sección' : 'las secciones'} ${pendientes
            .map((s) => s.numeral_romano)
            .join(', ')}`,
  );

  // ── Permisos ──────────────────────────────────────────────────────────────

  const canEditPrograma = $derived(
    canEdit ||
      hasPermission(userPermissions, 'cursos/programas:modificar:modulo_1') ||
      hasPermission(userPermissions, 'cursos/programas:*'),
  );

  /** Docente y ayudante: sólo se edita mientras el documento no esté sellado. */
  const puedeEditar = $derived(canEditPrograma && (esAdmin || !sellado));
  const puedeCrear = $derived(canCreate || (canEditPrograma && layoutType !== 'estudiante'));

  // ── Índice: sección visible ───────────────────────────────────────────────

  let seccionActiva = $state<string | null>(null);

  $effect(() => {
    // Depende del documento renderizado: al cambiar de programa se re-observa.
    void seccionesDelTipo.length;

    const nodos = Array.from(document.querySelectorAll<HTMLElement>('[id^="seccion-"]'));
    if (nodos.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((e) => e.isIntersecting)
          .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (visible) seccionActiva = visible.target.id.replace('seccion-', '');
      },
      { rootMargin: '-10% 0px -70% 0px', threshold: 0 },
    );

    nodos.forEach((n) => observer.observe(n));
    return () => observer.disconnect();
  });

  // ── Asistente ─────────────────────────────────────────────────────────────
  // Escribir ya no ocurre en un modal encima del documento: el asistente es una
  // página propia (`docente/SyllabusWizard.svelte`) con su URL.

  /**
   * Abre el asistente. `tipo` sólo hace falta cuando se decide el tipo aquí:
   * al crear desde cero y al promover un básico a completo. Editando, el tipo
   * lo dicta el propio programa.
   */
  function irAlAsistente(tipo?: 'BASICO' | 'COMPLETO') {
    const base = esAdmin
      ? `${rutaAdmin}/editar`
      : layoutType === 'ayudante'
        ? `/ayudante/cursos/${curso.id_curso}/programa/editar`
        : `${rutaDocente}/editar`;
    router.visit(tipo ? `${base}?tipo=${tipo}` : base);
  }

  const abrirEdicion = () => irAlAsistente();

  // ── Transiciones de estado ────────────────────────────────────────────────

  let accionEnCurso = $state<'basico' | 'enviar' | 'aprobar' | 'rechazar' | 'eliminar' | null>(
    null,
  );

  const rutaDocente = $derived(`/docente/cursos/${curso.id_curso}/programa`);
  const rutaAdmin = $derived(`/admin/cursos/${curso.id_curso}/programa`);

  function completarBasico() {
    accionEnCurso = 'basico';
    router.put(
      `${rutaDocente}/completar-basico`,
      {},
      { onFinish: () => (accionEnCurso = null) },
    );
  }

  function enviarParaRevision() {
    if (!documentoCompleto) return;
    accionEnCurso = 'enviar';
    router.put(`${rutaDocente}/enviar`, {}, { onFinish: () => (accionEnCurso = null) });
  }

  let confirmandoEliminar = $state(false);

  function eliminarPrograma() {
    accionEnCurso = 'eliminar';
    router.delete(rutaDocente, {
      onFinish: () => {
        accionEnCurso = null;
        confirmandoEliminar = false;
      },
    });
  }

  function aprobar() {
    accionEnCurso = 'aprobar';
    router.put(`${rutaAdmin}/aprobar`, {}, { onFinish: () => (accionEnCurso = null) });
  }

  let rechazoAbierto = $state(false);

  function rechazar(razon: string) {
    accionEnCurso = 'rechazar';
    router.put(
      `${rutaAdmin}/rechazar`,
      { razon_rechazo: razon, accion_tipo: 'rechazo' },
      {
        onSuccess: () => (rechazoAbierto = false),
        onFinish: () => (accionEnCurso = null),
      },
    );
  }

  // ── Historial: panel fijo en ≥1440, slide-over abajo ───────────────────────

  const historial = $derived<EventoHistorial[]>(programa?.historial ?? []);
  let historialAbierto = $state(false);

  // ── Fechas límite ─────────────────────────────────────────────────────────

  function toDateInput(val: string | null | undefined): string {
    return val ? val.slice(0, 10) : '';
  }

  function formatDate(val: string | null | undefined): string {
    return val ? formatFechaCorta(val) : '—';
  }

  function diasRestantes(val: string | null | undefined): number | null {
    if (!val) return null;
    return Math.ceil((parseFechaSoloDia(val).getTime() - Date.now()) / 86_400_000);
  }

  function estaVencida(val: string | null | undefined): boolean {
    return !!val && parseFechaSoloDia(val) < new Date();
  }

  /** Plazo que le corre al docente ahora mismo, según el estado del documento. */
  const plazoVigente = $derived.by(() => {
    if (esAdmin || layoutType !== 'docente') return null;
    const estado = programa?.estado ?? null;
    if (!estado || estado === 'BORRADOR') {
      const d = curso.fecha_limite_entrega_basico;
      return d ? { label: 'Entrega del programa básico', value: d } : null;
    }
    if (estado === 'BASICO_COMPLETO') {
      const d = curso.fecha_limite_entrega_syllabus;
      return d ? { label: 'Entrega del syllabus completo', value: d } : null;
    }
    return null;
  });

  let editandoFechas = $state(false);
  let guardandoFechas = $state(false);
  let fechaBasico = $state<string | null>(toDateInput(curso.fecha_limite_entrega_basico) || null);
  let fechaSyllabus = $state<string | null>(
    toDateInput(curso.fecha_limite_entrega_syllabus) || null,
  );

  function guardarFechas() {
    guardandoFechas = true;
    router.put(
      `${rutaAdmin}/fechas`,
      {
        fecha_limite_entrega_basico: fechaBasico || null,
        fecha_limite_entrega_syllabus: fechaSyllabus || null,
      },
      {
        onSuccess: () => (editandoFechas = false),
        onFinish: () => (guardandoFechas = false),
      },
    );
  }

  function cancelarEdicionFechas() {
    fechaBasico = toDateInput(curso.fecha_limite_entrega_basico) || null;
    fechaSyllabus = toDateInput(curso.fecha_limite_entrega_syllabus) || null;
    editandoFechas = false;
  }

  // ── Flash ─────────────────────────────────────────────────────────────────

  const flash = $derived(($page.props as any)?.flash ?? {});
  const flashError = $derived(flash.error as string | undefined);
  const flashOk = $derived((flash.success ?? flash.warning) as string | undefined);

  // ── Vocabulario visual compartido ─────────────────────────────────────────

  const BTN_OUTLINE =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2.5 text-[14px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA] disabled:opacity-50';
  const BTN_PRIMARY =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#002F6C] bg-[#002F6C] px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-[#1B4789] disabled:opacity-60';
  const BTN_PELIGRO =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#F0D2D2] bg-white px-3.5 py-2.5 text-[14px] font-medium text-[#B91C1C] transition-colors hover:bg-[#FEF2F2] disabled:opacity-50';
  const BTN_BLOQUEADO =
    'inline-flex cursor-not-allowed items-center gap-[7px] rounded-lg border border-[#E0E3E9] bg-[#E9EBEF] px-4 py-2.5 text-[14px] font-semibold text-[#9AA0AE]';
  const META = 'inline-flex items-center gap-1.5';
</script>

{#snippet metaFila()}
  <div
    class="flex flex-wrap items-center gap-x-[18px] gap-y-2 border-t border-dashed border-[#E5E7EB] pt-2.5 text-[12px] text-[#5A5E6E]"
  >
    {#if tipoSyllabus}
      <span class={META}>
        <FileText size={14} class="text-[#9AA0AE]" aria-hidden="true" />
        Tipo <strong class="font-semibold text-[#1A1A24]">{tipoSyllabus}</strong>
        {#if requeridas.length > 0}
          · {requeridas.length}
          {requeridas.length === 1 ? 'sección exigida' : 'secciones exigidas'}
        {/if}
      </span>
    {/if}
    {#if programa?.version_programa}
      <span class={META}>
        <GitBranch size={14} class="text-[#9AA0AE]" aria-hidden="true" />
        Versión <strong class="font-semibold text-[#1A1A24]">{programa.version_programa}</strong>
      </span>
    {/if}
    {#if programa?.fecha_modificacion}
      <span class={META}>
        <CalendarDays size={14} class="text-[#9AA0AE]" aria-hidden="true" />
        Última modificación {formatFechaHora(programa.fecha_modificacion)}
      </span>
    {/if}
    {#if programa?.autor}
      <span class={META}>
        <User size={14} class="text-[#9AA0AE]" aria-hidden="true" />
        Autor {programa.autor}
      </span>
    {/if}
    {#if programa?.fecha_aprobacion}
      <span class={META}>
        <CheckCircle2 size={14} class="text-[#059669]" aria-hidden="true" />
        Aprobado {formatDate(programa.fecha_aprobacion)}
      </span>
    {/if}
    {#if sellado && programa?.revisor}
      <span class={META}>
        <PenTool size={14} class="text-[#9AA0AE]" aria-hidden="true" />
        Revisado por {programa.revisor}
      </span>
    {/if}
  </div>
{/snippet}

{#snippet pageContent()}
  <div class="min-h-screen bg-white pb-4 print:pb-0">
    <div class="mx-auto flex max-w-[1440px] flex-col gap-4 px-4 py-6 sm:px-8">
      <!-- ── Migas ── -->
      <nav
        class="flex flex-wrap items-center gap-1.5 text-[12px] text-[#5A5E6E] print:hidden"
        aria-label="Ruta de navegación"
      >
        {#if esAdmin}
          <span class="font-semibold text-[#4F46E5]">Admin</span>
          <ChevronRight size={12} class="text-[#9AA0AE]" aria-hidden="true" />
          <Link
            href="/admin/syllabus"
            class="rounded-lg px-1.5 py-1 font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F5F1EA] hover:text-[#002F6C]"
          >
            Syllabus
          </Link>
        {:else}
          <Link
            href={resolvedBackUrl}
            class="rounded-lg px-1.5 py-1 font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F5F1EA] hover:text-[#002F6C]"
          >
            Mis cursos
          </Link>
        {/if}
        <ChevronRight size={12} class="text-[#9AA0AE]" aria-hidden="true" />
        <span class="px-1">{rotuloCurso}</span>
        <ChevronRight size={12} class="text-[#9AA0AE]" aria-hidden="true" />
        <span class="px-1 font-medium text-[#1A1A24]" aria-current="page">Programa</span>
      </nav>

      {#if flashError}
        <p
          class="m-0 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-[13px] font-medium text-[#B91C1C] print:hidden"
          role="alert"
        >
          {flashError}
        </p>
      {/if}
      {#if flashOk}
        <p
          class="m-0 rounded-lg border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3 text-[13px] font-medium text-[#047857] print:hidden"
          role="status"
        >
          {flashOk}
        </p>
      {/if}

      <div
        class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_1px_3px_rgba(0,0,0,.08)]"
      >
        <!-- ── Cabecera del documento ── -->
        <header
          class="flex flex-col gap-2.5 border-b border-[#E5E7EB] bg-white px-5 pt-3.5 pb-4 sm:px-7 {esAdmin
            ? 'shadow-[inset_3px_0_0_#4F46E5]'
            : ''}"
        >
          <div class="flex flex-wrap items-start gap-5">
            <div class="flex min-w-0 flex-col gap-1.5">
              <div class="flex flex-wrap items-baseline gap-2.5">
                {#if codigo}
                  <span class="font-mono text-[14px] text-[#5A5E6E]">{codigo}</span>
                {/if}
                <h1 class="m-0 text-[24px] font-semibold tracking-[-0.01em] text-[#1A1A24]">
                  {titulo}
                </h1>
              </div>
              <div class="flex flex-wrap items-center gap-2.5 text-[12.5px] text-[#5A5E6E]">
                {#if curso.letra_grupo}
                  <span
                    class="inline-flex h-5 w-5 items-center justify-center rounded-[5px] bg-[#F5F1EA] text-[11.5px] font-semibold text-[#5A5E6E]"
                    aria-hidden="true">{curso.letra_grupo}</span
                  >
                  <span>Grupo {curso.letra_grupo}</span>
                {/if}
                {#if curso.agno_real && curso.semestre_real}
                  <span class="text-[#D6D9E0]" aria-hidden="true">·</span>
                  <span>{curso.semestre_real}.º semestre {curso.agno_real}</span>
                {/if}
                {#if curso.carrera_nombre}
                  <span class="text-[#D6D9E0]" aria-hidden="true">·</span>
                  <span>{curso.carrera_nombre}</span>
                {/if}
              </div>
            </div>

            <div class="ml-auto flex flex-none flex-wrap items-center gap-2.5">
              <button
                type="button"
                onclick={() => window.print()}
                class="inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-[13px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA] print:hidden"
              >
                <Printer size={15} class="text-[#5A5E6E]" aria-hidden="true" />
                Imprimir
              </button>
              {#if programa && historial.length > 0}
                <button
                  type="button"
                  onclick={() => (historialAbierto = true)}
                  class="inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-[13px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA] print:hidden xl:hidden"
                >
                  <Clock size={15} class="text-[#5A5E6E]" aria-hidden="true" />
                  Historial
                </button>
              {/if}
              <span
                class="inline-flex items-center gap-[7px] rounded-full border px-3 py-1 text-[12.5px] font-semibold {badge.pill}"
              >
                <span class="h-[7px] w-[7px] rounded-full {badge.dot}" aria-hidden="true"></span>
                {badge.label}
              </span>
            </div>
          </div>

          {#if programa}
            {@render metaFila()}
          {/if}
        </header>

        {#if programa}
          <!-- ── Banner de rechazo: primer contenido de la pantalla ── -->
          {#if fueRechazado && !esAdmin}
            <div class="bg-[#F5F1EA] px-5 pt-5 sm:px-7">
              <div
                class="flex gap-3.5 rounded-[10px] border border-[#E5B4B4] border-l-4 border-l-[#8A1538] bg-[#FDF3F3] px-5 py-4"
                role="alert"
              >
                <AlertOctagon size={20} class="mt-0.5 flex-none text-[#8A1538]" aria-hidden="true" />
                <div class="flex min-w-0 flex-col gap-2">
                  <div class="flex flex-wrap items-baseline gap-2.5">
                    <span class="text-[15.5px] font-semibold text-[#7A1230]">
                      Syllabus rechazado{programa.rechazado_por ? ` por ${programa.rechazado_por}` : ''}
                    </span>
                    {#if programa.fecha_rechazo}
                      <span class="text-[12.5px] text-[#8A6A6A]"
                        >{formatDate(programa.fecha_rechazo)}</span
                      >
                    {/if}
                  </div>
                  <p
                    class="m-0 max-w-[800px] text-[14.5px] leading-[1.6] text-pretty whitespace-pre-wrap text-[#4A2028]"
                  >
                    {programa.razon_rechazo}
                  </p>
                  {#if puedeEditar}
                    <div class="flex flex-wrap items-center gap-3.5 pt-0.5 print:hidden">
                      <button
                        type="button"
                        onclick={abrirEdicion}
                        class="inline-flex items-center gap-[7px] rounded-lg border border-[#8A1538] bg-[#8A1538] px-3.5 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-[#7A1230]"
                      >
                        <Pencil size={14} aria-hidden="true" />
                        Editar syllabus
                      </button>
                    </div>
                  {/if}
                </div>
              </div>
            </div>
          {/if}

          <!-- ── Plazo vigente del docente ── -->
          {#if plazoVigente}
            {@const dias = diasRestantes(plazoVigente.value)}
            {@const vencido = dias !== null && dias < 0}
            {@const urgente = dias !== null && dias >= 0 && dias <= 3}
            <div class="bg-[#F5F1EA] px-5 pt-5 sm:px-7 print:hidden">
              <p
                class="m-0 flex flex-wrap items-center gap-2 rounded-[10px] border px-4 py-3 text-[13px] {vencido
                  ? 'border-[#F6C9C9] bg-[#FEF2F2] text-[#B91C1C]'
                  : urgente
                    ? 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]'
                    : 'border-[#C9D6E6] bg-[#E8EDF5] text-[#002F6C]'}"
              >
                <CalendarDays size={15} class="flex-none" aria-hidden="true" />
                <span class="font-semibold">{plazoVigente.label}</span>
                <span>· {formatFechaLarga(plazoVigente.value)}</span>
                {#if dias !== null}
                  <span class="font-semibold">
                    ·
                    {#if vencido}
                      Vencido hace {Math.abs(dias)}
                      {Math.abs(dias) === 1 ? 'día' : 'días'}
                    {:else if dias === 0}
                      Vence hoy
                    {:else}
                      {dias}
                      {dias === 1 ? 'día restante' : 'días restantes'}
                    {/if}
                  </span>
                {/if}
              </p>
            </div>
          {/if}

          <!-- ── Índice · lectura · contexto ── -->
          <div
            class="flex flex-col items-start gap-6 bg-[#F5F1EA] px-5 py-6 sm:px-7 lg:flex-row print:bg-white print:p-0"
          >
            <div class="w-full lg:w-[240px] lg:flex-none print:hidden">
              <SyllabusIndice
                entradas={entradasIndice}
                activa={seccionActiva}
                tipo={tipoSyllabus}
                {requeridas}
                mostrarProgreso={!sellado && !enRevision}
              />
            </div>

            <div class="flex w-full min-w-0 flex-col gap-3 lg:w-[760px] lg:flex-none">
              {#if sellado}
                <div class="flex justify-end print:justify-start">
                  <span
                    class="inline-flex flex-wrap items-center gap-2.5 rounded-[10px] border border-[#A7F3D0] bg-[#ECFDF5] px-3.5 py-2"
                  >
                    <BadgeCheck size={17} class="text-[#047857]" aria-hidden="true" />
                    <span class="text-[13px] font-semibold text-[#047857]">APROBADO</span>
                    {#if programa.fecha_aprobacion || programa.revisor}
                      <span class="h-3.5 w-px bg-[#A7F3D0]" aria-hidden="true"></span>
                      <span class="text-[12.5px] text-[#0F6B4F]">
                        {[
                          programa.fecha_aprobacion ? formatDate(programa.fecha_aprobacion) : null,
                          programa.revisor,
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </span>
                    {/if}
                  </span>
                </div>
              {/if}

              <ProgramaDocument
                secciones={seccionesDelTipo}
                pendientes={sellado || enRevision ? [] : pendientes}
              />
            </div>

            {#if historial.length > 0}
              <div class="hidden xl:block xl:w-[280px] xl:flex-none print:hidden">
                <SyllabusHistorial eventos={historial} />
              </div>
            {/if}
          </div>

          <!-- ── Pie de acciones ── -->
          <div
            class="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 border-t border-[#E5E7EB] bg-white px-5 py-3.5 shadow-[0_-1px_3px_rgba(0,0,0,.06)] sm:px-7 print:hidden"
          >
            {#if sellado && !esAdmin}
              <p class="m-0 flex items-center gap-2 text-[13px] text-[#5A5E6E]">
                <Lock size={15} class="text-[#5A5E6E]" aria-hidden="true" />
                Sellado{programa.fecha_aprobacion
                  ? ` el ${formatDate(programa.fecha_aprobacion)}`
                  : ''}. Para modificarlo, solicita reapertura a tu jefatura.
              </p>
            {:else if enRevision && !esAdmin}
              <p class="m-0 flex items-center gap-2 text-[13px] text-[#5A5E6E]">
                <Send size={15} class="text-[#5A5E6E]" aria-hidden="true" />
                En revisión desde {formatFechaCorta(programa.fecha_modificacion)}. Mientras dure la
                revisión el documento sólo se lee.
              </p>
            {:else if esAdmin}
              <p class="m-0 text-[12.5px] text-[#5A5E6E]">
                Tu decisión queda registrada con tu nombre y fecha en el historial del syllabus.
              </p>
            {:else}
              <p class="m-0 text-[12.5px] text-[#5A5E6E]">
                Editar abre el asistente del syllabus. Aquí solo se lee.
              </p>
            {/if}

            <div class="ml-auto flex flex-wrap items-center gap-2">
              {#if esAdmin}
                {#if puedeEditar}
                  <button type="button" onclick={abrirEdicion} class={BTN_OUTLINE}>
                    <Pencil size={15} class="text-[#5A5E6E]" aria-hidden="true" />
                    Editar
                  </button>
                {/if}
                {#if canApprove}
                  <button
                    type="button"
                    onclick={() => (rechazoAbierto = true)}
                    disabled={accionEnCurso !== null}
                    class="inline-flex items-center gap-[7px] rounded-lg border border-[#DC2626] bg-white px-3.5 py-2.5 text-[14px] font-semibold text-[#B91C1C] transition-colors hover:bg-[#FEF2F2] disabled:opacity-50"
                  >
                    <XCircle size={15} aria-hidden="true" />
                    {sellado ? 'Revocar aprobación' : 'Rechazar'}
                  </button>
                  {#if !sellado}
                    <button
                      type="button"
                      onclick={aprobar}
                      disabled={accionEnCurso !== null}
                      class="inline-flex items-center gap-[7px] rounded-lg border border-[#059669] bg-[#059669] px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-[#047857] disabled:opacity-60"
                    >
                      <Check size={15} aria-hidden="true" />
                      {accionEnCurso === 'aprobar' ? 'Aprobando…' : 'Aprobar'}
                    </button>
                  {/if}
                {/if}
              {:else if !sellado && !enRevision}
                {#if canDelete}
                  {#if confirmandoEliminar}
                    <span class="text-[12.5px] font-medium text-[#B91C1C]"
                      >¿Eliminar este syllabus?</span
                    >
                    <button
                      type="button"
                      onclick={() => (confirmandoEliminar = false)}
                      class={BTN_OUTLINE}
                    >
                      Cancelar
                    </button>
                    <button
                      type="button"
                      onclick={eliminarPrograma}
                      disabled={accionEnCurso !== null}
                      class="inline-flex items-center gap-[7px] rounded-lg border border-[#DC2626] bg-[#DC2626] px-3.5 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-[#B91C1C] disabled:opacity-60"
                    >
                      <Trash2 size={15} aria-hidden="true" />
                      {accionEnCurso === 'eliminar' ? 'Eliminando…' : 'Sí, eliminar'}
                    </button>
                  {:else}
                    <button
                      type="button"
                      onclick={() => (confirmandoEliminar = true)}
                      class={BTN_PELIGRO}
                    >
                      <Trash2 size={15} aria-hidden="true" />
                      Eliminar
                    </button>
                  {/if}
                {/if}
                {#if puedeEditar && !confirmandoEliminar}
                  <button type="button" onclick={abrirEdicion} class={BTN_OUTLINE}>
                    <Pencil size={15} class="text-[#5A5E6E]" aria-hidden="true" />
                    {tipoSyllabus === 'BASICO' && programa.estado === 'BASICO_COMPLETO'
                      ? 'Editar sección básica'
                      : 'Editar'}
                  </button>
                {/if}
                {#if puedeEditar && !confirmandoEliminar}
                  {#if tipoSyllabus === 'BASICO' && programa.estado === 'BASICO_COMPLETO'}
                    <!--
                      La versión básica ya está entregada y es pública. El único
                      camino hacia arriba es escribir las secciones que le faltan
                      al COMPLETO (III, IV, V, IX): «Enviar para revisión» aquí
                      promovería el documento con esas secciones vacías y el
                      revisor no podría aprobarlo.
                    -->
                    <button
                      type="button"
                      onclick={() => irAlAsistente('COMPLETO')}
                      class={BTN_PRIMARY}
                    >
                      <FileText size={15} aria-hidden="true" />
                      Completar syllabus
                    </button>
                  {:else if tipoSyllabus === 'BASICO'}
                    <!-- La versión básica no pasa por aprobación: se entrega y ya. -->
                    {#if documentoCompleto}
                      <button
                        type="button"
                        onclick={completarBasico}
                        disabled={accionEnCurso !== null}
                        class={BTN_PRIMARY}
                      >
                        <Check size={15} aria-hidden="true" />
                        {accionEnCurso === 'basico' ? 'Entregando…' : 'Completar básico'}
                      </button>
                    {:else}
                      <span class="relative inline-flex flex-col items-end gap-1.5">
                        <span
                          class="rounded-md bg-[#1A1A24] px-2.5 py-1.5 text-[12px] text-white shadow-[0_6px_16px_rgba(0,0,0,.2)]"
                        >
                          {razonBloqueoEnvio}
                        </span>
                        <span class={BTN_BLOQUEADO} aria-disabled="true">
                          <Check size={15} aria-hidden="true" />
                          Completar básico
                        </span>
                      </span>
                    {/if}
                  {:else if documentoCompleto}
                    <button
                      type="button"
                      onclick={enviarParaRevision}
                      disabled={accionEnCurso !== null}
                      class={BTN_PRIMARY}
                    >
                      <Send size={15} aria-hidden="true" />
                      {accionEnCurso === 'enviar' ? 'Enviando…' : 'Enviar para revisión'}
                    </button>
                  {:else}
                    <span class="relative inline-flex flex-col items-end gap-1.5">
                      <span
                        class="rounded-md bg-[#1A1A24] px-2.5 py-1.5 text-[12px] text-white shadow-[0_6px_16px_rgba(0,0,0,.2)]"
                      >
                        {razonBloqueoEnvio}
                      </span>
                      <span class={BTN_BLOQUEADO} aria-disabled="true">
                        <Send size={15} aria-hidden="true" />
                        Enviar para revisión
                      </span>
                    </span>
                  {/if}
                {/if}
              {/if}
            </div>
          </div>
        {:else}
          <!-- ── Curso sin syllabus ── -->
          <div
            class="flex flex-col items-center gap-[18px] bg-[#F5F1EA] px-6 py-14 text-center sm:px-10"
          >
            <span
              class="flex h-[52px] w-[52px] items-center justify-center rounded-xl border border-[#E5E7EB] bg-white"
            >
              {#if esAdmin}
                <Clock size={22} class="text-[#5A5E6E]" aria-hidden="true" />
              {:else}
                <FilePlus size={22} class="text-[#002F6C]" aria-hidden="true" />
              {/if}
            </span>

            {#if esAdmin}
              <div class="flex max-w-[460px] flex-col gap-2">
                <h2 class="m-0 text-[20px] font-semibold text-[#1A1A24]">
                  El docente titular aún no ha creado el syllabus
                </h2>
                <p class="m-0 text-[14px] text-pretty text-[#5A5E6E]">
                  {#if curso.docente_titular}
                    Titular: <strong class="font-semibold text-[#1A1A24]"
                      >{curso.docente_titular}</strong
                    >.
                  {/if}
                  Lo habitual es esperar; crear en su nombre es la excepción y queda registrado en
                  la auditoría del programa.
                </p>
              </div>
              {#if puedeCrear}
                <button
                  type="button"
                  onclick={() => irAlAsistente('COMPLETO')}
                  class={BTN_OUTLINE}
                >
                  <FilePlus size={16} class="text-[#5A5E6E]" aria-hidden="true" />
                  Crear en nombre del titular
                </button>
              {/if}
            {:else}
              <div class="flex max-w-[440px] flex-col gap-1.5">
                <h2 class="m-0 text-[20px] font-semibold text-[#1A1A24]">
                  Este curso aún no tiene syllabus
                </h2>
                <p class="m-0 text-[14px] text-pretty text-[#5A5E6E]">
                  Elige el tipo de programa. Podrás cambiarlo mientras esté en borrador.
                </p>
              </div>
              {#if puedeCrear}
                <div class="flex flex-wrap items-start justify-center gap-3.5 pt-1">
                  <div class="flex w-[260px] flex-col gap-2">
                    <button
                      type="button"
                      onclick={() => irAlAsistente('BASICO')}
                      class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#002F6C] bg-white px-4 py-2.5 text-[14px] font-semibold text-[#002F6C] transition-colors hover:bg-[#E8EDF5]"
                    >
                      <FileIcon size={16} aria-hidden="true" />
                      Crear syllabus BÁSICO
                    </button>
                    <span class="text-[12px] leading-[1.45] text-[#5A5E6E]">
                      5 secciones — para cursos electivos y talleres
                    </span>
                  </div>
                  <div class="flex w-[260px] flex-col gap-2">
                    <button
                      type="button"
                      onclick={() => irAlAsistente('COMPLETO')}
                      class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#002F6C] bg-[#002F6C] px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-[#1B4789]"
                    >
                      <FileText size={16} aria-hidden="true" />
                      Crear syllabus COMPLETO
                    </button>
                    <span class="text-[12px] leading-[1.45] text-[#5A5E6E]">
                      9 secciones — para asignaturas del plan de estudios
                    </span>
                  </div>
                </div>
              {:else}
                <p class="m-0 text-[13px] text-[#5A5E6E]">
                  No tienes permiso para crear el syllabus de este curso.
                </p>
              {/if}
            {/if}
          </div>
        {/if}
      </div>

      <!-- ── Admin: plazos de entrega del curso ── -->
      {#if esAdmin}
        <section
          class="flex flex-col gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,.08)] print:hidden"
        >
          <div class="flex flex-wrap items-center gap-3">
            <CalendarDays size={16} class="text-[#5A5E6E]" aria-hidden="true" />
            <h2 class="m-0 text-[14px] font-semibold text-[#1A1A24]">Fechas límite de entrega</h2>
            {#if !editandoFechas}
              <button
                type="button"
                onclick={() => (editandoFechas = true)}
                class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-transparent px-2.5 py-1.5 text-[13px] font-medium text-[#002F6C] transition-colors hover:bg-[#F5F1EA]"
              >
                <Pencil size={14} aria-hidden="true" />
                Editar
              </button>
            {/if}
          </div>

          {#if editandoFechas}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="flex flex-col gap-1.5">
                <label for="fecha-basico" class="text-[13px] font-medium text-[#1A1A24]">
                  Básico
                  <span class="font-normal text-[#5A5E6E]">— plazo del programa básico</span>
                </label>
                <DatePickerCL
                  id="fecha-basico"
                  value={fechaBasico}
                  onchange={(v) => (fechaBasico = v)}
                  disabled={guardandoFechas}
                />
              </div>
              <div class="flex flex-col gap-1.5">
                <label for="fecha-syllabus" class="text-[13px] font-medium text-[#1A1A24]">
                  Syllabus completo
                  <span class="font-normal text-[#5A5E6E]">— posterior al básico</span>
                </label>
                <DatePickerCL
                  id="fecha-syllabus"
                  value={fechaSyllabus}
                  minValue={fechaBasico}
                  onchange={(v) => (fechaSyllabus = v)}
                  disabled={guardandoFechas}
                />
              </div>
            </div>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                onclick={cancelarEdicionFechas}
                disabled={guardandoFechas}
                class={BTN_OUTLINE}
              >
                <X size={14} aria-hidden="true" />
                Cancelar
              </button>
              <button
                type="button"
                onclick={guardarFechas}
                disabled={guardandoFechas}
                class={BTN_PRIMARY}
              >
                <Save size={14} aria-hidden="true" />
                {guardandoFechas ? 'Guardando…' : 'Guardar fechas'}
              </button>
            </div>
          {:else}
            <dl class="m-0 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div class="rounded-lg border border-[#EDEFF3] bg-[#FCFBF9] px-3.5 py-2.5">
                <dt class="text-[11px] font-medium tracking-[0.04em] text-[#5A5E6E] uppercase">
                  Básico
                </dt>
                <dd
                  class="m-0 text-[13.5px] font-semibold {estaVencida(
                    curso.fecha_limite_entrega_basico,
                  ) && programa?.estado === 'BORRADOR'
                    ? 'text-[#B91C1C]'
                    : 'text-[#1A1A24]'}"
                >
                  {formatDate(curso.fecha_limite_entrega_basico)}
                </dd>
              </div>
              <div class="rounded-lg border border-[#EDEFF3] bg-[#FCFBF9] px-3.5 py-2.5">
                <dt class="text-[11px] font-medium tracking-[0.04em] text-[#5A5E6E] uppercase">
                  Syllabus completo
                </dt>
                <dd
                  class="m-0 text-[13.5px] font-semibold {estaVencida(
                    curso.fecha_limite_entrega_syllabus,
                  ) && programa?.estado === 'BASICO_COMPLETO'
                    ? 'text-[#B91C1C]'
                    : 'text-[#1A1A24]'}"
                >
                  {formatDate(curso.fecha_limite_entrega_syllabus)}
                </dd>
              </div>
            </dl>
          {/if}
        </section>
      {/if}
    </div>
  </div>

  <!-- ── Historial como slide-over bajo 1440px ── -->
  {#if historialAbierto}
    <div
      class="fixed inset-0 z-50 flex justify-end bg-[#1A1A24]/40 print:hidden"
      role="presentation"
      onclick={(e) => {
        if (e.target === e.currentTarget) historialAbierto = false;
      }}
    >
      <div
        class="flex h-full w-full max-w-[360px] flex-col gap-4 overflow-y-auto bg-white p-5 shadow-[0_0_40px_rgba(0,0,0,.2)]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="historial-titulo"
      >
        <div class="flex items-center gap-2">
          <Clock size={16} class="text-[#5A5E6E]" aria-hidden="true" />
          <h2 id="historial-titulo" class="m-0 text-[14px] font-semibold text-[#1A1A24]">
            Historial
          </h2>
          <button
            type="button"
            onclick={() => (historialAbierto = false)}
            class="ml-auto rounded-lg border border-transparent p-1.5 text-[#5A5E6E] transition-colors hover:bg-[#F5F1EA]"
            aria-label="Cerrar historial"
          >
            <X size={16} aria-hidden="true" />
          </button>
        </div>
        <SyllabusHistorial eventos={historial} plano />
      </div>
    </div>
  {/if}

  <SyllabusRechazoDialog
    abierto={rechazoAbierto}
    curso={rotuloCurso}
    revocando={sellado}
    enviando={accionEnCurso === 'rechazar'}
    onCancelar={() => (rechazoAbierto = false)}
    onConfirmar={rechazar}
  />
{/snippet}

{#if layoutType === 'ayudante'}
  <AyudanteLayout {breadcrumbs}>
    {@render pageContent()}
  </AyudanteLayout>
{:else if layoutType === 'estudiante'}
  <StudentLayout {breadcrumbs}>
    {@render pageContent()}
  </StudentLayout>
{:else if esAdmin}
  <AdminLayout {breadcrumbs}>
    {@render pageContent()}
  </AdminLayout>
{:else}
  <DocenteLayout>
    {@render pageContent()}
  </DocenteLayout>
{/if}
