<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import ActividadInteraccion from './ActividadInteraccion.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import { ChevronLeft } from 'lucide-svelte';
  import Agenda from './Agenda/Agenda.svelte';

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

  function toggleModal() {
    showAgendaModal = !showAgendaModal;
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
      mensaje: 'Hola profesor, tengo una duda sobre el formato de la bibliografía. ¿Debe ser APA 7ma edición?',
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
      mensaje: 'Se ha revisado tu avance. El marco teórico está bien planteado, pero falta profundizar en la metodología.',
      es_de_docente: true,
      es_retroalimentacion: true,
      adjunta_rubrica: true,
      rubrica: rubricaEjemplo,
      puntaje_obtenido: 30
    }
  ];
</script>

<StudentLayout {breadcrumbs}>
  <div class="px-5 md:px-10 lg:px-20 bg-white relative">
    <div
      class="w-full flex flex-col sm:flex-row items-start sm:items-center gap-6 sm:gap-10 md:gap-20 mb-6"
    >
      <button
        class="flex items-center px-6 py-4 bg-primary text-secondary hover:text-primary hover:bg-secondary transition-colors border-b border-gray-200 rounded-3xl"
        onclick={() => window.history.back()}
      >
        <ChevronLeft class="w-4 h-4 mr-2" />
        <p>Volver</p>
      </button>
      <h2 class="text-xl font-semibold">{nombre_curso}: {cod_actividad} {nombre_actividad}</h2>
    </div>

    <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
      <!--Celda izquierda-->
      <div
        class="flex flex-col w-full justify-center gap-6 sm:rounded-3xl sm:border-2 sm:px-10 sm:py-5"
      >
        <p class="text-start">Sobre esta Actividad</p>
        <!--Cuadrado descripción-->
        <div class="text-sm font-semibold text-primary px-8 py-4 rounded-3xl bg-secondary border-2">
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
        {#if trae_archivo}
          <button
            class="sm:mx-auto w-full lg:w-[50%] px-8 py-4 rounded-lg border transition-all bg-primary text-secondary hover:bg-secondary hover:text-primary flex justify-between gap-4"
          >
            <p class="text-xl font-semibold">Ver Archivo</p>
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
                d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"
              />
            </svg>
          </button>
        {/if}

        <!-- BOTON AGENDA -->
        <button
          class="mx-auto w-full lg:w-[50%] px-8 py-4 rounded-lg border transition-all bg-primary text-secondary hover:bg-secondary hover:text-primary flex justify-between gap-4"
          onclick={toggleModal}
        >
          <p class="text-xl font-semibold">Ver Agenda</p>
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
              d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"
            />
          </svg>
        </button>
      </div>
      <!--Celda derecha-->
      <div class="flex flex-col w-full justify-center items-start">
        <!--Cuadrado estado-->
        <p
          class="sm:mx-auto sm:w-[50%] flex flex-col sm:flex-row justify-between text-sm font-semibold"
        >
          Estado
        </p>
        <div
          class="sm:mx-auto sm:w-[50%] flex flex-col sm:flex-row justify-between text-sm font-semibold text-primary lg:px-8 py-4 rounded-3xl bg-secondary border-2 mb-10"
        >
          <p class="mx-auto">{ultimo_estado?.toLocaleUpperCase()}</p>
        </div>
        <!-- CUADRADO DE NOTA -->
        <ActividadInteraccion {es_sumativa} {ultima_nota} {ultimo_estado} />
      </div>
    </div>
  </div>

  {#if showAgendaModal}
    <div
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 transition-opacity"
    >
      <Agenda
        onCerrar={toggleModal}
        onInteraccionEnviada={(data: any) => console.log(data)}
        {cod_curso}
        {nombre_curso}
        {cod_actividad}
        {nombre_actividad}
        listado_interacciones={interaccionesEjemplo}
      />
    </div>
  {/if}
</StudentLayout>
<svelte:window onkeydown={(e) => e.key === 'Escape' && (showAgendaModal = false)} />
