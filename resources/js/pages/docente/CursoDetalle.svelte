<script lang="ts">
  /**
   * Página de detalles de curso para docentes.
   *
   * Muestra información completa del curso incluyendo:
   * - Información general (nombre, código, asignatura, plan, carrera)
   * - Fechas y período académico
   * - Estadísticas de estudiantes inscritos
   * - Mi Grupo: lista de alumnos del docente agrupados por componente
   */
  import { router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Button } from '@/components/ui/button';
  import * as Card from '@/components/ui/card';
  import { Badge } from '@/components/ui/badge';
  import {
    ArrowLeft,
    Calendar,
    BookOpen,
    Users,
    GraduationCap,
    Building2,
    FileText,
    BookOpenCheck,
    Crown,
    UserCheck,
  } from 'lucide-svelte';

  interface Seccion {
    id_seccion: number;
    tipo: string;
    total_estudiantes: number;
  }

  interface Componente {
    id_componente: number;
    tipo_componente: string;
    es_titular: boolean;
    total_docentes: number;
    total_estudiantes: number;
  }

  interface EstudianteComponente {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
    estudiante: {
      id_estudiante: number;
      nombre: string;
      username: string;
    };
  }

  interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    fecha_inicio: string;
    fecha_fin: string;
    agno_real: number;
    semestre_real: number;
    estado_interno: string;
    es_plantilla: boolean;
    tiene_programa: boolean;
    es_titular_curso: boolean;
    asignatura: {
      nombre: string;
      cod_asignatura: string;
      descripcion: string;
    };
    plan: {
      nombre: string;
      carrera: string;
    };
    secciones: Seccion[];
    total_estudiantes: number;
  }

  interface Props {
    curso: Curso;
    mis_componentes: Componente[];
    mis_estudiantes: EstudianteComponente[];
  }

  let { curso, mis_componentes, mis_estudiantes }: Props = $props();

  // Pestaña activa en la sección "Mi Grupo"
  let componenteActivo = $state<number | null>(null);

  $effect.pre(() => {
    if (componenteActivo === null && mis_componentes.length > 0) {
      componenteActivo = mis_componentes[0].id_componente;
    }
  });

  const estudiantesActivos = $derived(
    mis_estudiantes.filter((e) => e.id_componente === componenteActivo),
  );

  function goBack() {
    router.visit('/docente/cursos');
  }

  function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('es-CL', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }
</script>

