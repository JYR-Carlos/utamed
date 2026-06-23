<script lang="ts">
  /**
   * Página de gestión de docentes del curso (solo Docente Titular).
   *
   * Muestra los componentes con sus docentes asignados,
   * la matriz de permisos del syllabus por colegiado,
   * y permite gestionar autorizaciones individuales.
   */
  import { router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Button } from '@/components/ui/button';
  import * as Card from '@/components/ui/card';
  import { Badge } from '@/components/ui/badge';
  import ColegiadoPermisosModal from './components/ColegiadoPermisosModal.svelte';
  import PermisosSyllabusMatriz from './components/PermisosSyllabusMatriz.svelte';
  import { ArrowLeft, Crown, UserCheck, UsersRound, Shield, TableProperties } from 'lucide-svelte';

  interface DocenteComponenteCurso {
    id_docente_componente: number;
    id_docente: number;
    id_usuario: number;
    nombre: string;
    es_titular: boolean;
  }

  interface ComponenteCurso {
    id_componente: number;
    tipo_componente: string;
    total_estudiantes: number;
    docentes: DocenteComponenteCurso[];
  }

  interface PermisoEspecialActual {
    id_permiso: number;
    esta_permitido: boolean;
    puede_delegar: boolean;
  }

  interface ColegiadoDocente {
    id_docente: number;
    id_usuario: number;
    nombre: string;
    username: string;
    es_titular: boolean;
    special_permissions: PermisoEspecialActual[];
  }

  interface PermisoSyllabus {
    id_permiso: number;
    slug: string;
    nombre: string;
  }

  interface DocentePerms {
    id_usuario: number;
    nombre: string;
    perms: Record<string, boolean>;
  }

  interface SyllabusMatrix {
    permisos: PermisoSyllabus[];
    docentes: DocentePerms[];
  }

  interface CursoResumen {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    asignatura: string;
  }

  interface PermisoDisponible {
    id_permiso: number;
    slug: string;
    nombre: string;
    descripcion?: string;
  }

  interface Props {
    curso: CursoResumen;
    todos_componentes: ComponenteCurso[];
    colegiados: ColegiadoDocente[];
    syllabus_matrix: SyllabusMatrix;
    available_permissions: Record<string, PermisoDisponible[]>;
  }

  let { curso, todos_componentes, colegiados, syllabus_matrix, available_permissions = {} }: Props = $props();

  // Modal de permisos individuales
  let permisosModalOpen = $state(false);
  let colegiadoSeleccionado = $state<ColegiadoDocente | null>(null);

  // Modal de matriz del syllabus
  let matrizModalOpen = $state(false);

  function goBack() {
    router.visit(`/docente/cursos/${curso.id_curso}`);
  }

  function openPermisosModal(col: ColegiadoDocente) {
    colegiadoSeleccionado = col;
    permisosModalOpen = true;
  }

  function closePermisosModal() {
    permisosModalOpen = false;
    colegiadoSeleccionado = null;
  }
</script>

<DocenteLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center gap-4">
      <Button variant="ghost" onclick={goBack} class="gap-2">
        <ArrowLeft class="h-4 w-4" />
        Volver al curso
      </Button>
      <div class="flex-1 min-w-[200px]">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Docentes del Curso</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {curso.nombre} • {curso.cod_curso} • {curso.asignatura}
        </p>
      </div>
      {#if syllabus_matrix.permisos.length > 0 && syllabus_matrix.docentes.length > 0}
        <Button variant="outline" onclick={() => (matrizModalOpen = true)} class="gap-2">
          <TableProperties class="h-4 w-4" />
          Matriz Permisos Syllabus
        </Button>
      {/if}
    </div>

    <!-- Componentes y docentes -->
    <Card.Root>
      <Card.Header>
        <Card.Title class="flex items-center gap-2">
          <UsersRound class="h-5 w-5 text-blue-600" />
          Componentes y Docentes
        </Card.Title>
        <Card.Description>
          Estructura del curso con los docentes asignados a cada componente.
        </Card.Description>
      </Card.Header>
      <Card.Content class="space-y-4">
        {#each todos_componentes as comp}
          <div class="rounded-lg border border-slate-200 p-4">
            <div class="flex items-center gap-2 mb-3">
              <Badge variant="secondary">{comp.tipo_componente}</Badge>
              <span class="text-xs text-slate-500">
                {comp.total_estudiantes} estudiante(s)
              </span>
            </div>
            {#if comp.docentes.length > 0}
              <div class="space-y-2">
                {#each comp.docentes as doc}
                  <div class="flex items-center justify-between py-2 px-3 rounded-md bg-slate-50">
                    <div class="flex items-center gap-2">
                      {#if doc.es_titular}
                        <Crown class="h-3.5 w-3.5 text-amber-500" />
                      {:else}
                        <UserCheck class="h-3.5 w-3.5 text-slate-400" />
                      {/if}
                      <span class="text-sm font-medium text-slate-800">{doc.nombre}</span>
                      {#if doc.es_titular}
                        <Badge variant="outline" class="text-xs">Titular</Badge>
                      {/if}
                    </div>
                  </div>
                {/each}
              </div>
            {:else}
              <p class="text-xs text-slate-400">Sin docentes asignados</p>
            {/if}
          </div>
        {/each}

        {#if todos_componentes.length === 0}
          <div class="text-center py-8 text-slate-500">
            <UsersRound class="h-8 w-8 mx-auto mb-2 text-slate-300" />
            <p class="text-sm">No hay componentes configurados en este curso.</p>
          </div>
        {/if}
      </Card.Content>
    </Card.Root>

    <!-- Autorizaciones a Docentes -->
    {#if colegiados.length > 0}
      <Card.Root>
        <Card.Header>
          <Card.Title class="flex items-center gap-2">
            <Shield class="h-5 w-5 text-indigo-600" />
            Autorizaciones a Docentes
          </Card.Title>
          <Card.Description>
            Otorga permisos especiales a los docentes que comparten este curso.
          </Card.Description>
        </Card.Header>
        <Card.Content class="space-y-2">
          {#each colegiados as col}
            <div
              class="flex items-center justify-between py-3 px-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <UserCheck class="h-4 w-4 text-slate-400" />
                <div>
                  <span class="text-sm font-medium text-slate-800">{col.nombre}</span>
                  <span class="text-xs text-slate-400 ml-2">{col.username}</span>
                </div>
              </div>
              <Button
                variant="outline"
                size="sm"
                onclick={() => openPermisosModal(col)}
                class="gap-1.5"
              >
                <Shield class="h-3.5 w-3.5" />
                Gestionar Permisos
              </Button>
            </div>
          {/each}
        </Card.Content>
      </Card.Root>
    {:else}
      <Card.Root class="border-dashed">
        <Card.Content class="text-center py-8 text-slate-500">
          <Shield class="h-8 w-8 mx-auto mb-2 text-slate-300" />
          <p class="text-sm">No hay docentes colegiados en este curso.</p>
        </Card.Content>
      </Card.Root>
    {/if}
  </div>

  <!-- Modal permisos individuales -->
  <ColegiadoPermisosModal
    bind:isOpen={permisosModalOpen}
    cursoId={curso.id_curso}
    colegiado={colegiadoSeleccionado}
    availablePermissions={available_permissions}
    onClose={closePermisosModal}
  />

  <!-- Modal matriz de permisos del syllabus -->
  <PermisosSyllabusMatriz
    bind:isOpen={matrizModalOpen}
    matrix={syllabus_matrix}
    cursoNombre={curso.nombre}
  />
</DocenteLayout>
