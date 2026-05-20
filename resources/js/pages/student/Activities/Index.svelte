<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import Agenda from './Agenda/Agenda.svelte';
  import RubricaView from './Agenda/Rubrica.svelte';

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: 'Actividades', href: '/estudiante/actividades' },
  ];

  interface Props {
    cod_curso: string;
    nombre_curso: string;
    cod_actividad: string;
    nombre_actividad: string;
    descripcion: string;
    fecha_limite: string;
    es_sumativa: boolean;
    trae_archivo: boolean;
    entrega_obligatoria: boolean;
    ultima_nota?: number | null;
    ultimo_estado?: string | null;
    entradas: Array<{ id: number }>;
  }

  let {
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    descripcion,
    fecha_limite,
    es_sumativa,
    trae_archivo,
    entrega_obligatoria,
    ultima_nota,
    ultimo_estado,
    entradas: _entradas,
  }: Props = $props();

  let showAgendaModal = $state(false);
  let showRubricaModal = $state(false);

  function toggleAgendaModal() {
    showRubricaModal = false;
    showAgendaModal = !showAgendaModal;
  }
  function toggleRubricaModal() {
    showAgendaModal = false;
    showRubricaModal = !showRubricaModal;
  }

  function formatDateLong(d: string) {
    return new Date(d).toLocaleDateString('es-CL', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  }

  const stripTone = $derived.by((): 'pass' | 'fail' | 'pending' | 'submitted' => {
    if (ultima_nota !== null && ultima_nota !== undefined) {
      return ultima_nota >= 4.0 ? 'pass' : 'fail';
    }
    const t = ultimo_estado?.toLowerCase() ?? '';
    if (t.includes('entregad') || t.includes('completad')) return 'submitted';
    return 'pending';
  });

  const stateLabel = $derived.by(() => {
    if (stripTone === 'pass') return 'APROBADO';
    if (stripTone === 'fail') return 'REPROBADO';
    if (stripTone === 'submitted') return 'ENTREGADA';
    return 'PENDIENTE';
  });

  // Rubric example data — replace with real prop when backend provides it
  const rubricaEjemplo = {
    niveles: [
      {
        id: 'nivel_1',
        nombre: 'Claridad en la Comunicación',
        descripcion: 'Capacidad para transmitir ideas de forma estructurada y comprensible.',
        nro_escalas: 3,
        puntaje_minimo: 0,
        puntaje_total: 20,
        escalas: [
          {
            id: 'n1e1',
            puntos: 0,
            criterio:
              'El texto es confuso; no se identifican ideas principales o hay graves errores de redacción.',
          },
          {
            id: 'n1e2',
            puntos: 10,
            criterio:
              'Las ideas son comprensibles en su mayoría, aunque falta fluidez o mejor estructura gramatical.',
          },
          {
            id: 'n1e3',
            puntos: 20,
            criterio:
              'Comunicación excepcional; el texto es fluido, profesional y las ideas se transmiten sin ambigüedades.',
          },
        ],
      },
      {
        id: 'nivel_2',
        nombre: 'Argumentación y Evidencia',
        descripcion: 'Uso de fuentes y solidez lógica de los argumentos presentados.',
        nro_escalas: 3,
        puntaje_minimo: 0,
        puntaje_total: 25,
        escalas: [
          {
            id: 'n2e1',
            puntos: 0,
            criterio:
              'Argumentos basados únicamente en opiniones sin sustento teórico ni evidencia.',
          },
          {
            id: 'n2e2',
            puntos: 12,
            criterio:
              'Presenta argumentos válidos y cita algunas fuentes, aunque la conexión entre ellas es débil.',
          },
          {
            id: 'n2e3',
            puntos: 25,
            criterio:
              'Análisis profundo con múltiples fuentes integradas que sostienen una postura sólida y coherente.',
          },
        ],
      },
      {
        id: 'nivel_3',
        nombre: 'Uso de Normas APA',
        descripcion: 'Correcta citación y referenciación siguiendo el estándar APA 7ª edición.',
        nro_escalas: 3,
        puntaje_minimo: 0,
        puntaje_total: 15,
        escalas: [
          {
            id: 'n3e1',
            puntos: 0,
            criterio: 'No incluye referencias o no sigue ningún formato de citación reconocible.',
          },
          {
            id: 'n3e2',
            puntos: 7,
            criterio:
              'Aplica normas APA con errores menores (ej. falta de sangría francesa o errores en cursivas).',
          },
          {
            id: 'n3e3',
            puntos: 15,
            criterio: 'Citación y bibliografía perfectas según el manual APA 7.',
          },
        ],
      },
    ],
    detalles_evaluacion: {
      puntaje_total: 60,
      escala_evaluacion: [
        { puntaje_minimo: 0, evaluacion: 'Reprobado' },
        { puntaje_minimo: 36, evaluacion: 'Aprobación Mínima' },
        { puntaje_minimo: 54, evaluacion: 'Aprobación con Distinción' },
      ],
    },
  };

  // Arreglo limpio sin elementos duplicados y con puntaje agregado
  const interaccionesEjemplo = [
    {
      id_interaccion: 1,
      fecha_emision: '2026-05-10 09:00',
      tipo_interaccion: 'Consulta',
      emisor: 'Juan Pérez (Estudiante)',
      mensaje:
        'Hola profesor, tengo una duda sobre el formato de la bibliografía. ¿Debe ser APA 7ma edición?',
      es_de_docente: false,
      es_retroalimentacion: false,
      adjunta_rubrica: false,
    },
    {
      id_interaccion: 2,
      fecha_emision: '2026-05-10 14:30',
      tipo_interaccion: 'Respuesta',
      emisor: 'Docente',
      mensaje: 'Exactamente. Todo el documento debe seguir las normas APA 7.',
      es_de_docente: true,
      es_retroalimentacion: false,
      adjunta_rubrica: false,
    },
    {
      id_interaccion: 3,
      fecha_emision: '2026-05-11 10:15',
      tipo_interaccion: 'Retroalimentación',
      emisor: 'Sistema de Evaluación',
      mensaje:
        'Se ha revisado tu avance. El marco teórico está bien planteado, pero falta profundizar en la metodología.',
      es_de_docente: true,
      es_retroalimentacion: true,
      adjunta_rubrica: true,
      rubrica: rubricaEjemplo,
      puntaje_obtenido: 30,
    },
  ];
  const rubrica = rubricaEjemplo;
</script>

<svelte:head>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=JetBrains+Mono:wght@400;500;700&display=swap"
    rel="stylesheet"
  />
</svelte:head>

      <h2 class="text-base sm:text-xl md:text-2xl font-semibold wrap-break-word leading-snug">
        {nombre_curso}: {cod_actividad}
        {nombre_actividad}
      </h2>
    </div>

    <div class="w-full grid grid-cols-1 xl:grid-cols-2 gap-6 lg:gap-8 items-start">
      <!--Celda izquierda-->
      <div
        class="flex flex-col sm:min-h-110 w-full justify-start gap-6 lg:rounded-3xl lg:mt-3 lg:px-10 lg:py-5"
      >
        <p class="text-start text-sm sm:text-base font-semibold">Sobre esta Actividad</p>

        <!--Cuadrado descripción-->
        <div
          class="text-sm font-semibold text-primary px-4 sm:px-6 md:px-8 py-4 sm:h-50 rounded-3xl bg-secondary border-2 break-words"
        >
          Descripción:
          <br />
          {descripcion}
          <br class="mb-4" />
          Fecha límite: {fecha_limite}
          <br />
          Tipo Actividad: {es_sumativa ? 'Sumativa' : 'Formativa'}
          <br />
          Entrega Obligatoria: {trae_archivo ? 'Sí' : 'No'}
        </div>

        <!-- GRID BOTONES -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
          {#if trae_archivo}
            <button
              class="w-full px-4 sm:px-6 md:px-8 py-3 sm:py-4 rounded-lg border transition-all bg-primary text-secondary hover:bg-secondary hover:text-primary flex items-center justify-between gap-4 text-sm sm:text-lg lg:text-xl font-semibold"
            >
              <p>Ver Enunciado</p>

              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-6"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                />
              </svg>
            </button>
          {/if}

          <!-- BOTON RUBRICA -->
          {#if es_sumativa}
            <button
              class="w-full px-4 sm:px-6 md:px-8 py-3 sm:py-4 rounded-lg border transition-all bg-primary text-secondary hover:bg-secondary hover:text-primary flex items-center justify-between gap-4 text-sm sm:text-lg lg:text-xl font-semibold sm:col-span-1"
              onclick={toggleRubricaModal}
            >
              <p>Ver Rúbrica</p>

              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-5 sm:size-6 shrink-0"
              >
            </div>
            <div class="sec-action-text">
              <div class="sec-action-title">Ver Agenda del Curso</div>
              <div class="sec-action-sub">Calendario de hitos, consultas y retroalimentación</div>
            </div>
            <svg
              width="16"
              height="16"
              class="sec-action-arrow"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              ><line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" /></svg
            >
          </button>
          <button class="sec-action">
            <div class="sec-action-icon">
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
                ><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path
                  d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"
                /></svg
              >
            </div>
            <div class="sec-action-text">
              <div class="sec-action-title">Bibliografía Sugerida</div>
              <div class="sec-action-sub">Material de apoyo relacionado con esta actividad</div>
            </div>
            <svg
              width="16"
              height="16"
              class="sec-action-arrow"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              ><line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" /></svg
            >
          </button>
        </div>

        <!-- Submission / upload area -->
        {#if stripTone === 'pass' || stripTone === 'fail' || stripTone === 'submitted'}
          <div class="upload-card locked">
            <div class="upload-icon">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
              >
            </div>
            <div class="upload-text">
              <h3 class="upload-title">Tu entrega</h3>
              <p class="upload-sub">
                {#if stripTone === 'submitted' || stripTone === 'pass' || stripTone === 'fail'}
                  Tu trabajo ha sido entregado.
                  {#if stripTone === 'pass' || stripTone === 'fail'}
                    Como ya fue calificada, no es posible volver a subir un archivo.
                  {/if}
                {/if}
              </p>
            </div>
            <button class="upload-btn" disabled>
              <svg
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
                ><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline
                  points="7 10 12 15 17 10"
                /><line x1="12" y1="15" x2="12" y2="3" /></svg
              >
              Descargar entrega
            </button>
          {/if}
        </div>
      </div>

      <!--Celda derecha-->
      <div class="w-full grid grid-cols-4 gap-4 items-start justify-start">
        <!-- Contenedor principal -->
        <div
          class="col-span-4 w-full rounded-3xl bg-secondary border-2 p-4 sm:p-6 lg:p-8 mb-6 sm:mb-10"
        >
          <p class="w-full text-sm font-semibold text-primary mb-4">
            Estado: {ultimo_estado?.toLocaleUpperCase()}
          </p>

          <!-- Grid botones -->
          <div class="grid grid-cols-2 gap-4">
            <!-- BOTON AGENDA -->
            <button
              class="w-full px-4 sm:px-6 md:px-8 py-3 sm:py-4 rounded-lg border transition-all bg-primary text-secondary hover:bg-secondary hover:text-primary flex items-center justify-between gap-4 text-sm sm:text-lg lg:text-xl font-semibold"
              onclick={toggleAgendaModal}
            >
              <p>Ver Agenda</p>

              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-5 sm:size-6 shrink-0"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"
                />
              </svg>
            </button>

            <!-- BOTON ENVÍO -->
            <button
              class="w-full px-4 sm:px-6 md:px-8 py-3 sm:py-4 rounded-lg border transition-all bg-primary text-secondary hover:bg-secondary hover:text-primary flex items-center justify-between gap-4 text-sm sm:text-lg lg:text-xl font-semibold"
              onclick={toggleAgendaModal}
            >
              <p>Agregar Entrega</p>

              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-5 sm:size-6 shrink-0"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"
                />
              </svg>
            </button>

            
          </div>
          <div class="col-span-4">
              <ActividadInteraccion {es_sumativa} {ultima_nota} {ultimo_estado} />
            </div>
        </div>
      </div>
    </div>

    {#if showAgendaModal}
      <div
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/50 transition-opacity overflow-y-auto"
      >
        <div class="w-full max-w-7xl">
          <Agenda
            onCerrar={toggleAgendaModal}
            onInteraccionEnviada={(data: any) => console.log(data)}
            {cod_curso}
            {nombre_curso}
            {cod_actividad}
            {nombre_actividad}
            listado_interacciones={interaccionesEjemplo}
          />
        </div>
      </div>
    {/if}

    {#if showRubricaModal}
      <div
        class="fixed inset-0 z-50 sm:relative sm:inset-auto w-full border-l bg-gray-50 h-full overflow-y-auto p-6 animate-slide-in"
      >
        <div class="flex flex-col gap-4 w-full max-w-7xl bg-white rounded-4xl">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-sm text-primary">Rubrica Asociada</h2>
            <button
              class="p-2 hover:bg-gray-200 rounded-full transition-colors flex items-center gap-2 group"
              onclick={toggleRubricaModal}
              aria-label="cerrar"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-6"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <RubricaView {rubrica} modoLectura={true} />
        </div>
      </div>
    {/if}
  </div></StudentLayout
>

<svelte:window onkeydown={(e) => e.key === 'Escape' && (showAgendaModal = false)} />

<style>
  .pg {
    --bg: #ffffff;
    --surface: #ffffff;
    --surface-2: #f4f6fa;
    --surface-3: #eeeff3;
    --primary: #1b3158;
    --primary-2: #2a4576;
    --primary-3: #4865a1;
    --primary-soft: #e8edf5;
    --primary-tint: #f1f4fa;
    --accent: #7a1f2b;
    --accent-2: #9d2937;
    --accent-soft: #f4e6e8;
    --accent-tint: #faeef0;
    --gold: #b58a3c;
    --gold-soft: #f5ebd2;
    --gold-ink: #6e5217;
    --ink-1: #11141b;
    --ink-2: #2c3142;
    --ink-3: #5a6075;
    --ink-4: #8a8e9c;
    --ink-5: #b8bcc8;
    --line: #e2dbc8;
    --line-2: #cfc6ab;
    --success: #2c7a4b;
    --success-soft: #e2f0e7;
    --warning-soft: #fbefd3;
    --danger: #993244;
    --danger-soft: #f5e1e4;
    --info: #1f5ba8;
    --info-soft: #e4ecf8;
    --font-sans: 'Inter Tight', system-ui, sans-serif;
    --font-display: 'Fraunces', 'Inter Tight', Georgia, serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;
    --radius: 10px;
    --radius-lg: 14px;
    --radius-xl: 18px;
    --shadow: 0 2px 6px rgba(20, 30, 55, 0.05), 0 8px 24px rgba(20, 30, 55, 0.06);
    --shadow-lg: 0 12px 40px rgba(20, 30, 55, 0.18), 0 4px 12px rgba(20, 30, 55, 0.08);

    font-family: var(--font-sans);
    background: var(--bg);
    min-height: 100vh;
    color: var(--ink-1);
    -webkit-font-smoothing: antialiased;
  }

  /* Heritage stripe */
  .heritage-stripe {
    display: flex;
    height: 4px;
    width: 100%;
  }
  .h-navy {
    flex: 6;
    background: var(--primary);
  }
  .h-wine {
    flex: 1.6;
    background: var(--accent);
  }
  .h-gold {
    flex: 1.2;
    background: var(--gold);
  }
  .h-navy2 {
    flex: 1;
    background: var(--primary);
  }

  /* Shell */
  .shell {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 28px 80px;
    display: grid;
    grid-template-columns: 264px minmax(0, 1fr);
    gap: 36px;
  }

  /* Sidebar */
  .sidebar {
    padding-top: 28px;
    position: sticky;
    top: 0;
    align-self: start;
    max-height: 100vh;
    overflow-y: auto;
  }

  :global(.side-back) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--ink-3);
    padding: 6px 10px 6px 4px;
    border-radius: 6px;
    margin-bottom: 18px;
    transition: color 0.15s;
    text-decoration: none;
    font-family: var(--font-sans);
  }
  :global(.side-back:hover) {
    color: var(--primary);
  }

  :global(.side-action-link) {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--surface);
    border: 1px solid var(--line);
    font-size: 13px;
    color: var(--ink-2);
    font-weight: 500;
    transition: all 0.15s;
    text-decoration: none;
    font-family: var(--font-sans);
  }
  :global(.side-action-link:hover) {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-tint);
  }
  :global(.side-action-link span) {
    flex: 1;
  }

  .side-course {
    border-left: 3px solid var(--accent);
    padding: 4px 0 4px 14px;
    margin-bottom: 24px;
  }
  .side-course-eyebrow {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .side-course-name {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 4px 0 0;
    color: var(--ink-1);
    line-height: 1.1;
  }

  .side-divider {
    height: 1px;
    background: var(--line);
    margin: 18px 0;
  }

  .side-action {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--surface);
    border: 1px solid var(--line);
    font-size: 13px;
    color: var(--ink-2);
    font-weight: 500;
    transition: all 0.15s;
    text-align: left;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .side-action span {
    flex: 1;
  }
  .side-action:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-tint);
  }
  .side-action-arrow {
    margin-left: auto;
  }

  /* Main */
  .main-col {
    padding-top: 28px;
  }

  .detail-head {
    margin-bottom: 24px;
  }

  .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--ink-2);
    background: var(--surface);
    border: 1px solid var(--line);
    transition: all 0.15s;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .back-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
  }

  /* Hero card */
  .detail-hero {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-xl);
    padding: 32px 36px 0;
    margin-bottom: 18px;
    position: relative;
    overflow: hidden;
  }
  .detail-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(
      90deg,
      var(--primary) 0% 70%,
      var(--accent) 70% 88%,
      var(--gold) 88% 100%
    );
  }

  .detail-eyebrow {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--ink-3);
    font-weight: 600;
    margin-bottom: 12px;
  }
  .detail-eyebrow-code {
    font-family: var(--font-mono);
    font-size: 11px;
    letter-spacing: 0.04em;
    padding: 3px 8px;
    background: var(--primary);
    color: #fff;
    border-radius: 4px;
  }
  .detail-eyebrow-sep {
    color: var(--ink-5);
  }

  .detail-title {
    font-family: var(--font-display);
    font-size: 42px;
    font-weight: 600;
    letter-spacing: -0.018em;
    line-height: 1.04;
    margin: 0 0 18px;
    color: var(--ink-1);
    max-width: 760px;
  }

  .detail-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 22px;
  }

  .act-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 500;
    padding: 4px 9px;
    border-radius: 999px;
    color: var(--ink-2);
    background: var(--surface-3);
    border: 1px solid var(--line);
  }
  .act-tag.sumativa {
    background: var(--accent-tint);
    color: var(--accent);
    border-color: var(--accent-soft);
  }
  .act-tag.formativa {
    background: var(--primary-tint);
    color: var(--primary);
    border-color: var(--primary-soft);
  }
  .act-tag.obligatoria {
    background: var(--gold-soft);
    color: var(--gold-ink);
    border-color: #ead79a;
  }
  .act-tag-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
  }

  .detail-desc-label {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
  }
  .detail-desc {
    font-size: 15px;
    line-height: 1.65;
    color: var(--ink-2);
    max-width: 720px;
    margin: 0 0 24px;
  }

  .detail-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    border-top: 1px solid var(--line);
    margin: 0 -36px;
    padding: 0;
  }
  .detail-meta-item {
    flex: 1;
    min-width: 140px;
    padding: 18px 24px;
    border-right: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .detail-meta-item:last-child {
    border-right: 0;
  }
  .detail-meta-key {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .detail-meta-val {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-1);
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Grade strip */
  .grade-strip {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 28px;
    padding: 22px 28px;
    border-radius: var(--radius-lg);
    margin-bottom: 18px;
    border: 1px solid;
  }
  .grade-strip.fail {
    background: linear-gradient(90deg, var(--danger-soft) 0%, var(--surface-2) 100%);
    border-color: #e9c5cb;
  }
  .grade-strip.pass {
    background: linear-gradient(90deg, var(--success-soft) 0%, var(--surface-2) 100%);
    border-color: #bfe0c9;
  }
  .grade-strip.pending {
    background: linear-gradient(90deg, var(--warning-soft) 0%, var(--surface-2) 100%);
    border-color: #e4d2a2;
  }
  .grade-strip.submitted {
    background: linear-gradient(90deg, var(--info-soft) 0%, var(--surface-2) 100%);
    border-color: #b8d0f0;
  }

  .grade-state {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .grade-state-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid currentColor;
  }
  .grade-strip.fail .grade-state-icon {
    color: var(--danger);
  }
  .grade-strip.pass .grade-state-icon {
    color: var(--success);
  }
  .grade-strip.pending .grade-state-icon {
    color: var(--gold-ink);
  }
  .grade-strip.submitted .grade-state-icon {
    color: var(--info);
  }

  .grade-state-text {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
  }
  .grade-state-eyebrow {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .grade-state-label {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.01em;
  }
  .grade-strip.fail .grade-state-label {
    color: var(--danger);
  }
  .grade-strip.pass .grade-state-label {
    color: var(--success);
  }
  .grade-strip.pending .grade-state-label {
    color: var(--gold-ink);
  }
  .grade-strip.submitted .grade-state-label {
    color: var(--info);
  }

  .grade-detail {
    display: flex;
    align-items: center;
    gap: 14px 18px;
    flex-wrap: wrap;
    min-width: 0;
    font-size: 12.5px;
    color: var(--ink-3);
  }
  .grade-detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .grade-detail-key {
    font-size: 10.5px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
  }
  .grade-detail-val {
    font-size: 13px;
    color: var(--ink-2);
    font-weight: 500;
  }
  .grade-detail-sep {
    width: 1px;
    height: 28px;
    background: currentColor;
    opacity: 0.2;
  }

  .grade-number {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: 1;
    gap: 4px;
  }
  .grade-number-big {
    font-family: var(--font-display);
    font-size: 56px;
    font-weight: 600;
    letter-spacing: -0.03em;
    line-height: 0.9;
  }
  .grade-strip.fail .grade-number-big {
    color: var(--danger);
  }
  .grade-strip.pass .grade-number-big {
    color: var(--success);
  }
  .grade-number-dash {
    font-family: var(--font-display);
    font-size: 40px;
    font-weight: 600;
    color: var(--ink-4);
  }
  .grade-number-scale {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ink-4);
    letter-spacing: 0.04em;
  }
  .grade-number-link {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--primary);
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    font-family: var(--font-sans);
  }
  .grade-number-link:hover {
    text-decoration: underline;
  }

  /* Primary actions */
  .primary-actions {
    display: grid;
    grid-template-columns: 1.7fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
  }

  .action-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
    padding: 24px 26px;
    display: flex;
    align-items: center;
    gap: 20px;
    text-align: left;
    width: 100%;
    transition: all 0.15s;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .action-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
    transform: translateY(-1px);
  }

  .action-card.primary {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    padding: 28px 30px;
  }
  .action-card.primary:hover {
    background: var(--primary-2);
    border-color: var(--primary-2);
  }
  .action-card.primary::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 180px;
    height: 100%;
    background: radial-gradient(circle at 80% 50%, rgba(255, 255, 255, 0.06) 0%, transparent 60%);
    pointer-events: none;
  }

  .action-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    background: var(--primary-tint);
    color: var(--primary);
    display: grid;
    place-items: center;
    flex-shrink: 0;
  }
  .action-card.primary .action-icon {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  .action-card.secondary .action-icon {
    background: var(--accent-tint);
    color: var(--accent);
  }

  .action-text {
    flex: 1;
    min-width: 0;
  }
  .action-label {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 600;
    margin-bottom: 4px;
  }
  .action-card.primary .action-label {
    color: rgba(255, 255, 255, 0.65);
  }
  .action-title {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 600;
    letter-spacing: -0.012em;
    line-height: 1.1;
    margin: 0 0 6px;
  }
  .action-sub {
    font-size: 13px;
    color: var(--ink-3);
    line-height: 1.4;
  }
  .action-card.primary .action-sub {
    color: rgba(255, 255, 255, 0.7);
  }
  .action-file-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--ink-4);
    margin-top: 8px;
  }
  .action-card.primary .action-file-meta {
    color: rgba(255, 255, 255, 0.6);
  }
  .action-file-meta-chip {
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.85);
  }

  .action-arrow {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: var(--surface-3);
    color: var(--primary);
    flex-shrink: 0;
    transition: transform 0.15s;
  }
  .action-card:hover .action-arrow {
    transform: translateX(4px);
  }
  .action-card.primary .action-arrow {
    background: var(--gold);
    color: var(--primary);
  }
  .action-card.secondary .action-title {
    font-size: 19px;
  }

  /* Secondary actions */
  .secondary-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
  }
  .sec-action {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    text-align: left;
    width: 100%;
    transition: all 0.15s;
    cursor: pointer;
    font-family: var(--font-sans);
  }
  .sec-action:hover {
    border-color: var(--accent);
    background: var(--accent-tint);
  }
  .sec-action:hover .sec-action-icon {
    background: var(--accent);
    color: #fff;
  }
  .sec-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: var(--accent-tint);
    color: var(--accent);
    display: grid;
    place-items: center;
    flex-shrink: 0;
    transition: all 0.15s;
  }
  .sec-action-text {
    flex: 1;
    min-width: 0;
  }
  .sec-action-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-1);
    line-height: 1.2;
    margin-bottom: 2px;
  }
  .sec-action-sub {
    font-size: 12px;
    color: var(--ink-3);
  }
  .sec-action-arrow {
    color: var(--ink-4);
  }

  /* Upload card */
  .upload-card {
    background: var(--surface);
    border: 1px dashed var(--line-2);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 18px;
  }
  .upload-card.locked {
    border-style: solid;
    background: var(--surface-2);
  }
  .upload-icon {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    background: var(--surface-3);
    color: var(--ink-4);
    display: grid;
    place-items: center;
    flex-shrink: 0;
  }
  .upload-icon-active {
    background: var(--info-soft) !important;
    color: var(--info) !important;
  }
  .upload-text {
    flex: 1;
  }
  .upload-title {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 4px;
    color: var(--ink-1);
  }
  .upload-sub {
    font-size: 13px;
    color: var(--ink-3);
    line-height: 1.45;
  }
  .upload-sub strong {
    color: var(--ink-2);
    font-weight: 600;
  }
  .upload-btn {
    padding: 10px 18px;
    border-radius: 8px;
    background: var(--accent);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.15s;
    border: none;
    cursor: pointer;
    font-family: var(--font-sans);
    white-space: nowrap;
  }
  .upload-btn:hover {
    background: var(--accent-2);
  }
  .upload-btn:disabled {
    background: var(--ink-5);
    cursor: not-allowed;
  }

  /* Modal overlay */
  .modal-overlay {
    /* CSS variables for modal scope — modals render outside .pg so variables must be redefined here */
    --bg: #FFFFFF;
    --surface: #FFFFFF;
    --surface-2: #F4F6FA;
    --surface-3: #EEEFF3;
    --primary: #1B3158;
    --primary-2: #2A4576;
    --primary-soft: #E8EDF5;
    --primary-tint: #F1F4FA;
    --accent: #7A1F2B;
    --accent-2: #9D2937;
    --accent-soft: #F4E6E8;
    --accent-tint: #FAEEF0;
    --gold: #B58A3C;
    --gold-soft: #F5EBD2;
    --gold-ink: #6E5217;
    --ink-1: #11141B;
    --ink-2: #2C3142;
    --ink-3: #5A6075;
    --ink-4: #8A8E9C;
    --ink-5: #B8BCC8;
    --line: #E2DBC8;
    --line-2: #CFC6AB;
    --success: #2C7A4B;
    --success-soft: #E2F0E7;
    --warning-soft: #FBEFD3;
    --danger: #993244;
    --danger-soft: #F5E1E4;
    --info: #1F5BA8;
    --info-soft: #E4ECF8;
    --font-sans: 'Inter Tight', system-ui, sans-serif;
    --font-display: 'Fraunces', 'Inter Tight', Georgia, serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;
    --radius: 10px;
    --radius-lg: 14px;
    --radius-xl: 18px;
    --shadow-lg: 0 12px 40px rgba(20,30,55,.18), 0 4px 12px rgba(20,30,55,.08);

    position: fixed;
    inset: 0;
    background: rgba(17, 20, 27, 0.55);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 100;
    padding: 28px;
    animation: ov-fade 0.15s ease-out;
  }
  @keyframes ov-fade {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  .modal-agenda {
    width: 100%;
    max-width: 1280px;
  }
</style>
