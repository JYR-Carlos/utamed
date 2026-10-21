<script lang="ts">
    import { OPCIONES_APARIENCIA, useAppearance } from '@/hooks/useAppearance.svelte';

    interface Props {
        class?: string;
    }

    let { class: className }: Props = $props();

    const apariencia = useAppearance();
</script>

<div role="radiogroup" aria-label="Tema" class="inline-flex gap-1 rounded-lg bg-muted p-1 {className}">
    {#each OPCIONES_APARIENCIA as { value, Icon, label } (value)}
        <button
            type="button"
            role="radio"
            aria-checked={apariencia.appearance === value}
            onclick={() => apariencia.updateAppearance(value)}
            class="flex items-center rounded-md px-3.5 py-1.5 transition-colors
            {apariencia.appearance === value
                ? 'bg-card text-foreground shadow-xs'
                : 'text-muted-foreground hover:bg-accent hover:text-foreground'}"
        >
            <Icon class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{label}</span>
        </button>
    {/each}
</div>
