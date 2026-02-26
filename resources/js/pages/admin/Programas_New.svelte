<script lang="ts">
  import { usePage } from '@inertiajs/svelte';
  import { page } from '@inertiajs/svelte';
  import ProgramasListView from '@/components/custom/admin/ProgramasListView.svelte';

  interface Programa {
    id_programa: number;
    version_programa: number;
    estado: string;
    creado_por: number;
    revisado_por?: number;
    fecha_creacion: string;
    data_syllabus: {
      metadata?: {
        tipo_syllabus: string;
        curso?: string;
        asignatura?: string;
      };
      secciones?: object;
    };
    completenessPercentage?: number;
    creator?: { id_usuario: number; nombre_completo: string };
    reviewer?: { id_usuario: number; nombre_completo: string };
  }

  interface Props {
    cursoId: number;
    cursoNombre: string;
    programas: Programa[];
    userRole: string;
    userId: number;
  }

  let { cursoId, cursoNombre, programas, userRole, userId }: Props = $props();
</script>

<div class="min-h-screen bg-gray-50 py-8">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <ProgramasListView {programas} {cursoId} {cursoNombre} {userRole} {userId} />
  </div>
</div>
