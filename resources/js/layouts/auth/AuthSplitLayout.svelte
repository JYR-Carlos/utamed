<script lang="ts">
    import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';
    import { home } from '@/routes';
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import { fade, fly } from 'svelte/transition';

    interface Props {
        title: string;
        description: string;
        children?: Snippet;
    }

    let { title, description, children }: Props = $props();
</script>

<div class="flex min-h-screen bg-background text-foreground selection:bg-primary/30">
    <!-- Left Column: Login Form -->
    <div class="flex flex-1 flex-col justify-center px-8 sm:px-12 md:px-24 lg:flex-none lg:w-[450px] xl:w-[500px] z-10 bg-background">
        <div class="mx-auto w-full max-w-sm lg:w-96" in:fly={{ y: 20, duration: 600, delay: 200 }}>
            <div class="mb-10 flex items-center gap-2">
                <Link href={home()} class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary shadow-lg shadow-primary/20">
                        <AppLogoIcon class="size-6 fill-current text-primary-foreground" />
                    </div>
                    <span class="text-xl font-bold tracking-tight">UTAMed</span>
                </Link>
            </div>

            <div class="space-y-2 mb-8">
                <h1 class="text-4xl font-bold tracking-tight text-foreground">{title}</h1>
                <p class="text-muted-foreground text-sm">{description}</p>
            </div>

            <div class="mt-8">
                {@render children?.()}
            </div>

            <div class="mt-12 flex items-center justify-between text-xs text-muted-foreground">
                <p>&copy; 2026 Universidad de Tarapacá</p>
                <div class="flex gap-4">
                    <a href="#" class="hover:text-primary transition-colors">Privacidad</a>
                    <a href="#" class="hover:text-primary transition-colors">Soporte</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Illustration (Dynamic Design) -->
    <div class="hidden lg:relative lg:flex lg:flex-1 lg:items-center lg:justify-center bg-primary/5 overflow-hidden border-l border-border">
        <!-- Abstract Shapes / Blobs -->
        <div class="absolute inset-0 z-0">
            <div class="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-primary/10 opacity-20 blur-3xl animate-pulse"></div>
            <div class="absolute -bottom-24 -right-24 h-[500px] w-[500px] rounded-full bg-primary/20 opacity-20 blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[800px] w-[800px] rounded-full bg-primary/5 opacity-10 blur-3xl"></div>
        </div>

        <div class="relative z-10 flex flex-col items-center text-center p-12 max-w-2xl" in:fade={{ duration: 1000 }}>
            <div class="space-y-6 mb-12">
                <h2 class="text-5xl font-extrabold tracking-tight text-foreground leading-tight">
                    Bienvenido al <br/>
                    <span class="text-primary">portal académico</span>
                </h2>
                <p class="text-muted-foreground text-lg opacity-80">
                    Tu puerta de entrada a la excelencia académica e innovación en la Universidad de Tarapacá.
                </p>
            </div>

            <!-- Illustration Container -->
            <div class="relative w-full max-w-2xl aspect-video drop-shadow-xl translate-y-4 rounded-3xl overflow-hidden border border-border bg-white" in:fly={{ y: 40, duration: 800, delay: 400 }}>
                <img 
                    src="/students.jpg" 
                    alt="Estudiantes UTAMed" 
                    class="w-full h-full object-cover"
                />
            </div>
        </div>
        
        <!-- Bottom gradient overlay -->
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-background/50 to-transparent pointer-events-none"></div>
    </div>
</div>

<style>
    :global(body) {
        background-color: var(--background);
    }
    
    /* Custom scrollbar for theme */
    :global(::-webkit-scrollbar) {
        width: 8px;
    }
    :global(::-webkit-scrollbar-track) {
        background: var(--background);
    }
    :global(::-webkit-scrollbar-thumb) {
        background: var(--border);
        border-radius: 4px;
    }
    :global(::-webkit-scrollbar-thumb:hover) {
        background: var(--muted-foreground);
    }
</style>
