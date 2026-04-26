<script lang="ts">
  /**
   * Página de actividades del curso - Vista del Estudiante.
   *
   * Muestra todas las actividades visibles del curso con su estado,
   * nota grupal y nota individual del alumno.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import { Undo2, Clock, CheckCircle, AlertCircle, Users, User, BookOpen } from 'lucide-svelte';
  import * as Card from '@/components/ui/card';

  // ---------------------------------------------------------------------------
  // Types
  // ---------------------------------------------------------------------------
  interface Estado {
    id_estado: number;
    titulo: string;
  }

  interface Actividad {
    id_actividad: number;
    nombre: string;
    fecha_limite: string;
    tipo_actividad: number;
    tipo_entrega: string;
    es_grupal: boolean;
    max_integrantes: number;
    componente: { id_componente: number; tipo: string } | null;
    unidad: { id_unidad: number; nombre: string } | null;
    // Estado de asignación del alumno
    asignado: boolean;
    grupo_numero: number | null;
    nota_grupal: number | null;
    nota_individual: number | null;
    diferencia_decimas: number | null;
    estado: Estado | null;
  }

  interface Props {
    curso: {
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura_nombre: string;
    };
    actividades: Actividad[];
  }

  let { curso, actividades }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso.nombre, href: `/estudiante/cursos/${curso.id_curso}` },
    { title: 'Actividades', href: '' },
  ]);

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------
  function formatDate(d: string) {
    return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function isVencida(fechaLimite: string) {
    return new Date(fechaLimite) < new Date();
  }

  function notaFinal(act: Actividad): number | null {
    // Si tiene nota individual úsela, sino usa nota grupal
    if (act.nota_individual !== null && act.nota_individual !== undefined) {
      const diff = act.diferencia_decimas ?? 0;
      return Math.min(10, Math.max(0, act.nota_individual + diff * 0.1));
    }
    return act.nota_grupal;
  }

  function notaColor(nota: number | null): string {
    if (nota === null || nota === undefined) return 'nota-sin-calificar';
    if (nota >= 6) return 'nota-aprobado';
    if (nota >= 4) return 'nota-regular';
    return 'nota-reprobado';
  }

  function estadoBadgeClass(titulo: string): string {
    const t = titulo.toLowerCase();
    if (t.includes('entregad') || t.includes('completad')) return 'badge-success';
    if (t.includes('calificad') || t.includes('revisad')) return 'badge-info';
    if (t.includes('pendiente') || t.includes('asign')) return 'badge-warning';
    if (t.includes('retraso') || t.includes('tard')) return 'badge-danger';
    return 'badge-neutral';
  }

  // Agrupar por unidad para organizar la vista
  const porUnidad = $derived.by(() => {
    const map = new Map<string, Actividad[]>();
    for (const act of actividades) {
      const key = act.unidad?.nombre ?? 'Sin unidad';
      if (!map.has(key)) map.set(key, []);
      map.get(key)!.push(act);
    }
    return map;
  });
</script>

<StudentLayout {breadcrumbs}>
  <div class="container mx-auto px-6 py-8">
    <!-- ── Header ─────────────────────────────────────────────────── -->
    <div class="mb-8 flex items-center gap-4">
      <Link
        href={`/estudiante/cursos/${curso.id_curso}`}
        class="bg-white p-2 rounded-full shadow-sm hover:shadow-md transition-shadow text-slate-600"
      >
        <Undo2 size={20} />
      </Link>
      <div>
        <h1 class="text-3xl font-bold text-slate-900">Actividades</h1>
        <p class="text-slate-600">{curso.asignatura_nombre} · {curso.cod_curso}</p>
      </div>
    </div>

    <!-- ── Sin actividades ────────────────────────────────────────── -->
    {#if actividades.length === 0}
      <Card.Root>
        <Card.Content class="py-16 text-center">
          <BookOpen class="mx-auto text-slate-300 mb-4" size={48} />
          <h3 class="text-lg font-semibold text-slate-700 mb-1">No hay actividades disponibles</h3>
          <p class="text-slate-500 text-sm">Aún no se han publicado actividades para este curso.</p>
        </Card.Content>
      </Card.Root>
    {:else}
      <!-- ── Resumen ────────────────────────────────────────────── -->
      <div class="grid grid-cols-4 gap-4 mb-8 md:grid-cols-2 sm:grid-cols-1">
        <div class="bg-white border border-gray-200 rounded-xl p-5 flex flex-col items-center gap-1">
          <span class="text-3xl font-bold text-gray-900 leading-none">{actividades.length}</span>
          <span class="text-xs text-gray-600 uppercase tracking-widest">Total</span>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5 flex flex-col items-center gap-1">
          <span class="text-3xl font-bold text-green-600 leading-none">
            {actividades.filter((a) => notaFinal(a) !== null && notaFinal(a)! >= 6).length}
          </span>
          <span class="text-xs text-gray-600 uppercase tracking-widest">Aprobadas</span>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5 flex flex-col items-center gap-1">
          <span class="text-3xl font-bold text-amber-600 leading-none">
            {actividades.filter((a) => a.asignado && notaFinal(a) === null).length}
          </span>
          <span class="text-xs text-gray-600 uppercase tracking-widest">Pendiente nota</span>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5 flex flex-col items-center gap-1">
          <span class="text-3xl font-bold text-slate-400 leading-none">
            {actividades.filter((a) => !a.asignado).length}
          </span>
          <span class="text-xs text-gray-600 uppercase tracking-widest">Sin asignar</span>
        </div>
      </div>

      <!-- ── Actividades por unidad ─────────────────────────────── -->
      {#each [...porUnidad.entries()] as [unidadNombre, acts]}
        <section class="mb-8">
          <h2 class="text-base font-bold text-gray-700 mb-3 pl-3 border-l-4 border-blue-500">{unidadNombre}</h2>

          <div class="flex flex-col gap-2">
            {#each acts as act (act.id_actividad)}
              <div
                class="flex items-center gap-4 p-4 bg-white border border-gray-200 rounded-xl transition-shadow hover:shadow-md"
                class:bg-red-50={!act.asignado && isVencida(act.fecha_limite)}
                class:border-red-200={!act.asignado && isVencida(act.fecha_limite)}
              >
                <!-- Ícono de modalidad -->
                <div class="flex-shrink-0 w-9 h-9 flex items-center justify-center bg-slate-100 rounded-lg">
                  {#if act.es_grupal}
                    <Users size={20} class="text-blue-500" />
                  {:else}
                    <User size={20} class="text-purple-500" />
                  {/if}
                </div>

                <!-- Info principal -->
                <div class="flex-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="font-semibold text-gray-900 text-sm">{act.nombre}</span>
                    {#if act.es_grupal}
                      <span class="text-xs font-semibold px-2 py-1 bg-blue-100 text-blue-700 rounded-full">Grupal</span>
                    {:else}
                      <span class="text-xs font-semibold px-2 py-1 bg-purple-100 text-purple-700 rounded-full">Individual</span>
                    {/if}
                    <span class="text-xs font-semibold px-2 py-1 bg-gray-100 text-gray-700 rounded-full">{act.tipo_entrega}</span>
                    {#if act.componente}
                      <span class="text-xs font-semibold px-2 py-1 bg-amber-100 text-amber-700 rounded-full">{act.componente.tipo}</span>
                    {/if}
                  </div>
                  <div class="flex flex-wrap gap-3 items-center">
                    <span class="inline-flex items-center gap-1 text-xs text-gray-600">
                      <Clock size={13} />
                      {formatDate(act.fecha_limite)}
                      {#if isVencida(act.fecha_limite)}
                        <span class="text-red-500 font-semibold">(vencida)</span>
                      {/if}
                    </span>
                  </div>
                </div>

                <!-- Estado del alumno -->
                <div class="flex-shrink-0">
                  {#if act.estado}
                    <span
                      class="inline-block text-xs font-semibold px-3 py-2 rounded-full"
                      class:bg-green-100={act.estado.titulo.toLowerCase().includes('entregad') ||
                        act.estado.titulo.toLowerCase().includes('completad')}
                      class:text-green-700={act.estado.titulo.toLowerCase().includes('entregad') ||
                        act.estado.titulo.toLowerCase().includes('completad')}
                      class:bg-blue-100={act.estado.titulo.toLowerCase().includes('calificad') || act.estado.titulo.toLowerCase().includes('revisad')}
                      class:text-blue-700={act.estado.titulo.toLowerCase().includes('calificad') ||
                        act.estado.titulo.toLowerCase().includes('revisad')}
                      class:bg-amber-100={act.estado.titulo.toLowerCase().includes('pendiente') || act.estado.titulo.toLowerCase().includes('asign')}
                      class:text-amber-700={act.estado.titulo.toLowerCase().includes('pendiente') ||
                        act.estado.titulo.toLowerCase().includes('asign')}
                      class:bg-red-100={act.estado.titulo.toLowerCase().includes('retraso') || act.estado.titulo.toLowerCase().includes('tard')}
                      class:text-red-700={act.estado.titulo.toLowerCase().includes('retraso') || act.estado.titulo.toLowerCase().includes('tard')}
                      class:bg-gray-100={!act.estado.titulo.toLowerCase().includes('entregad') &&
                        !act.estado.titulo.toLowerCase().includes('completad') &&
                        !act.estado.titulo.toLowerCase().includes('calificad') &&
                        !act.estado.titulo.toLowerCase().includes('revisad') &&
                        !act.estado.titulo.toLowerCase().includes('pendiente') &&
                        !act.estado.titulo.toLowerCase().includes('asign') &&
                        !act.estado.titulo.toLowerCase().includes('retraso') &&
                        !act.estado.titulo.toLowerCase().includes('tard')}
                      class:text-gray-700={!act.estado.titulo.toLowerCase().includes('entregad') &&
                        !act.estado.titulo.toLowerCase().includes('completad') &&
                        !act.estado.titulo.toLowerCase().includes('calificad') &&
                        !act.estado.titulo.toLowerCase().includes('revisad') &&
                        !act.estado.titulo.toLowerCase().includes('pendiente') &&
                        !act.estado.titulo.toLowerCase().includes('asign') &&
                        !act.estado.titulo.toLowerCase().includes('retraso') &&
                        !act.estado.titulo.toLowerCase().includes('tard')}>{act.estado.titulo}</span
                    >
                  {:else if act.asignado}
                    <span class="inline-block text-xs font-semibold px-3 py-2 bg-amber-100 text-amber-700 rounded-full">Sin estado</span>
                  {:else}
                    <span class="inline-block text-xs font-semibold px-3 py-2 bg-gray-100 text-gray-700 rounded-full">No asignado</span>
                  {/if}
                </div>

                <!-- Nota -->
                <div class="flex-shrink-0 text-right min-w-[60px] flex flex-col items-end gap-1">
                  {#if !act.asignado}
                    <span class="text-xl font-black text-gray-400">—</span>
                  {:else}
                    {@const final = notaFinal(act)}
                    <span
                      class="text-xl font-black leading-none"
                      class:text-green-600={notaColor(final) === 'nota-aprobado'}
                      class:text-amber-700={notaColor(final) === 'nota-regular'}
                      class:text-red-600={notaColor(final) === 'nota-reprobado'}
                      class:text-gray-400={notaColor(final) === 'nota-sin-calificar'}
                    >
                      {final !== null ? final.toFixed(1) : '—'}
                    </span>
                    {#if act.nota_grupal !== null && act.nota_individual !== null}
                      <span class="text-xs text-gray-400">G:{act.nota_grupal ?? '—'} / I:{act.nota_individual ?? '—'}</span>
                    {/if}
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        </section>
      {/each}
    {/if}
  </div>
</StudentLayout>
