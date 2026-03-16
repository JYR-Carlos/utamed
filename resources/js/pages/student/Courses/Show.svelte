<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { ArrowLeft, PlayCircle, FileText, Bookmark, Share2, ScrollText } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';
  import CourseSidebar from '@/components/student/CourseSidebar.svelte';
  import ResourceCard from '@/components/student/ResourceCard.svelte';

  interface Props {
    curso?: {
      id_curso?: number;
      nombre?: string;
    };
  }

  let { curso }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  let activeModuleId = $state('module-1-2');

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: 'Contenido', href: `/estudiante/cursos/${id_curso}` },
  ]);

  // Estructura del curso
  const courseUnits = [
    {
      id: 'unit-1',
      title: 'Unidad 1: Fundamentos de UX',
      modules: [
        { id: 'module-1-1', title: '1.1 Heurísticas de Usabilidad', duration: '12 min' },
        { id: 'module-1-2', title: '1.2 Arquitectura de Información', duration: '15 min' },
        { id: 'module-1-3', title: '1.3 Principios de Diseño', duration: '18 min' },
      ],
    },
    {
      id: 'unit-2',
      title: 'Unidad 2: Research y Análisis',
      modules: [
        { id: 'module-2-1', title: '2.1 Métodos de Investigación', duration: '20 min' },
        { id: 'module-2-2', title: '2.2 User Personas', duration: '14 min' },
        { id: 'module-2-3', title: '2.3 Journey Maps', duration: '16 min' },
      ],
    },
    {
      id: 'unit-3',
      title: 'Unidad 3: Wireframing y Prototipos',
      modules: [
        { id: 'module-3-1', title: '3.1 Wireframes de Baja Fidelidad', duration: '10 min' },
        { id: 'module-3-2', title: '3.2 Herramientas de Prototipado', duration: '22 min' },
      ],
    },
  ];

  // Recursos de la unidad actual
  const resources = [
    {
      type: 'pdf' as const,
      title: 'Presentación: Arquitectura de Información',
      fileSize: '2.4 MB',
    },
    { type: 'figma' as const, title: 'Plantilla de Sitemap Interactivo', fileSize: '1.8 MB' },
    {
      type: 'reading' as const,
      title: 'Lectura Complementaria: Information Architecture 4th Ed.',
      fileSize: 'PDF · 856 KB',
    },
  ];

  function handleModuleClick(moduleId: string) {
    activeModuleId = moduleId;
  }
</script>

