<script lang="ts">
    interface Props {
        children: any;
        class?: string;
    }
    let { children, class: className = '' }: Props = $props();
</script>

<div class="soft-card {className}">
    {@render children()}
</div>

<style>
    .soft-card {
        background: var(--card);
        color: var(--card-foreground);
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        border: 1px solid var(--border);
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .soft-card:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
    }
</style>
