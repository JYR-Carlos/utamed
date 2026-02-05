<script lang="ts">
    interface Props {
        user: any;
    }
    let { user }: Props = $props();
</script>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Ayudante Widgets -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Panel de Ayudantía</h3>
        <p class="text-slate-600">Gestión de ayudantías asignadas.</p>
         <button class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            Registrar Asistencia
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Tareas Pendientes</h3>
        <p class="text-slate-600">Tienes 3 informes por revisar.</p>
    </div>
</div>
