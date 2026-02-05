<script lang="ts">
    /**
     * Widget de dashboard para docentes.
     * 
     * Componente reutilizable que muestra opciones y accesos rápidos
     * específicos para docentes en el dashboard principal.
     */
    import { Link } from '@inertiajs/svelte';
    
    /**
     * Props recibidas.
     */
    interface Props {
        /** Datos del usuario docente autenticado */
        user: any;
    }
    let { user }: Props = $props();
</script>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Docente Widgets -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Mis Asignaturas</h3>
        <p class="text-slate-600">Gestión de cursos y alumnos.</p>
        <Link href="/docente/cursos" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            Ver Cursos
        </Link>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Horario Docente</h3>
        <p class="text-slate-600">Próxima clase: Hoy 14:30 hrs.</p>
    </div>
</div>
