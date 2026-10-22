<script lang="ts">
    /**
     * Cáscara de la vista del alumno con la guía dm: menú noche a la izquierda
     * (cajón en móvil), barra superior y el contenido, que es lo único que se
     * desplaza. `vista-alumno` activa la variante `alumno:` (guia-dm.css) en lo
     * que el alumno comparte con el docente.
     */
    import { page } from '@inertiajs/svelte';
    import { Dialog } from 'bits-ui';
    import { X } from 'lucide-svelte';
    import MenuAlumno from '@/components/student/layout/MenuAlumno.svelte';
    import BarraAlumno from '@/components/student/layout/BarraAlumno.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();

    let menuAbierto = $state(false);

    // Cada visita cierra el cajón: si no, el menú quedaba tapando la página nueva.
    $effect(() => {
        $page.url;
        menuAbierto = false;
    });

    // Salta a las pestañas del curso si la página las tiene; si no, al contenido.
    function saltarAlContenido(e: MouseEvent) {
        e.preventDefault();
        const pestanas = document.getElementById('secciones-curso');
        const destino =
            pestanas?.querySelector<HTMLElement>('button, a') ??
            document.getElementById('contenido');
        if (!destino) return;
        destino.focus({ preventScroll: true });
        destino.scrollIntoView({ block: 'start' });
    }
</script>

<a
    href="#contenido"
    onclick={saltarAlContenido}
    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:flex focus:min-h-11 focus:items-center focus:rounded-[10px] focus:bg-dm-accion focus:px-4 focus:font-dm focus:text-sm focus:font-semibold focus:text-dm-sobre-accion focus:no-underline"
>
    Saltar al contenido
</a>

<div class="vista-alumno flex h-svh overflow-hidden bg-dm-pagina font-dm text-sm text-dm-ink">
    <aside class="hidden w-62 shrink-0 lg:block">
        <MenuAlumno />
    </aside>

    <Dialog.Root bind:open={menuAbierto}>
        <Dialog.Portal>
            <Dialog.Overlay class="fixed inset-0 z-50 bg-dm-velo lg:hidden" />
            <Dialog.Content
                class="vista-alumno fixed inset-y-0 left-0 z-50 w-80 max-w-[88vw] font-dm shadow-2xl outline-none lg:hidden"
                onOpenAutoFocus={(e) => e.preventDefault()}
            >
                <Dialog.Title class="sr-only">Menú</Dialog.Title>
                <MenuAlumno />
                <Dialog.Close
                    class="absolute top-4 right-3 flex size-11 cursor-pointer items-center justify-center rounded-lg text-dm-noche-texto hover:bg-dm-noche-activo focus-visible:outline-dm-amarillo"
                    aria-label="Cerrar el menú"
                >
                    <X size={22} aria-hidden="true" />
                </Dialog.Close>
            </Dialog.Content>
        </Dialog.Portal>
    </Dialog.Root>

    <div class="flex min-w-0 flex-1 flex-col">
        <BarraAlumno {breadcrumbs} onAbrirMenu={() => (menuAbierto = true)} />
        <!-- `scroll-region`: Inertia guarda y restaura el desplazamiento de este
             contenedor al volver atrás; sin él la vista saltaba al inicio. -->
        <main id="contenido" tabindex="-1" class="min-w-0 flex-1 overflow-y-auto outline-none" scroll-region>
            {@render children?.()}
        </main>
    </div>
</div>
