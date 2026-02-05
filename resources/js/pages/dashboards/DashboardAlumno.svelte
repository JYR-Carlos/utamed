<script lang="ts">
    /**
     * Widget de dashboard para estudiantes/alumnos.
     * 
     * Componente reutilizable que muestra opciones y accesos rápidos
     * específicos para estudiantes en el dashboard principal.
     */
    /**
     * Props recibidas.
     */
    interface Props {
        /** Datos del usuario estudiante autenticado */
        user: any;
    }
    let { user }: Props = $props();
</script>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Alumno Widgets -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Mis Notas</h3>
        <p class="text-slate-600">Resumen de calificaciones del semestre.</p>
        <div class="mt-4 flex gap-2">
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-sm font-medium">Promedio: 6.2</span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Próximas Evaluaciones</h3>
        <ul class="space-y-2 text-slate-600">
             <li>• Anatomía - Prueba 2 (Lunes 10)</li>
             <li>• Bioquímica - Laboratorio (Miércoles 12)</li>
        </ul>
    </div>
</div>
