<script lang="ts">
  import { CheckCircle, Clock, XCircle, AlertCircle } from 'lucide-svelte';

  interface Props {
    estado: string;
    tipoSyllabus?: string;
    completenessPercentage?: number;
  }

  let { estado, tipoSyllabus, completenessPercentage = 0 }: Props = $props();

  let badge = $derived.by(() => {
    switch (estado) {
      case 'BORRADOR':
        return {
          iconType: 'clock',
          label: 'Borrador',
          color: 'bg-yellow-100 text-yellow-700 border-yellow-300',
          contentClass: 'text-yellow-700',
        };
      case 'BASICO_COMPLETO':
        return {
          iconType: 'clock',
          label: 'Básico Completo',
          color: 'bg-blue-100 text-blue-700 border-blue-300',
          contentClass: 'text-blue-700',
        };
      case 'COMPLETO':
        return {
          iconType: 'alert',
          label: 'Completo',
          color: 'bg-purple-100 text-purple-700 border-purple-300',
          contentClass: 'text-purple-700',
        };
      case 'APROBADO':
        return {
          iconType: 'check',
          label: 'Aprobado',
          color: 'bg-green-100 text-green-700 border-green-300',
          contentClass: 'text-green-700',
        };
      case 'PUBLICADO':
        return {
          iconType: 'check',
          label: 'Publicado',
          color: 'bg-teal-100 text-teal-700 border-teal-300',
          contentClass: 'text-teal-700',
        };
      default:
        return {
          iconType: 'alert',
          label: estado,
          color: 'bg-gray-100 text-gray-700 border-gray-300',
          contentClass: 'text-gray-700',
        };
    }
  });

  let tipoBadge = $derived.by(() => ({
    label: tipoSyllabus === 'BASICO' ? 'Básico (5)' : 'Completo (9)',
    color: tipoSyllabus === 'BASICO' ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-green-50 text-green-600 border-green-200',
  }));
</script>

<div class="flex flex-wrap items-center gap-2">
  <!-- Estado Badge -->
  <div class={`flex items-center gap-1.5 px-3 py-1.5 rounded-full border ${badge.color}`}>
    {#if badge.iconType === 'clock'}
      <Clock size={16} />
    {:else if badge.iconType === 'check'}
      <CheckCircle size={16} />
    {:else if badge.iconType === 'alert'}
      <AlertCircle size={16} />
    {:else if badge.iconType === 'x'}
      <XCircle size={16} />
    {/if}
    <span class="text-sm font-medium">{badge.label}</span>
  </div>

  <!-- Tipo Syllabus Badge -->
  {#if tipoSyllabus}
    <div class={`px-3 py-1.5 rounded-full border text-sm font-medium ${tipoBadge.color}`}>
      {tipoBadge.label}
    </div>
  {/if}

  <!-- Completeness Badge -->
  {#if completenessPercentage !== undefined && completenessPercentage > 0}
    <div class="px-3 py-1.5 rounded-full border border-orange-200 bg-orange-50 text-orange-600 text-sm font-medium">
      {completenessPercentage}% completo
    </div>
  {/if}
</div>
