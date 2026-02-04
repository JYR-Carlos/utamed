<script lang="ts">
    import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';
</script>

<div class="inline-flex items-center gap-3">
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary">
        <AppLogoIcon class="size-5 fill-current text-white! dark:text-black!" />
    </div>
    <div class="flex flex-col gap-0.5 leading-none">
        <span class="truncate text-sm font-semibold">UtaMed</span>
        <span class="truncate text-xs text-muted-foreground">Sistema de Gestión</span>
    </div>
</div>
