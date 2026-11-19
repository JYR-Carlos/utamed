<script lang="ts">
    /**
     * Carcasa del docente con la dirección institucional UTA: barra marino
     * arriba, menú a la izquierda (panel desplegable en el teléfono), cabecera
     * de la página y pie tricolor. Sólo el contenido se desplaza.
     *
     * Una página rediseñada pasa `titulo` (y si quiere `ceja`, `acciones` y
     * `detalle`) y arma su cuerpo con `cuerpo` de lib/inst. Las que todavía
     * no se rediseñan no lo pasan: conservan su margen y su encabezado, y
     * aquí sólo se les dibuja la ruta.
     */
    import { page } from '@inertiajs/svelte';
    import BarraDocente from '@/components/docente/layout/BarraDocente.svelte';
    import MenuDocente from '@/components/docente/layout/MenuDocente.svelte';
    import CabeceraDocente from '@/components/docente/layout/CabeceraDocente.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        ceja?: string;
        titulo?: string;
        acciones?: Snippet;
        detalle?: Snippet;
        /** Pestañas de las secciones del curso: dejan la cabecera fija arriba. */
        pestanas?: Snippet;
        children?: Snippet;
    }

    let { breadcrumbs = [], ceja, titulo, acciones, detalle, pestanas, children }: Props = $props();

    let menuAbierto = $state(false);

    // Cada visita cierra el menú del teléfono: si no, tapaba la página nueva.
    $effect(() => {
        $page.url;
        menuAbierto = false;
    });

    function saltarAlContenido(e: MouseEvent) {
        e.preventDefault();
        const destino = document.getElementById('contenido');
        destino?.focus({ preventScroll: true });
        destino?.scrollIntoView({ block: 'start' });
    }
</script>

<a
    href="#contenido"
    onclick={saltarAlContenido}
    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:flex focus:min-h-11 focus:items-center focus:rounded-md focus:bg-inst-oro focus:px-4 focus:font-inst focus:text-sm focus:font-bold focus:text-uta-marino focus:no-underline"
>
    Saltar al contenido
</a>

<div class="vista-docente flex h-svh flex-col overflow-hidden bg-inst-pagina font-inst text-sm text-inst-ink">
    <BarraDocente {menuAbierto} onAlternarMenu={() => (menuAbierto = !menuAbierto)} />

    {#if menuAbierto}
        <div class="max-h-[calc(100svh-3.5rem)] shrink-0 overflow-y-auto lg:hidden">
            <MenuDocente movil />
        </div>
    {/if}

    <div class="flex min-h-0 flex-1">
        <aside class="hidden w-60 shrink-0 lg:block">
            <MenuDocente />
        </aside>

        <!-- `scroll-region`: Inertia guarda y restaura el desplazamiento de este
             contenedor al volver atrás (UI-06). `relative`: lo absoluto de
             adentro (los `sr-only`) se mide contra este contenedor y no alarga
             el documento con un segundo scroll. -->
        <main
            id="contenido"
            tabindex="-1"
            class="relative flex min-w-0 flex-1 flex-col overflow-y-auto outline-none"
            scroll-region
        >
            {#if titulo || breadcrumbs.length > 1}
                <CabeceraDocente {breadcrumbs} {ceja} {titulo} {acciones} {detalle} {pestanas} />
            {/if}

            <div class="flex-1 {titulo ? '' : 'w-full py-6 md:p-8'}">
                {@render children?.()}
            </div>

            <footer class="mt-auto shrink-0 border-t border-inst-linea bg-inst-tarjeta">
                <div aria-hidden="true" class="grid h-1.5 grid-cols-3">
                    <span class="bg-uta-marino"></span>
                    <span class="bg-uta-celeste"></span>
                    <span class="bg-uta-oro"></span>
                </div>
                <div class="flex flex-wrap justify-between gap-2 px-6 py-3 text-[13px] text-inst-ink-3">
                    <span>Universidad de Tarapacá · Arica, Chile</span>
                    <span>UTAMED · Plataforma académica</span>
                </div>
            </footer>
        </main>
    </div>
</div>
