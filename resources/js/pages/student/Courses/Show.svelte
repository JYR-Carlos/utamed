<script lang="ts">
    import StudentLayout from '@/layouts/StudentLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Undo2 } from 'lucide-svelte';
    import { Link } from '@inertiajs/svelte';

    interface Props {
        id_curso: number;
    }

    let { id_curso }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/estudiante/dashboard' },
        { title: 'Mis Cursos', href: '/estudiante/cursos' },
        { title: 'Detalle Curso', href: `/estudiante/cursos/${id_curso}` }
    ];
</script>

<StudentLayout {breadcrumbs}>
    <div class="container mx-auto px-6 py-8">
        <div class="mb-6 flex items-center gap-4">
            <Link href="/estudiante/cursos" class="bg-white p-2 rounded-full shadow-sm hover:shadow-md transition-shadow text-slate-600">
                <Undo2 size={20} />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Detalle del Curso</h1>
                <p class="text-slate-500">ID: {id_curso}</p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-8 text-center shadow-sm">
            <p class="text-slate-600 mb-4">La vista de detalle del curso está en construcción.</p>
            <p class="text-slate-500 text-sm">Aquí podrás ver actividades, mensajes y material del curso.</p>
        </div>
    </div>
</StudentLayout>
