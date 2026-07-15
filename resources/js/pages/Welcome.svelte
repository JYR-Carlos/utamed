<script lang="ts">
    /**
     * Página de bienvenida de UTAMed - Estética Académica Moderna
     *
     * Diseño inspirado en "Swiss Style" / Bauhaus para facultad de diseño.
     * Enfoque en tipografía, grillas estructuradas, y claridad institucional.
     */
    import { dashboard, login } from '@/routes';
    import { Link, page } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';
    import {
        Clock,
        CalendarRange,
        MonitorPlay,
        MousePointer2,
        MessageSquareQuote,
        BrainCircuit,
        UserCheck,
        Users2,
        ArrowRight,
        GraduationCap,
        ChevronRight
    } from 'lucide-svelte';
    import { fade, fly } from 'svelte/transition';

    let user = $derived($page.props.auth.user);

    let scrolled = $state(false);

    $effect(() => {
        const onScroll = () => {
            scrolled = window.scrollY > 40;
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    });

    const features = [
        {
            title: 'Asíncrono',
            description: 'Aprendizaje que se adapta a tu ritmo personal.',
            icon: Clock,
        },
        {
            title: 'Flexible',
            description: 'Modalidades de estudio adaptables a tus necesidades.',
            icon: CalendarRange,
        },
        {
            title: 'Accesible',
            description: 'Sin barreras geográficas o tecnológicas.',
            icon: MonitorPlay,
        },
        {
            title: 'Interactivo',
            description: 'Participación activa en tu proceso formativo.',
            icon: MousePointer2,
        },
        {
            title: 'Retroalimentado',
            description: 'Evaluación y mejora continua de tu progreso.',
            icon: MessageSquareQuote,
        },
        {
            title: 'Contextualizado',
            description: 'Conocimiento aplicado a realidades profesionales.',
            icon: BrainCircuit,
        },
        {
            title: 'Centrado en el Estudiante',
            description: 'Tu desarrollo integral es el eje del modelo.',
            icon: UserCheck,
        },
        {
            title: 'Construcción Social de Aprendizaje',
            description: 'Aprendizaje colaborativo en comunidad.',
            icon: Users2,
        }
    ];
</script>

<svelte:head>
    <title>UTAMed | Entorno Virtual de Aprendizaje</title>
</svelte:head>

<div class="relative min-h-screen font-sans text-[#F5F3FF] selection:bg-[#5B9BD5]/30 selection:text-[#F5F3FF]">
    <!-- Fondo fotográfico full-bleed, fijo al viewport -->
    <div
        class="fixed inset-0 z-0 scale-110 bg-cover bg-center blur-md"
        style="background-image: url('/img/background-design.jpg');"
        aria-hidden="true"
    ></div>

    <!-- Velo nocturno base para legibilidad constante -->
    <div class="fixed inset-0 z-0 bg-[#0d1626]/80" aria-hidden="true"></div>

    <!-- Overlay progresivo: se oscurece hacia el final del scroll -->
    <div
        class="absolute inset-0 z-0 bg-gradient-to-b from-transparent via-[rgba(13,22,38,0.55)] to-[rgba(13,22,38,0.9)]"
        aria-hidden="true"
    ></div>

    <!-- Resplandor ambiental lavanda -->
    <div
        class="pointer-events-none fixed left-1/2 top-1/3 z-0 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#5B9BD5]/10 blur-[120px]"
        aria-hidden="true"
    ></div>

    <!-- Navigation -->
    <nav
        class="fixed top-0 z-50 w-full border-b transition-all duration-200 ease-out {scrolled
            ? 'border-[rgba(255,255,255,0.12)] bg-[rgba(28,44,64,0.45)] backdrop-blur-2xl'
            : 'border-transparent bg-transparent'}"
    >
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-12">
            <div class="flex items-center gap-3">
                <AppLogoIcon class="size-6 fill-current text-[#F5F3FF] -rotate-3" />
                <div class="flex flex-col leading-none">
                    <span class="text-lg font-bold tracking-tight text-[#F5F3FF]">UTAMed</span>
                    <span class="text-[0.6rem] uppercase tracking-widest text-[#C4BFE0] font-medium">Facultad de Diseño</span>
                </div>
            </div>

            <div class="flex items-center gap-6 text-sm font-medium">
                {#if user}
                    <Link
                        href={dashboard()}
                        class="group flex items-center gap-2 rounded-md text-[#5B9BD5] transition-colors hover:text-[#2A66AC] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(91,155,213,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0d1626]"
                    >
                        Ir al Dashboard
                        <ArrowRight size={16} class="group-hover:translate-x-1 transition-transform" />
                    </Link>
                {:else}
                    <Link
                        href={login()}
                        class="rounded-[14px] bg-[#F5F4F0] px-8 py-3 text-sm font-semibold text-[#1A1625] transition-all duration-200 hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(91,155,213,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0d1626]"
                    >
                        Acceso Institucional
                    </Link>
                {/if}
            </div>
        </div>
    </nav>

    <main class="relative z-10 pt-32 pb-24">
        <!-- Hero Section -->
        <div class="container mx-auto px-6 lg:px-12 mb-24">
            <div class="grid lg:grid-cols-12 gap-12 lg:gap-24 items-center">

                <!-- Typography / Left Column -->
                <div class="lg:col-span-6 space-y-10" in:fade={{ duration: 800 }}>
                    <div class="inline-flex items-center gap-3 border-b border-[#5B9BD5] pb-1 w-fit">
                        <GraduationCap size={16} class="text-[#5B9BD5]" />
                        <span class="text-xs font-bold uppercase tracking-widest text-[#5B9BD5]">Modelo Educativo 2026</span>
                    </div>

                    <h1 class="text-5xl lg:text-7xl font-bold tracking-tight leading-[1.05] text-[#F5F3FF]">
                        Entorno Virtual de <br/>
                        <span class="bg-gradient-to-r from-white to-[#BFD9F2] bg-clip-text text-transparent"
                            >Enseñanza <span class="font-light text-[#C4BFE0]">&</span> Aprendizaje.</span
                        >
                    </h1>

                    <p class="text-lg text-[#C4BFE0] max-w-lg leading-relaxed border-l-2 border-[rgba(255,255,255,0.12)] pl-6">
                        Una plataforma que redefine la experiencia académica mediante la integración de tecnología, diseño y pedagogía.
                    </p>

                    <div class="flex items-center gap-4 pt-4">
                        <Link
                            href={user ? dashboard() : login()}
                            class="inline-flex h-12 items-center px-8 rounded-[14px] bg-[#F5F4F0] text-[15px] font-semibold text-[#1A1625] shadow-[0_24px_64px_rgba(0,0,0,0.35)] transition-all duration-200 hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(91,155,213,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0d1626]"
                        >
                            {user ? 'Continuar al Panel' : 'Ingresar al Portal'}
                        </Link>
                        {#if !user}
                        <div class="text-xs text-[#C4BFE0] max-w-[150px] leading-tight">
                            Acceso exclusivo para docentes y estudiantes.
                        </div>
                        {/if}
                    </div>
                </div>

                <!-- Structured Image / Right Column -->
                <div class="lg:col-span-6 relative" in:fly={{ x: 50, duration: 1000, delay: 200 }}>
                    <!-- Decorative Graphic Elements behind -->
                    <div class="absolute -top-12 -right-12 w-64 h-64 bg-[#5B9BD5]/10 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-8 -left-8 w-40 h-40 bg-[#BFD9F2]/10 rounded-full blur-2xl"></div>

                    <!-- Main Image Container (marco glass) -->
                    <div class="relative aspect-[4/3] rounded-[20px] border border-[rgba(255,255,255,0.12)] bg-[rgba(28,44,64,0.45)] p-2 shadow-[0_24px_64px_rgba(0,0,0,0.35)] backdrop-blur-2xl">
                        <img
                            src="/banner-utamed.jpg"
                            alt="Estudiantes de Diseño UTAMed"
                            class="w-full h-full object-cover rounded-2xl filter saturate-[.8] contrast-[1.1]"
                        />

                    </div>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="py-24">
            <div class="container mx-auto px-6 lg:px-12">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-6">
                    <div>
                        <h2 class="text-3xl font-bold text-[#F5F3FF] tracking-tight mb-2">Pilares Fundamentales</h2>
                        <div class="h-1 w-24 bg-[#5B9BD5] mt-4"></div>
                    </div>
                    <p class="text-[#C4BFE0] max-w-md text-sm leading-relaxed text-right md:text-left">
                        Nuestro modelo pedagógico se sustenta en 8 dimensiones clave que garantizan una formación integral de vanguardia.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {#each features as feature, i}
                        <div
                            class="rounded-[20px] border border-[rgba(255,255,255,0.12)] bg-[rgba(28,44,64,0.45)] backdrop-blur-2xl p-8 group hover:bg-[rgba(91,155,213,0.08)] transition-colors duration-300 flex flex-col h-full relative overflow-hidden"
                        >
                            <div class="mb-6 text-[#5B9BD5] bg-[#5B9BD5]/15 w-12 h-12 flex items-center justify-center rounded-2xl group-hover:bg-[#5B9BD5] group-hover:text-white transition-all duration-300">
                                <feature.icon size={24} strokeWidth={1.5} />
                            </div>

                            <h3 class="text-lg font-bold text-[#F5F3FF] mb-3 group-hover:text-[#2A66AC] transition-colors">{feature.title}</h3>
                            <p class="text-sm text-[#C4BFE0] leading-relaxed max-w-[90%]">
                                {feature.description}
                            </p>

                            <!-- Decorative corner -->
                            <div class="absolute top-0 right-0 w-8 h-8 flex items-start justify-end p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <div class="w-1.5 h-1.5 bg-[#5B9BD5] rounded-full"></div>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        </div>
    </main>

    <!-- Institutional Footer -->
    <footer class="relative z-10 border-t border-[rgba(255,255,255,0.12)] bg-[rgba(28,44,64,0.45)] backdrop-blur-2xl pt-16 pb-8">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="grid md:grid-cols-4 gap-12 mb-12">
                <div class="space-y-4 col-span-2">
                    <div class="flex items-center gap-2">
                        <AppLogoIcon class="size-5 fill-current text-[#F5F3FF]" />
                        <span class="font-bold text-[#F5F3FF] tracking-tight">UTAMed</span>
                    </div>
                    <p class="text-[#C4BFE0] text-sm max-w-xs leading-relaxed">
                        Universidad de Tarapacá.<br/>
                        Escuela de Diseño e Innovación Tecnológica.<br/>
                        Centro de Innovación Tecnoeducativa (CITE).
                    </p>
                </div>

                <div>
                    <h4 class="font-bold text-[#F5F3FF] text-sm mb-4 uppercase tracking-wider">Enlaces</h4>
                    <ul class="space-y-2 text-sm text-[#C4BFE0]">
                        <li><a href="#" class="rounded-sm transition-colors hover:text-[#5B9BD5] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(91,155,213,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0d1626]">Portal de Estudiantes</a></li>
                        <li><a href="#" class="rounded-sm transition-colors hover:text-[#5B9BD5] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(91,155,213,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0d1626]">Biblioteca Digital</a></li>
                        <li><a href="#" class="rounded-sm transition-colors hover:text-[#5B9BD5] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(91,155,213,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0d1626]">Calendario Académico</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-[#F5F3FF] text-sm mb-4 uppercase tracking-wider">Ayuda</h4>
                    <ul class="space-y-2 text-sm text-[#C4BFE0]">
                        <li><a href="mailto:cite@gestion.uta.cl" class="rounded-sm transition-colors hover:text-[#5B9BD5] font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(91,155,213,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0d1626]">cite@gestion.uta.cl</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-[rgba(255,255,255,0.12)] pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-[#C4BFE0]">
                <p>&copy; 2026 Universidad de Tarapacá. Arica - Chile.</p>
                <div class="flex gap-6">
                    <span>Acreditada por CNA-Chile</span>
                </div>
            </div>
        </div>
    </footer>
</div>
