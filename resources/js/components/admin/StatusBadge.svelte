<script lang="ts">
  /**
   * Badge para mostrar estado (Activa/Discontinuada, etc.)
   */
  interface Props {
    status: 'active' | 'discontinued' | 'success' | 'warning' | 'error' | 'info';
    label: string;
    icon?: boolean;
  }

  let { status, label, icon = true }: Props = $props();

  const statusConfig = {
    active: {
      bg: 'bg-green-50',
      text: 'text-green-700',
      border: 'border-green-100',
      dot: 'bg-green-500',
    },
    discontinued: {
      bg: 'bg-gray-100',
      text: 'text-gray-500',
      border: 'border-gray-200',
      dot: 'bg-gray-400',
    },
    success: {
      bg: 'bg-green-50',
      text: 'text-green-700',
      border: 'border-green-100',
      dot: 'bg-green-500',
    },
    warning: {
      bg: 'bg-amber-50',
      text: 'text-amber-700',
      border: 'border-amber-200',
      dot: 'bg-amber-500',
    },
    error: {
      bg: 'bg-red-50',
      text: 'text-red-700',
      border: 'border-red-100',
      dot: 'bg-red-500',
    },
    info: {
      bg: 'bg-blue-50',
      text: 'text-blue-700',
      border: 'border-blue-100',
      dot: 'bg-blue-500',
    },
  };

  const config = statusConfig[status];
</script>

<span
  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {config.bg} {config.text} text-[11px] font-semibold border {config.border} whitespace-nowrap"
>
  {#if icon}
    <span class="w-1.5 h-1.5 rounded-full {config.dot} shrink-0"></span>
  {/if}
  {label}
</span>