<DocenteLayout>
  <div class="space-y-6">
    <!-- Header con navegación -->
    <div class="flex items-center gap-4">
      <Button variant="ghost" onclick={goBack} class="gap-2">
        <ArrowLeft class="h-4 w-4" />
        Volver
      </Button>
      <div class="flex-1">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">{curso.nombre}</h1>
        <p class="text-sm text-slate-500 mt-1">
          {curso.cod_curso} • {curso.asignatura.cod_asignatura}
        </p>
      </div>
      {#if curso.tiene_programa}
        <Button
          variant="outline"
          onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
          class="gap-2"
        >
          <BookOpenCheck class="h-4 w-4" />
          Ver Programa
        </Button>
      {/if}
    </div>

    <!-- Grid de información -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <!-- Card: Información General -->
      <Card.Root class="col-span-full lg:col-span-2">
        <Card.Header>
          <Card.Title class="flex items-center gap-2">
            <BookOpen class="h-5 w-5 text-blue-600" />
            Información General
          </Card.Title>
        </Card.Header>
        <Card.Content class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <p class="text-sm font-medium text-slate-500">Asignatura</p>
              <p class="text-base font-semibold text-slate-900">{curso.asignatura.nombre}</p>
              <p class="text-sm text-slate-600">{curso.asignatura.cod_asignatura}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-500">Código del Curso</p>
              <p class="text-base font-semibold text-slate-900">{curso.cod_curso}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-500">Plan de Estudios</p>
              <p class="text-base font-semibold text-slate-900">{curso.plan.nombre}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-500 flex items-center gap-1">
                <Building2 class="h-4 w-4" />
                Carrera
              </p>
              <p class="text-base font-semibold text-slate-900">{curso.plan.carrera}</p>
            </div>
          </div>
          {#if curso.asignatura.descripcion}
            <div class="pt-4 border-t">
              <p class="text-sm font-medium text-slate-500 mb-2">Descripción</p>
              <p class="text-sm text-slate-700">{curso.asignatura.descripcion}</p>
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- Card: Período Académico -->
      <Card.Root>
        <Card.Header>
          <Card.Title class="flex items-center gap-2">
            <Calendar class="h-5 w-5 text-indigo-600" />
            Período Académico
          </Card.Title>
        </Card.Header>
        <Card.Content class="space-y-3">
          <div>
            <p class="text-sm font-medium text-slate-500">Año</p>
            <p class="text-lg font-semibold text-slate-900">{curso.agno_real}</p>
          </div>
          <div>
            <p class="text-sm font-medium text-slate-500">Semestre</p>
            <Badge variant="secondary" class="text-base">
              {curso.semestre_real === 1 ? 'Primer Semestre' : 'Segundo Semestre'}
            </Badge>
          </div>
          <div class="pt-3 border-t">
            <p class="text-sm font-medium text-slate-500 mb-1">Inicio</p>
            <p class="text-sm text-slate-700">{formatDate(curso.fecha_inicio)}</p>
          </div>
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">Término</p>
            <p class="text-sm text-slate-700">{formatDate(curso.fecha_fin)}</p>
          </div>
          <div class="pt-3 border-t">
            <p class="text-sm font-medium text-slate-500 mb-1">Estado</p>
            <Badge variant={curso.es_plantilla ? 'outline' : 'default'}>
              {curso.es_plantilla ? 'Plantilla' : 'Activo'}
            </Badge>
          </div>
        </Card.Content>
      </Card.Root>

      <!-- Card: Estadísticas -->
      <Card.Root class="col-span-full lg:col-span-1">
        <Card.Header>
          <Card.Title class="flex items-center gap-2">
            <Users class="h-5 w-5 text-emerald-600" />
            Estadísticas
          </Card.Title>
        </Card.Header>
        <Card.Content class="space-y-4">
          <div class="text-center p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg">
            <p class="text-sm font-medium text-slate-600 mb-2">Total de Estudiantes</p>
            <p class="text-4xl font-bold text-blue-600">{curso.total_estudiantes}</p>
          </div>
          <div class="text-center p-4 bg-slate-50 rounded-lg">
            <p class="text-sm font-medium text-slate-600 mb-1">Mis componentes</p>
            <p class="text-2xl font-bold text-slate-900">{mis_componentes.length}</p>
          </div>
          {#if curso.es_titular_curso}
            <div class="flex items-center gap-2 text-amber-700 bg-amber-50 rounded-lg px-3 py-2">
              <Crown class="h-4 w-4 shrink-0" />
              <span class="text-xs font-medium">Titular del curso</span>
            </div>
          {:else}
            <div class="flex items-center gap-2 text-slate-600 bg-slate-100 rounded-lg px-3 py-2">
              <UserCheck class="h-4 w-4 shrink-0" />
              <span class="text-xs font-medium">Docente colaborador</span>
            </div>
          {/if}
        </Card.Content>
      </Card.Root>
    </div>

    <!-- Sección: Mi Grupo -->
    {#if mis_componentes.length > 0}
      <Card.Root class="col-span-full">
        <Card.Header>
          <Card.Title class="flex items-center gap-2">
            <GraduationCap class="h-5 w-5 text-purple-600" />
            Mi Grupo
          </Card.Title>
          <Card.Description>Estudiantes inscritos en tus componentes asignados</Card.Description>
        </Card.Header>
        <Card.Content class="space-y-4">
          <!-- Tabs por componente -->
          {#if mis_componentes.length > 1}
            <div class="flex gap-2 border-b border-slate-200 pb-0 overflow-x-auto">
              {#each mis_componentes as comp}
                <button
                  onclick={() => (componenteActivo = comp.id_componente)}
                  class={`flex items-center gap-2 px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                    componenteActivo === comp.id_componente
                      ? 'border-indigo-600 text-indigo-700'
                      : 'border-transparent text-slate-500 hover:text-slate-700'
                  }`}
                >
                  {comp.tipo_componente}
                  {#if comp.es_titular}
                    <Crown class="h-3 w-3 text-amber-500" />
                  {/if}
                  <span
                    class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                  >
                    {comp.total_estudiantes}
                  </span>
                </button>
              {/each}
            </div>
          {:else if mis_componentes.length === 1}
            <div class="flex items-center gap-3">
              <Badge variant="secondary" class="text-sm">
                {mis_componentes[0].tipo_componente}
              </Badge>
              {#if mis_componentes[0].es_titular}
                <span
                  class="flex items-center gap-1 text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded-full"
                >
                  <Crown class="h-3 w-3" />
                  Titular del componente
                </span>
              {/if}
              {#if mis_componentes[0].total_docentes > 1}
                <span class="text-xs text-slate-500">
                  {mis_componentes[0].total_docentes} docentes en este componente
                </span>
              {/if}
            </div>
          {/if}

          <!-- Tabla de estudiantes -->
          {#if estudiantesActivos.length === 0}
            <div class="text-center py-10 text-slate-500">
              <Users class="h-10 w-10 mx-auto mb-3 text-slate-300" />
              <p class="text-sm">No hay estudiantes inscritos en este componente todavía.</p>
            </div>
          {:else}
            <div class="overflow-x-auto rounded-lg border border-slate-200">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                  <tr>
                    <th class="text-left py-3 px-4 font-semibold text-slate-700">#</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-700">Estudiante</th>
                    <th class="text-left py-3 px-4 font-semibold text-slate-700">Usuario</th>
                    <th class="text-right py-3 px-4 font-semibold text-slate-700">Nota parcial</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  {#each estudiantesActivos as item, i}
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-3 px-4 text-slate-400">{i + 1}</td>
                      <td class="py-3 px-4">
                        <span class="font-medium text-slate-900">{item.estudiante.nombre}</span>
                      </td>
                      <td class="py-3 px-4 text-slate-500">{item.estudiante.username}</td>
                      <td class="py-3 px-4 text-right">
                        {#if item.nota_componente !== null}
                          <span
                            class={`font-semibold ${item.nota_componente >= 4 ? 'text-emerald-600' : 'text-red-500'}`}
                          >
                            {item.nota_componente}
                          </span>
                        {:else}
                          <span class="text-slate-400">—</span>
                        {/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
            <p class="text-xs text-slate-400 text-right">
              {estudiantesActivos.length} estudiante(s)
            </p>
          {/if}
        </Card.Content>
      </Card.Root>
    {:else}
      <Card.Root class="col-span-full border-dashed">
        <Card.Content class="text-center py-10 text-slate-500">
          <GraduationCap class="h-10 w-10 mx-auto mb-3 text-slate-300" />
          <p class="text-sm">No estás asignado a ningún componente de este curso.</p>
        </Card.Content>
      </Card.Root>
    {/if}

    <!-- Acciones rápidas -->
    <div class="flex gap-3 pt-4 border-t">
      <Button
        onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
        class="gap-2"
      >
        <FileText class="h-4 w-4" />
        Gestionar Actividades
      </Button>
    </div>
  </div>
</DocenteLayout>
