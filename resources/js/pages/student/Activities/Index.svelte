<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import ActividadInteraccion from './ActividadInteraccion.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import { ChevronLeft } from 'lucide-svelte';
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
    entradas,
  }: Props = $props();

  let showAgendaModal = $state(false);
  function toggleAgendaModal() {
    showRubricaModal = false;
    showAgendaModal = !showAgendaModal;
  }

  let showRubricaModal = $state(false);
  function toggleRubricaModal() {
    showAgendaModal = false;
    showRubricaModal = !showRubricaModal;
  }

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
            id: 'nivel_1.escala_1',
            puntos: 0,
            criterio:
              'El texto es confuso; no se identifican ideas principales o hay graves errores de redacción.',
          },
          {
            id: 'nivel_1.escala_2',
            puntos: 10,
            criterio:
              'Las ideas son comprensibles en su mayoría, aunque falta fluidez o mejor estructura gramatical.',
          },
          {
            id: 'nivel_1.escala_3',
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
            id: 'nivel_2.escala_1',
            puntos: 0,
            criterio:
              'Argumentos basados únicamente en opiniones sin sustento teórico ni evidencia.',
          },
          {
            id: 'nivel_2.escala_2',
            puntos: 12,
            criterio:
              'Presenta argumentos válidos y cita algunas fuentes, aunque la conexión entre ellas es débil.',
          },
          {
            id: 'nivel_2.escala_3',
            puntos: 25,
            criterio:
              'Análisis profundo con múltiples fuentes integradas que sostienen una postura sólida y coherente.',
          },
        ],
      },
      {
        id: 'nivel_3',
        nombre: 'Uso de Normas APA',
        descripcion: 'Correcta citación y referenciación siguiendo el estándar APA 7ma edición.',
        nro_escalas: 3,
        puntaje_minimo: 0,
        puntaje_total: 15,
        escalas: [
          {
            id: 'nivel_3.escala_1',
            puntos: 0,
            criterio: 'No incluye referencias o no sigue ningún formato de citación reconocible.',
          },
          {
            id: 'nivel_3.escala_2',
            puntos: 7,
            criterio:
              'Aplica normas APA con errores menores (ej. falta de sangría francesa o errores en cursivas).',
          },
          {
            id: 'nivel_3.escala_3',
            puntos: 15,
            criterio: 'Citación y bibliografía perfectas según el manual APA 7.',
          },
        ],
      },
    ],
    detalles_evaluacion: {
      puntaje_total: 60,
      escala_evaluacion: [
        {
          puntaje_minimo: 0,
          evaluacion: 'Reprobado',
        },
        {
          puntaje_minimo: 36,
          evaluacion: 'Aprobación Mínima',
        },
        {
          puntaje_minimo: 54,
          evaluacion: 'Aprobación con Distinción',
        },
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
      emisor: 'Dr. Arancibia (Docente)',
      mensaje: 'Exactamente, Juan. Todo el documento debe seguir las normas APA 7. Saludos.',
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

<StudentLayout {breadcrumbs}>
  <div class="px-4 sm:px-6 md:px-10 lg:px-20 bg-white relative">
    <div
      class="w-full flex flex-col lg:flex-row items-start lg:items-center gap-4 sm:gap-6 lg:gap-20 mb-6"
    >
      <button
        class="flex items-center px-4 sm:px-6 py-1 sm:py-4 bg-primary text-secondary hover:text-primary hover:bg-secondary transition-colors border-b border-gray-200 rounded-3xl shrink-0"
        onclick={() => window.history.back()}
      >
        <ChevronLeft class="w-4 h-4 mr-2" />
        <p class="text-sm sm:text-base">Volver</p>
      </button>

      <p class="text-base sm:text-xl md:text-2xl font-semibold wrap-break-word leading-snug">
        Actividad: {cod_actividad}
        {nombre_actividad}
      </p>
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
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5v-5.25Z"
                />
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M13.5 2.25v4.875A1.875 1.875 0 0 0 15.375 9h4.125"
                />
              </svg>
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
  @keyframes slide-in {
    from {
      transform: translateY(0%);
      opacity: 0;
    }

    to {
      transform: translateY(100);
      opacity: 1;
    }
  }

  .animate-slide-in {
    animation: slide-in 0.25s ease-out;
  }
</style>

