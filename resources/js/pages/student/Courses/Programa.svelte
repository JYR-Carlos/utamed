<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Undo2, BookOpen } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';

  interface Props {
    programa: {
      id_programa: number;
      id_curso: number;
      version: number;
      estado: string;
      secciones: Array<{
        nombre_seccion: string;
        numeral_romano?: string;
        orden: number;
        contenidos_programa?: Array<{
          texto_contenido: string;
          orden_item: number;
        }>;
        contenidos?: Array<{
          texto_contenido: string;
          orden_item: number;
        }>;
        componentes?: Array<any>;
        ponderacion_optativa?: any;
      }>;
      creado_por: string;
      fecha_creacion: string;
    } | null;
    curso: {
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura: any;
      carrera: any;
    };
  }

  let { programa, curso }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso.nombre, href: `/estudiante/cursos/${curso.id_curso}` },
    { title: 'Programa', href: `/estudiante/cursos/${curso.id_curso}/programa` },
  ];

  function getContenidos(seccion: any) {
    return seccion.contenidos || seccion.contenidos_programa || [];
  }

  function formatDate(dateString: string) {
    try {
      return new Date(dateString).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    } catch {
      return dateString;
    }
  }
</script>

<StudentLayout {breadcrumbs}>
  <div class="w-full bg-white min-h-screen">
    <div class="max-w-5xl mx-auto px-6 py-12">
      {#if programa === null}
        <!-- Mensaje cuando no hay programa disponible -->
        <div class="mb-8 rounded-lg bg-amber-50 border-2 border-amber-200 p-8">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
              <BookOpen class="text-amber-600" size={32} />
            </div>
            <div class="flex-1">
              <h2 class="text-lg font-semibold text-amber-900 mb-2">Programa aún no disponible</h2>
              <p class="text-amber-800 text-sm mb-3">El programa de cátedra para este curso aún no ha sido aprobado por los administradores.</p>
              <p class="text-amber-700 text-xs">Por favor, intenta más tarde o contacta con la coordinación del programa.</p>
            </div>
          </div>
        </div>
      {:else}
        <!-- Documento de Programa -->
        <article class="prose prose-sm lg:prose-base max-w-none">
          <!-- Encabezado del Programa -->
          <div class="text-center mb-8 pb-6 border-b-2 border-slate-300">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">PROGRAMA DE ASIGNATURA</h1>
            <h2 class="text-2xl font-semibold text-slate-700 mb-4">{curso.asignatura?.nombre}</h2>
            <div class="text-sm text-slate-600 space-y-1">
              <p><strong>Código:</strong> {curso.cod_curso}</p>
              <p><strong>Carrera:</strong> {curso.carrera?.nombre}</p>
            </div>
          </div>

          <!-- Secciones -->
          <div class="space-y-8">
            {#each programa.secciones as seccion}
              <section class="mb-8">
                <!-- Encabezado de sección -->
                <div class="mb-4">
                  <h3 class="text-xl font-bold text-slate-900 flex items-center gap-3">
                    <span class="text-slate-600">{seccion.numeral_romano}.</span>
                    <span>{seccion.nombre_seccion.toUpperCase()}</span>
                  </h3>
                  <div class="h-1 w-20 bg-blue-600 mt-2"></div>
                </div>

                <!-- Contenido por sección -->
                {#if seccion.numeral_romano === 'I'}
                  <!-- Sección I: Tabla de identificación -->
                  <div class="overflow-x-auto mb-6">
                    <table class="w-full border-collapse border border-slate-300">
                      <tbody>
                        {#each getContenidos(seccion) as contenido}
                          {@const lines = contenido.texto_contenido.split('\n')}
                          {#each lines as line}
                            {@const [key, ...valueParts] = line.split(': ')}
                            {#if key && valueParts.length > 0}
                              <tr class="border border-slate-300">
                                <td class="bg-blue-50 p-3 font-semibold text-slate-700 w-1/3 border border-slate-300">{key}</td>
                                <td class="p-3 text-slate-700 border border-slate-300">{valueParts.join(': ')}</td>
                              </tr>
                            {/if}
                          {/each}
                        {/each}
                      </tbody>
                    </table>
                  </div>
                {:else if seccion.numeral_romano === 'IX'}
                  <!-- Sección IX: Aspecto administrativo con tabla de componentes -->
                  {#each getContenidos(seccion) as contenido}
                    <div class="whitespace-pre-wrap text-slate-700 mb-6 leading-relaxed font-sans text-sm">
                      {contenido.texto_contenido}
                    </div>
                  {/each}

                  {#if seccion.componentes && seccion.componentes.length > 0}
                    <div class="overflow-x-auto">
                      <table class="w-full border-collapse border border-slate-300">
                        <thead class="bg-blue-600 text-white">
                          <tr>
                            <th class="p-3 text-left border border-slate-300">Componente</th>
                            <th class="p-3 text-center border border-slate-300">Genera Acta</th>
                            <th class="p-3 text-center border border-slate-300">Porcentaje</th>
                            <th class="p-3 text-center border border-slate-300">Aprobación Obligatoria</th>
                            <th class="p-3 text-center border border-slate-300">Asistencia Obligatoria</th>
                          </tr>
                        </thead>
                        <tbody>
                          {#each seccion.componentes as comp, idx}
                            <tr class="border border-slate-300 {idx % 2 === 0 ? 'bg-slate-50' : 'bg-white'}">
                              <td class="p-3 border border-slate-300 font-medium text-slate-700">{comp.componente}</td>
                              <td class="p-3 text-center border border-slate-300">{comp.genera_acta ? 'Sí' : 'No'}</td>
                              <td class="p-3 text-center border border-slate-300">{comp.porcentaje}%</td>
                              <td class="p-3 text-center border border-slate-300">{comp.aprobacion_obligatoria ? 'Sí' : 'No'}</td>
                              <td class="p-3 text-center border border-slate-300">{comp.asistencia_obligatoria}%</td>
                            </tr>
                          {/each}
                        </tbody>
                      </table>
                    </div>

                    {#if seccion.ponderacion_optativa}
                      <div class="mt-4 p-4 bg-amber-50 border-l-4 border-amber-500 rounded">
                        <p class="font-semibold text-amber-900 mb-1">Ponderación Prueba Optativa</p>
                        <p class="text-amber-800 text-lg font-bold">{seccion.ponderacion_optativa.porcentaje || 0}%</p>
                      </div>
                    {/if}
                  {/if}
                {:else}
                  <!-- Resto de secciones: contenido formateado -->
                  {#each getContenidos(seccion) as contenido}
                    <div class="whitespace-pre-wrap text-slate-700 mb-6 leading-relaxed font-sans text-sm">
                      {contenido.texto_contenido}
                    </div>
                  {/each}
                {/if}
              </section>
            {/each}
          </div>

          <!-- Pie de página con información del documento -->
          <div class="mt-12 pt-6 border-t border-slate-300 text-xs text-slate-600 space-y-1">
            <p><strong>Versión:</strong> {programa.version}</p>
            <p><strong>Preparado por:</strong> {programa.creado_por || 'N/A'}</p>
            <p><strong>Fecha de Aprobación:</strong> {formatDate(programa.fecha_creacion)}</p>
          </div>
        </article>
      {/if}

      <!-- Botón volver -->
      <div class="mt-12 flex justify-center pt-6 border-t border-slate-200">
        <Link
          href={`/estudiante/cursos/${curso.id_curso}`}
          class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium transition-colors"
        >
          <Undo2 size={18} />
          Volver al Curso
        </Link>
      </div>
    </div>
  </div>
</StudentLayout>