<StudentLayout {breadcrumbs}>
  <div class="min-h-screen bg-white flex">
    <!-- Sidebar -->
    <aside class="w-80 flex-shrink-0 sticky top-0 self-start">
      <CourseSidebar
        units={courseUnits}
        {activeModuleId}
        onModuleClick={handleModuleClick}
        courseName={curso?.nombre ?? 'Diseño de Interfaces Digitales'}
      />
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto">
      <!-- Top Navigation -->
      <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-sm border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-12 py-4">
          <div class="flex items-center justify-between">
            <Link
              href="/estudiante/dashboard"
              class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-indigo-600 transition-colors"
            >
              <ArrowLeft class="w-4 h-4" />
              Volver al Dashboard
            </Link>

            <div class="flex items-center gap-3">
              <Link
                href={`/estudiante/cursos/${id_curso}/programa`}
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors text-sm font-medium"
              >
                <ScrollText class="w-4 h-4" />
                Syllabus
              </Link>
              <button
                class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 hover:text-indigo-600 transition-colors"
              >
                <Bookmark class="w-5 h-5" />
              </button>
              <button
                class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 hover:text-indigo-600 transition-colors"
              >
                <Share2 class="w-5 h-5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Container -->
      <div class="max-w-4xl mx-auto px-12 py-12">
        <!-- Content Header -->
        <div class="mb-10">
          <div class="flex items-center gap-3 mb-4">
            <div
              class="px-3 py-1 rounded-lg bg-indigo-100 text-indigo-700 border border-indigo-200 inline-flex items-center gap-1.5 text-xs font-semibold"
            >
              <PlayCircle class="w-3.5 h-3.5" />
              Video · 15 min
            </div>
            <div
              class="px-3 py-1 rounded-lg border border-gray-300 text-gray-600 text-xs font-semibold"
            >
              Unidad 1
            </div>
          </div>

          <h1 class="text-5xl font-extrabold text-gray-900 mb-4 leading-tight">
            1.2 Arquitectura de Información
          </h1>

          <p class="text-xl text-gray-600 leading-relaxed">
            Aprende a organizar y estructurar el contenido de productos digitales de manera lógica e
            intuitiva para mejorar la experiencia del usuario.
          </p>
        </div>

        <!-- Video Player Placeholder -->
        <div class="mb-12">
          <div
            class="relative aspect-video rounded-2xl bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 border border-gray-200 overflow-hidden group cursor-pointer hover:shadow-xl transition-shadow"
          >
            <!-- Play Button Overlay -->
            <div class="absolute inset-0 flex items-center justify-center">
              <div
                class="w-20 h-20 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform"
              >
                <PlayCircle class="w-12 h-12 text-indigo-600" />
              </div>
            </div>

            <!-- Decorative Elements -->
            <div
              class="absolute top-8 left-8 w-32 h-32 rounded-full bg-indigo-200/30 blur-2xl"
            ></div>
            <div
              class="absolute bottom-8 right-8 w-40 h-40 rounded-full bg-purple-200/30 blur-3xl"
            ></div>

            <!-- Duration Badge -->
            <div
              class="absolute bottom-4 right-4 px-3 py-1.5 rounded-lg bg-gray-900/80 backdrop-blur-sm"
            >
              <span class="text-sm font-medium text-white">15:24</span>
            </div>
          </div>
        </div>

        <!-- Rich Text Content -->
        <article class="space-y-8">
          <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-6">
              ¿Qué es la Arquitectura de Información?
            </h2>

            <p class="text-gray-700 leading-relaxed mb-6">
              La Arquitectura de Información (AI) es la práctica de decidir cómo organizar las
              partes de algo para que sea comprensible. En el contexto del diseño digital, se
              refiere a la estructuración y organización del contenido en productos digitales como
              sitios web, aplicaciones móviles y software.
            </p>

            <p class="text-gray-700 leading-relaxed mb-6">
              Una buena arquitectura de información ayuda a los usuarios a encontrar lo que buscan
              de manera eficiente y cumplir sus objetivos sin frustraciones. Es la columna vertebral
              invisible que sostiene toda la experiencia de usuario.
            </p>
          </div>

          <!-- Blockquote -->
          <blockquote class="border-l-4 border-indigo-600 bg-indigo-50 pl-6 pr-6 py-4 rounded-r-xl">
            <p class="text-gray-800 italic text-lg mb-2">
              "El objetivo de la arquitectura de información es ayudar a los usuarios a encontrar
              información y completar tareas."
            </p>
            <footer class="text-sm font-semibold text-indigo-600">
              — Lou Rosenfeld & Peter Morville, Information Architecture for the Web
            </footer>
          </blockquote>

          <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Componentes Principales</h2>

            <p class="text-gray-700 leading-relaxed mb-6">
              La arquitectura de información se compone de cuatro sistemas principales que trabajan
              en conjunto:
            </p>

            <!-- Custom List -->
            <div class="space-y-4">
              <div class="flex gap-4">
                <div
                  class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600 mt-1"
                >
                  1
                </div>
                <div>
                  <h4 class="font-bold text-gray-900 mb-1">Sistemas de Organización</h4>
                  <p class="text-gray-700">
                    Definen cómo categorizamos y estructuramos la información (cronológico,
                    alfabético, por tema, etc.).
                  </p>
                </div>
              </div>

              <div class="flex gap-4">
                <div
                  class="flex-shrink-0 w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-sm font-bold text-purple-600 mt-1"
                >
                  2
                </div>
                <div>
                  <h4 class="font-bold text-gray-900 mb-1">Sistemas de Etiquetado</h4>
                  <p class="text-gray-700">
                    Determinan cómo representamos la información a través de lenguaje y términos
                    consistentes.
                  </p>
                </div>
              </div>

              <div class="flex gap-4">
                <div
                  class="flex-shrink-0 w-8 h-8 rounded-lg bg-pink-100 flex items-center justify-center text-sm font-bold text-pink-600 mt-1"
                >
                  3
                </div>
                <div>
                  <h4 class="font-bold text-gray-900 mb-1">Sistemas de Navegación</h4>
                  <p class="text-gray-700">
                    Especifican las formas en que los usuarios pueden moverse a través de la
                    información.
                  </p>
                </div>
              </div>

              <div class="flex gap-4">
                <div
                  class="flex-shrink-0 w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center text-sm font-bold text-cyan-600 mt-1"
                >
                  4
                </div>
                <div>
                  <h4 class="font-bold text-gray-900 mb-1">Sistemas de Búsqueda</h4>
                  <p class="text-gray-700">
                    Permiten a los usuarios buscar contenido activamente mediante queries y filtros.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Herramientas y Métodos</h2>

            <p class="text-gray-700 leading-relaxed mb-6">
              Para diseñar una arquitectura de información efectiva, los diseñadores UX utilizan
              diversas técnicas y entregables, entre los más comunes encontramos:
            </p>

            <ul class="space-y-3">
              <li class="flex items-start gap-3">
                <div class="w-2 h-2 rounded-full bg-indigo-600 mt-2.5 flex-shrink-0"></div>
                <span class="text-gray-700"
                  ><strong class="text-gray-900">Sitemaps:</strong> Diagramas que muestran la estructura
                  jerárquica de un sitio web o aplicación</span
                >
              </li>
              <li class="flex items-start gap-3">
                <div class="w-2 h-2 rounded-full bg-indigo-600 mt-2.5 flex-shrink-0"></div>
                <span class="text-gray-700"
                  ><strong class="text-gray-900">Card Sorting:</strong> Técnica de investigación para
                  entender cómo los usuarios categorizan información</span
                >
              </li>
              <li class="flex items-start gap-3">
                <div class="w-2 h-2 rounded-full bg-indigo-600 mt-2.5 flex-shrink-0"></div>
                <span class="text-gray-700"
                  ><strong class="text-gray-900">User Flows:</strong> Mapas que visualizan los caminos
                  que toman los usuarios para completar tareas</span
                >
              </li>
              <li class="flex items-start gap-3">
                <div class="w-2 h-2 rounded-full bg-indigo-600 mt-2.5 flex-shrink-0"></div>
                <span class="text-gray-700"
                  ><strong class="text-gray-900">Taxonomías:</strong> Sistemas formales de clasificación
                  y etiquetado de contenido</span
                >
              </li>
            </ul>
          </div>
        </article>

        <!-- Resources Section -->
        <div class="mt-16 pt-12 border-t border-gray-200">
          <div class="mb-8">
            <div class="flex items-center gap-3 mb-3">
              <FileText class="w-5 h-5 text-indigo-600" />
              <h3 class="text-2xl font-bold text-gray-900">Recursos de la Unidad</h3>
            </div>
            <p class="text-gray-600">Materiales complementarios para profundizar en el tema</p>
          </div>

          <!-- Resource Cards Grid -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {#each resources as resource}
              <ResourceCard {...resource} />
            {/each}
          </div>
        </div>

        <!-- Navigation Footer -->
        <div class="mt-16 pt-8 border-t border-gray-200 flex items-center justify-between">
          <Link
            href={`/estudiante/cursos/${id_curso}`}
            class="flex items-center gap-2 px-5 py-3 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-all"
          >
            <ArrowLeft class="w-4 h-4" />
            <span class="font-medium">Anterior: 1.1 Heurísticas</span>
          </Link>

          <button
            class="flex items-center gap-2 px-5 py-3 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
          >
            <span class="font-medium">Siguiente: 1.3 Principios</span>
            <ArrowLeft class="w-4 h-4 rotate-180" />
          </button>
        </div>
      </div>
    </main>
  </div>
</StudentLayout>
