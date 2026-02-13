<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { BookOpen } from 'lucide-svelte';

    /**
     * Widget de dashboard para ayudantes de cátedra.
     */
    
    interface Props {
        /** Datos del usuario ayudante autenticado */
        user: any;
        courses?: Array<{
            id_curso: number;
            nombre: string;
            cod_curso: string;
            asignatura_nombre: string;
        }>;
    }
    let { user, courses = [] }: Props = $props();
</script>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Ayudante Widgets -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4 flex justify-between items-center">
            <span>Mis Ayudantías</span>
            <span class="text-xs font-normal text-slate-500 bg-slate-100 px-2 py-1 rounded-full">{courses.length} Cursos</span>
        </h3>
        
        {#if courses.length > 0}
            <div class="space-y-3">
                {#each courses as curso}
                    <div class="p-3 border border-slate-100 rounded-lg hover:bg-slate-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-slate-900 text-sm">{curso.asignatura_nombre}</h4>
                                <p class="text-xs text-slate-500">{curso.nombre}</p>
                            </div>
                            <Link href={`/ayudante/cursos/${curso.id_curso}`} class="text-xs text-blue-600 hover:underline font-medium">
                                Ver
                            </Link>
                        </div>
                    </div>
                {/each}
            </div>
             <div class="mt-4 pt-3 border-t border-slate-100">
                <Link href="/ayudante/cursos" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center justify-center">
                    Ver todas las ayudantías →
                </Link>
            </div>
        {:else}
            <div class="text-center py-6 text-slate-500">
                <BookOpen class="mx-auto h-8 w-8 text-slate-300 mb-2" />
                <p>No tienes ayudantías asignadas.</p>
            </div>
        {/if}
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Tareas Pendientes</h3>
        <div class="text-center py-8 text-slate-500 bg-slate-50 rounded-lg border border-dashed border-slate-200">
            <p>No tienes tareas pendientes por ahora.</p>
        </div>
    </div>
</div>
