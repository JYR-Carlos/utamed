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
            title: 'Construcción Social',
            description: 'Aprendizaje colaborativo en comunidad.',
            icon: Users2,
        }
    ];
</script>

<svelte:head>
    <title>UTAMed | Entorno Virtual de Aprendizaje</title>
</svelte:head>

<div class="min-h-screen bg-white text-slate-900 font-sans selection:bg-indigo-100 selection:text-indigo-900">
    <!-- Grid Background Pattern -->
    <div class="fixed inset-0 z-0 opacity-[0.03] pointer-events-none" 
        style="background-image: radial-gradient(#4f46e5 1px, transparent 1px); background-size: 32px 32px;">
    </div>

    <!-- Navigation -->
    <nav class="fixed top-0 z-50 w-full bg-white/90 backdrop-blur-md border-b border-slate-100">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-12">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center bg-indigo-600 rounded-none transform rotate-3 shadow-sm">
                    <AppLogoIcon class="size-6 fill-current text-white -rotate-3" />
                </div>
                <div class="flex flex-col leading-none">
                    <span class="text-lg font-bold tracking-tight text-slate-900">UTAMed</span>
                    <span class="text-[0.6rem] uppercase tracking-widest text-slate-500 font-medium">Facultad de Diseño</span>
                </div>
            </div>

            <div class="flex items-center gap-6 text-sm font-medium">
                {#if user}
                    <Link href={dashboard()} class="text-indigo-600 hover:text-indigo-800 transition-colors flex items-center gap-2 group">
                        Ir al Dashboard
                        <ArrowRight size={16} class="group-hover:translate-x-1 transition-transform" />
                    </Link>
                {:else}
                    <div class="hidden md:flex gap-6 text-slate-500">
                        <span class="cursor-default hover:text-slate-900 transition-colors">Programas</span>
                        <span class="cursor-default hover:text-slate-900 transition-colors">Investigación</span>
                        <span class="cursor-default hover:text-slate-900 transition-colors">Comunidad</span>
                    </div>
                    <Link
                        href={login()}
                        class="px-6 py-2 bg-slate-900 text-white text-xs uppercase tracking-wider font-bold hover:bg-indigo-600 transition-colors rounded-sm"
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
                    <div class="inline-flex items-center gap-3 border-b border-indigo-600 pb-1 w-fit">
                        <GraduationCap size={16} class="text-indigo-600" />
                        <span class="text-xs font-bold uppercase tracking-widest text-indigo-900">Modelo Educativo 2026</span>
                    </div>

                    <h1 class="text-5xl lg:text-7xl font-bold tracking-tight leading-[1.05] text-slate-900">
                        Entorno Virtual de <br/>
                        <span class="text-indigo-600">Enseñanza <span class="font-light text-slate-400">&</span> Aprendizaje.</span>
                    </h1>

                    <p class="text-lg text-slate-600 max-w-lg leading-relaxed border-l-2 border-slate-200 pl-6">
                        Una plataforma que redefine la experiencia académica mediante la integración de tecnología, diseño y pedagogía.
                    </p>

                    <div class="flex items-center gap-4 pt-4">
                        <Link
                            href={user ? dashboard() : login()}
                            class="inline-flex h-12 items-center px-8 bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-200"
                        >
                            {user ? 'Continuar al Panel' : 'Ingresar al Portal'}
                        </Link>
                        {#if !user}
                        <div class="text-xs text-slate-400 max-w-[150px] leading-tight">
                            Acceso exclusivo para docentes y estudiantes.
                        </div>
                        {/if}
                    </div>
                </div>

                <!-- Structured Image / Right Column -->
                <div class="lg:col-span-6 relative" in:fly={{ x: 50, duration: 1000, delay: 200 }}>
                    <!-- Decorative Graphic Elements behind -->
                    <div class="absolute -top-12 -right-12 w-64 h-64 bg-slate-100 rounded-full mix-blend-multiply opacity-50"></div>
                    <div class="absolute -bottom-8 -left-8 w-40 h-40 bg-indigo-50 rounded-full mix-blend-multiply opacity-50"></div>
                    
                    <!-- Main Image Container -->
                    <div class="relative aspect-[4/3] bg-slate-200 overflow-hidden shadow-2xl shadow-slate-200 border-8 border-white">
                        <img 
                            src="/students.jpg" 
                            alt="Estudiantes de Diseño UTAMed" 
                            class="w-full h-full object-cover filter saturate-[.8] contrast-[1.1]"
                        />
                        <!-- Overlay Text -->
                        <div class="absolute bottom-0 left-0 bg-white/95 backdrop-blur-sm p-6 max-w-xs border-t border-r border-slate-100">
                            <p class="text-sm font-serif italic text-slate-600">
                                "El diseño no es solo lo que se ve y se siente. El diseño es cómo funciona."
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Grid (Swiss Grid Style) -->
        <div class="bg-slate-50 border-y border-slate-200 py-24">
            <div class="container mx-auto px-6 lg:px-12">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-6">
                    <div>
                        <h2 class="text-3xl font-bold text-slate-900 tracking-tight mb-2">Pilares Fundamentales</h2>
                        <div class="h-1 w-24 bg-indigo-600 mt-4"></div>
                    </div>
                    <p class="text-slate-500 max-w-md text-sm leading-relaxed text-right md:text-left">
                        Nuestro modelo pedagógico se sustenta en 8 dimensiones clave que garantizan una formación integral de vanguardia.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-px bg-slate-200 border border-slate-200">
                    {#each features as feature, i}
                        <div 
                            class="bg-white p-8 group hover:bg-indigo-50/50 transition-colors duration-300 flex flex-col h-full relative overflow-hidden"
                        >
                            <div class="mb-6 text-indigo-600 bg-indigo-50 w-12 h-12 flex items-center justify-center rounded-sm group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                <feature.icon size={24} strokeWidth={1.5} />
                            </div>
                            
                            <h3 class="text-lg font-bold text-slate-900 mb-3 group-hover:text-indigo-700 transition-colors">{feature.title}</h3>
                            <p class="text-sm text-slate-500 leading-relaxed max-w-[90%]">
                                {feature.description}
                            </p>

                            <!-- Decorative corner -->
                            <div class="absolute top-0 right-0 w-8 h-8 flex items-start justify-end p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <div class="w-1.5 h-1.5 bg-indigo-600 rounded-full"></div>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        </div>
    </main>

    <!-- Institutional Footer -->
    <footer class="bg-white border-t border-slate-200 pt-16 pb-8">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="grid md:grid-cols-4 gap-12 mb-12">
                <div class="space-y-4 col-span-2">
                    <div class="flex items-center gap-2">
                        <AppLogoIcon class="size-5 fill-current text-slate-900" />
                        <span class="font-bold text-slate-900 tracking-tight">UTAMed</span>
                    </div>
                    <p class="text-slate-500 text-sm max-w-xs leading-relaxed">
                        Universidad de Tarapacá.<br/>
                        Comprometidos con la excelencia académica y la innovación educativa en la región.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-bold text-slate-900 text-sm mb-4 uppercase tracking-wider">Enlaces</h4>
                    <ul class="space-y-2 text-sm text-slate-500">
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Portal de Estudiantes</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Biblioteca Digital</a></li>
                        <li><a href="#" class="hover:text-indigo-600 transition-colors">Calendario Académico</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-slate-900 text-sm mb-4 uppercase tracking-wider">Ayuda</h4>
                    <ul class="space-y-2 text-sm text-slate-500">
                        <li><a href="mailto:soporte@uta.cl" class="hover:text-indigo-600 transition-colors font-medium">soporte@uta.cl</a></li>
                        <li><span class="text-slate-400">Mesa de Ayuda D.I.T.</span></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-slate-400">
                <p>&copy; 2026 Universidad de Tarapacá. Arica - Chile.</p>
                <div class="flex gap-6">
                    <span>Acreditada por CNA-Chile</span>
                </div>
            </div>
        </div>
    </footer>
</div>
