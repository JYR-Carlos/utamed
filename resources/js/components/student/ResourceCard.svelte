<script lang="ts">
  import { FileText, FileJson2, BookOpen, Download } from 'lucide-svelte';

  interface Props {
    type: 'pdf' | 'figma' | 'reading';
    title: string;
    fileSize: string;
  }

  let { type, title, fileSize }: Props = $props();

  const typeConfig = {
    pdf: {
      icon: FileText,
      color: 'from-red-600 to-red-400',
      bgColor: 'bg-red-50',
      textColor: 'text-red-700',
      label: 'PDF'
    },
    figma: {
      icon: FileJson2,
      color: 'from-purple-600 to-purple-400',
      bgColor: 'bg-purple-50',
      textColor: 'text-purple-700',
      label: 'Figma'
    },
    reading: {
      icon: BookOpen,
      color: 'from-blue-600 to-blue-400',
      bgColor: 'bg-blue-50',
      textColor: 'text-blue-700',
      label: 'Lectura'
    }
  };

  const config = typeConfig[type];
  const IconComponent = config.icon;
</script>

<button
  class="group relative overflow-hidden rounded-xl border border-gray-200 p-6 text-left transition-all hover:border-gray-300 hover:shadow-md"
>
  <!-- Background -->
  <div class="absolute inset-0 {config.bgColor}" />

  <!-- Content -->
  <div class="relative z-10">
    <!-- Icon -->
    <div class="mb-4 inline-block rounded-lg bg-gradient-to-br {config.color} p-3">
      <IconComponent class="h-6 w-6 text-white" />
    </div>

    <!-- Title -->
    <h4 class="mb-2 font-semibold text-gray-900 text-sm leading-snug group-hover:text-indigo-700 transition-colors">
      {title}
    </h4>

    <!-- Metadata -->
    <div class="flex items-center justify-between">
      <span class="text-xs text-gray-600">{fileSize}</span>
      <Download class="h-4 w-4 text-gray-400 group-hover:text-indigo-600 transition-colors" />
    </div>
  </div>
</button>
