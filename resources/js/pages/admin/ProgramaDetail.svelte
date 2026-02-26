<script lang="ts">
  import { usePage } from '@inertiajs/svelte';
  import { page } from '@inertiajs/svelte';
  import ProgramaDetailView from '@/components/custom/admin/ProgramaDetailView.svelte';

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
        creditos?: number;
      };
      secciones?: Record<string, any>;
    };
    completenessPercentage?: number;
    creator?: { id_usuario: number; nombre_completo: string };
    reviewer?: { id_usuario: number; nombre_completo: string };
  }

  interface Props {
    programa: Programa;
    userRole: string;
    userId: number;
  }

  let { programa, userRole, userId }: Props = $props();
</script>

<div class="min-h-screen bg-gray-50 py-8">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <ProgramaDetailView {programa} {userRole} {userId} />
  </div>
</div>
