<script lang="ts">
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import * as Card from '@/components/ui/card';
  import * as Button from '@/components/ui/button';
  import { BookOpen, FileText, ArrowRight, Plus, MessageSquare } from 'lucide-svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';

  interface Props {
    id_curso: number;
    curso?: {
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura_nombre?: string;
      carrera_nombre?: string;
      fecha_inicio?: string;
      fecha_fin?: string;
    };
    tiene_programa?: boolean;
    userPermissions?: Permission[];
  }

  let { id_curso, curso, tiene_programa = false, userPermissions = [] }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: 'dashboard' },
    { title: 'Cursos', href: 'cursos' },
    { title: curso?.nombre || `Curso ${id_curso}`, href: '' },
  ]);

  // Validar permisos
  const canCreatePrograma = $derived.by(() => hasPermission(userPermissions, 'cursos/programas:crear'));
</script>

<AyudanteLayout {breadcrumbs}>
  <div class="space-y-6">
    <!-- Course Header Card -->
    <Card.Root class="bg-gradient-to-r from-blue-50 to-indigo-50 border-indigo-200">
      <Card.Header>
        <div class="flex items-start justify-between">
          <div class="space-y-2">
            <Card.Title class="flex items-center gap-2 text-2xl">
              <BookOpen class="h-6 w-6 text-indigo-600" />
              {curso?.nombre || `Curso ${id_curso}`}
            </Card.Title>
            <p class="text-sm text-gray-600">
              Código: <span class="font-semibold">{curso?.cod_curso || 'N/A'}</span>
            </p>
          </div>
        </div>
      </Card.Header>
      <Card.Content class="space-y-3">
        {#if curso?.asignatura_nombre}
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs font-medium text-gray-500 uppercase">Asignatura</p>
              <p class="text-sm font-semibold text-gray-900">{curso.asignatura_nombre}</p>
            </div>
            {#if curso?.carrera_nombre}
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Carrera</p>
                <p class="text-sm font-semibold text-gray-900">{curso.carrera_nombre}</p>
              </div>
            {/if}
          </div>
        {/if}
        {#if curso?.fecha_inicio || curso?.fecha_fin}
          <div class="border-t border-indigo-200 pt-3">
            <p class="text-xs font-medium text-gray-500 uppercase">Período</p>
            <p class="text-sm text-gray-900">
              {#if curso?.fecha_inicio}
                {new Date(curso.fecha_inicio).toLocaleDateString('es-CL')}
              {/if}
              {#if curso?.fecha_fin}
                - {new Date(curso.fecha_fin).toLocaleDateString('es-CL')}
              {/if}
            </p>
          </div>
        {/if}
      </Card.Content>
    </Card.Root>

    <!-- Course Sections -->
    <div class="grid gap-4">
      <h2 class="text-lg font-semibold text-gray-900">Secciones</h2>

      {#if tiene_programa}
        <Card.Root class="hover:shadow-md transition-shadow">
          <Card.Header>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <FileText class="h-5 w-5 text-blue-600" />
                <div>
                  <Card.Title class="text-base">Programa de Curso</Card.Title>
                  <p class="text-xs text-gray-500 mt-1">Ver y gestionar el programa del curso</p>
                </div>
              </div>
            </div>
          </Card.Header>
          <Card.Content>
            <Link href={`/ayudante/cursos/${id_curso}/programa`}>
              <Button.Root class="w-full">
                <span>Ver Programa</span>
                <ArrowRight class="h-4 w-4 ml-2" />
              </Button.Root>
            </Link>
          </Card.Content>
        </Card.Root>
      {:else if canCreatePrograma}
        <Card.Root class="bg-green-50 border-green-200 hover:shadow-md transition-shadow">
          <Card.Header>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <Plus class="h-5 w-5 text-green-600" />
                <div>
                  <Card.Title class="text-base">Crear Programa de Curso</Card.Title>
                  <p class="text-xs text-gray-500 mt-1">Inicia la creación del programa de este curso</p>
                </div>
              </div>
            </div>
          </Card.Header>
          <Card.Content>
            <Link href={`/ayudante/cursos/${id_curso}/programa/create`}>
              <Button.Root class="w-full bg-green-600 hover:bg-green-700">
                <span>Crear Programa</span>
                <Plus class="h-4 w-4 ml-2" />
              </Button.Root>
            </Link>
          </Card.Content>
        </Card.Root>
      {:else}
        <Card.Root class="bg-yellow-50 border-yellow-200">
          <Card.Content class="pt-6">
            <p class="text-sm text-yellow-800">No hay programa disponible para este curso en este momento.</p>
          </Card.Content>
        </Card.Root>
      {/if}

      <!-- Mensajería de nivel curso (curso.mensaje): avisos al componente y el
           canal de cada alumno, compartido con los docentes. Se entra desde el
           curso porque el hilo pertenece a él. -->
      <Card.Root class="hover:shadow-md transition-shadow">
        <Card.Header>
          <div class="flex items-center gap-3">
            <MessageSquare class="h-5 w-5 text-indigo-600" />
            <div>
              <Card.Title class="text-base">Mensajería</Card.Title>
              <p class="text-xs text-gray-500 mt-1">
                Avisos del curso y conversación con cada alumno
              </p>
            </div>
          </div>
        </Card.Header>
        <Card.Content>
          <Link href={`/ayudante/cursos/${id_curso}/mensajeria`}>
            <Button.Root variant="outline" class="w-full">
              <span>Abrir mensajería</span>
              <ArrowRight class="h-4 w-4 ml-2" />
            </Button.Root>
          </Link>
        </Card.Content>
      </Card.Root>
    </div>
  </div>
</AyudanteLayout>
