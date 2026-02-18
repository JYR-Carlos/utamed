<script lang="ts">
    /**
     * Dashboard del docente — Teacher Portal.
     * Muestra estadísticas, acciones rápidas y recursos del docente.
     * La navegación de cursos ahora vive en RoleSidebar.
     */
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Link, page } from '@inertiajs/svelte';
    import {
        BookOpen,
        Users,
        Award,
        ClipboardList,
        MessageSquare,
        Calendar,
        GraduationCap,
        BarChart3,
        Bell,
        ArrowRight,
        CheckCircle2,
        Clock,
        Sparkles,
    } from 'lucide-svelte';

    interface Curso {
        id_curso: number;
        nombre: string;
        cod_curso?: string;
        tiene_programa?: boolean;
    }

    interface Props {
        docente: {
            id_docente: number;
            grado?: string;
            titulo?: string;
            cargo?: string;
            id_usuario: number;
        };
        stats: {
            total_cursos: number;
            nombre_completo: string;
        };
        cursos?: Curso[];
    }

    let { docente, stats, cursos = [] }: Props = $props();

    // Cursos desde shared props como fallback (para el panel de estado de programas)
    let sharedCourses = $derived(($page.props.auth as any)?.docente_courses ?? []);
    let allCursos = $derived(cursos.length > 0 ? cursos : sharedCourses);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/docente/dashboard' }
    ];

    // Periodo académico actual
    const now = new Date();
    const periodoActual = `${now.getFullYear()} · Semestre ${now.getMonth() < 6 ? 1 : 2}`;
</script>

<DocenteLayout {breadcrumbs}>
    <main class="dash">

        <!-- ── Header ─────────────────────────────────────────── -->
        <header class="dash-header">
            <div class="dash-header-left">
                <div class="dash-period-badge">
                    <Sparkles size={11} />
                    {periodoActual}
                </div>
                <h1 class="dash-title">Portal Docente</h1>
                <p class="dash-subtitle">
                    Bienvenido, <strong>{stats.nombre_completo}</strong>{#if docente.grado} — {docente.grado}{/if}
                </p>
            </div>
            <div class="dash-header-actions">
                <button class="hdr-btn" disabled title="Próximamente">
                    <Bell size={15} />
                    <span>Notificaciones</span>
                </button>
                <button class="hdr-btn hdr-btn--primary" disabled title="Próximamente">
                    Vista Estudiante
                </button>
            </div>
        </header>

        <!-- ── Metric Cards ───────────────────────────────────── -->
        <div class="metrics">
            <div class="metric metric--indigo">
                <div class="metric-icon">
                    <BookOpen size={20} />
                </div>
                <div class="metric-body">
                    <span class="metric-value">{stats.total_cursos}</span>
                    <span class="metric-label">Cursos Asignados</span>
                </div>
            </div>

            <div class="metric metric--violet">
                <div class="metric-icon">
                    <Award size={20} />
                </div>
                <div class="metric-body">
                    <span class="metric-value metric-value--sm">{docente.grado || '—'}</span>
                    <span class="metric-label">Grado Académico</span>
                </div>
            </div>

            <div class="metric metric--emerald">
                <div class="metric-icon">
                    <Users size={20} />
                </div>
                <div class="metric-body">
                    <span class="metric-value metric-value--sm">{docente.cargo || '—'}</span>
                    <span class="metric-label">Cargo</span>
                </div>
            </div>

            <div class="metric metric--amber">
                <div class="metric-icon">
                    <BarChart3 size={20} />
                </div>
                <div class="metric-body">
                    <span class="metric-value">—</span>
                    <span class="metric-label">Actividades Pendientes</span>
                </div>
            </div>
        </div>

        <!-- ── Main grid ──────────────────────────────────────── -->
        <div class="main-grid">

            <!-- Acciones Rápidas -->
            <section class="actions-section">
                <h2 class="section-heading">Acciones Rápidas</h2>
                <div class="actions-grid">

                    <!-- Mis Cursos — activo -->
                    <Link href="/docente/cursos" class="acard acard--indigo">
                        <div class="acard-top">
                            <div class="acard-icon">
                                <BookOpen size={20} />
                            </div>
                            <span class="acard-count">{stats.total_cursos}</span>
                        </div>
                        <h3 class="acard-title">Mis Cursos</h3>
                        <p class="acard-desc">Gestiona tus cursos, programas y equipos de cátedra</p>
                        <div class="acard-footer">
                            <span>Ver todos</span>
                            <ArrowRight size={13} />
                        </div>
                    </Link>

                    <!-- Inscripciones — activo -->
                    <Link href="/docente/inscripciones" class="acard acard--indigo">
                        <div class="acard-top">
                            <div class="acard-icon">
                                <Users size={20} />
                            </div>
                        </div>
                        <h3 class="acard-title">Inscripciones</h3>
                        <p class="acard-desc">Revisa los estudiantes inscritos en tus secciones</p>
                        <div class="acard-footer">
                            <span>Ver inscripciones</span>
                            <ArrowRight size={13} />
                        </div>
                    </Link>

                    <!-- Asistencia — próximamente -->
                    <button class="acard acard--muted" disabled>
                        <div class="acard-top">
                            <div class="acard-icon">
                                <ClipboardList size={20} />
                            </div>
                            <span class="soon-badge">Próximamente</span>
                        </div>
                        <h3 class="acard-title">Asistencia</h3>
                        <p class="acard-desc">Registra y consulta la asistencia de tus estudiantes</p>
                    </button>

                    <!-- Calificaciones — próximamente -->
                    <button class="acard acard--muted" disabled>
                        <div class="acard-top">
                            <div class="acard-icon">
                                <BarChart3 size={20} />
                            </div>
                            <span class="soon-badge">Próximamente</span>
                        </div>
                        <h3 class="acard-title">Calificaciones</h3>
                        <p class="acard-desc">Ingresa y gestiona las notas de tus estudiantes</p>
                    </button>

                    <!-- Mensajes — próximamente -->
                    <button class="acard acard--muted" disabled>
                        <div class="acard-top">
                            <div class="acard-icon">
                                <MessageSquare size={20} />
                            </div>
                            <span class="soon-badge">Próximamente</span>
                        </div>
                        <h3 class="acard-title">Mensajes</h3>
                        <p class="acard-desc">Comunicación directa con estudiantes y ayudantes</p>
                    </button>

                    <!-- Calendario — próximamente -->
                    <button class="acard acard--muted" disabled>
                        <div class="acard-top">
                            <div class="acard-icon">
                                <Calendar size={20} />
                            </div>
                            <span class="soon-badge">Próximamente</span>
                        </div>
                        <h3 class="acard-title">Calendario</h3>
                        <p class="acard-desc">Visualiza tus clases, evaluaciones y eventos</p>
                    </button>

                </div>
            </section>

            <!-- Panel lateral derecho -->
            <aside class="right-panel">

                <!-- Estado de Programas -->
                <div class="panel-card">
                    <h3 class="panel-card-title">Estado de Programas</h3>
                    <div class="program-list">
                        {#if allCursos.length === 0}
                            <p class="panel-empty">Sin cursos asignados</p>
                        {:else}
                            {#each allCursos.slice(0, 5) as curso (curso.id_curso)}
                                <div class="prog-item">
                                    <div class="prog-info">
                                        <span class="prog-name">{curso.nombre}</span>
                                        {#if curso.cod_curso}
                                            <span class="prog-code">{curso.cod_curso}</span>
                                        {/if}
                                    </div>
                                    {#if curso.tiene_programa}
                                        <span class="prog-status prog-status--ok">
                                            <CheckCircle2 size={11} />
                                            Generado
                                        </span>
                                    {:else}
                                        <span class="prog-status prog-status--pending">
                                            <Clock size={11} />
                                            Pendiente
                                        </span>
                                    {/if}
                                </div>
                            {/each}
                            {#if allCursos.length > 5}
                                <Link href="/docente/cursos" class="panel-see-more">
                                    Ver todos ({allCursos.length}) →
                                </Link>
                            {/if}
                        {/if}
                    </div>
                </div>

                <!-- Recursos Docentes -->
                <div class="panel-card panel-card--gradient">
                    <div class="panel-gradient-hdr">
                        <GraduationCap size={17} />
                        <span>Recursos Docentes</span>
                    </div>
                    <div class="panel-gradient-links">
                        <button class="grad-link" disabled title="Próximamente">
                            Documentación del Sistema <span>→</span>
                        </button>
                        <button class="grad-link" disabled title="Próximamente">
                            Guía de Programas <span>→</span>
                        </button>
                        <button class="grad-link" disabled title="Próximamente">
                            Soporte Técnico <span>→</span>
                        </button>
                    </div>
                </div>

            </aside>
        </div>

    </main>
</DocenteLayout>

<style>
    /* ── Root panel ─────────────────────────────────────────── */
    .dash {
        flex: 1;
        overflow-y: auto;
        padding: 32px 36px;
        display: flex;
        flex-direction: column;
        gap: 28px;
        max-width: 1440px;
        width: 100%;
        margin: 0 auto;
    }

    /* ── Header ─────────────────────────────────────────────── */
    .dash-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .dash-header-left { display: flex; flex-direction: column; gap: 4px; }
    .dash-period-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 700;
        color: #6366f1;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 20px;
        padding: 3px 10px;
        letter-spacing: 0.03em;
        width: fit-content;
        margin-bottom: 4px;
    }
    .dash-title {
        font-size: 28px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.025em;
        line-height: 1.15;
    }
    .dash-subtitle { font-size: 14px; color: #64748b; }
    .dash-subtitle strong { color: #334155; font-weight: 600; }

    .dash-header-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; padding-top: 4px; }
    .hdr-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        cursor: not-allowed;
        opacity: 0.65;
    }
    .hdr-btn--primary {
        background: #6366f1;
        color: #fff;
        border-color: #6366f1;
    }

    /* ── Metric cards ───────────────────────────────────────── */
    .metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }
    .metric {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px 22px;
        border-radius: 16px;
        border: 1px solid transparent;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .metric-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .metric--indigo {
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        border-color: #c7d2fe;
    }
    .metric--indigo .metric-icon { background: #6366f1; color: #fff; }
    .metric--indigo .metric-value { color: #3730a3; }

    .metric--violet {
        background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
        border-color: #ddd6fe;
    }
    .metric--violet .metric-icon { background: #7c3aed; color: #fff; }
    .metric--violet .metric-value { color: #5b21b6; }

    .metric--emerald {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-color: #a7f3d0;
    }
    .metric--emerald .metric-icon { background: #059669; color: #fff; }
    .metric--emerald .metric-value { color: #065f46; }

    .metric--amber {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        border-color: #fde68a;
    }
    .metric--amber .metric-icon { background: #d97706; color: #fff; }
    .metric--amber .metric-value { color: #92400e; }

    .metric-body { display: flex; flex-direction: column; min-width: 0; }
    .metric-value {
        font-size: 24px;
        font-weight: 800;
        line-height: 1.1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .metric-value--sm { font-size: 16px; font-weight: 700; }
    .metric-label {
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 3px;
    }

    /* ── Main grid ──────────────────────────────────────────── */
    .main-grid {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 24px;
        align-items: start;
    }

    /* ── Section heading ────────────────────────────────────── */
    .section-heading {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 14px;
        letter-spacing: -0.01em;
    }

    /* ── Actions grid ───────────────────────────────────────── */
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
    }

    /* All action card styles are :global() because <Link> renders
       an <a> tag that doesn't receive Svelte's scoped attribute */
    :global(.acard) {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 18px;
        border-radius: 14px;
        border: 1px solid transparent;
        text-decoration: none;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        cursor: pointer;
        text-align: left;
        position: relative;
        overflow: hidden;
        font-family: inherit;
    }
    :global(.acard:focus-visible) {
        outline: 2px solid #6366f1;
        outline-offset: 2px;
    }
    :global(.acard:hover:not(.acard--muted)) {
        transform: translateY(-3px) scale(1.01);
        box-shadow: 0 10px 28px rgba(99,102,241,0.15);
    }

    /* Active — indigo */
    :global(.acard--indigo) {
        background: linear-gradient(145deg, #eef2ff 0%, #e0e7ff 100%);
        border-color: #c7d2fe;
        color: #3730a3;
    }
    :global(.acard--indigo .acard-top .acard-icon) {
        background: #6366f1;
        color: #fff;
        border-color: transparent;
    }
    :global(.acard--indigo .acard-title) { color: #312e81; }
    :global(.acard--indigo .acard-desc)  { color: #4338ca; opacity: 0.8; }
    :global(.acard--indigo .acard-footer){ color: #4338ca; }

    /* Muted / disabled */
    :global(.acard--muted) {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #94a3b8;
        cursor: not-allowed;
        opacity: 0.72;
    }
    :global(.acard--muted .acard-icon) {
        background: #f1f5f9;
        border-color: #e2e8f0;
        color: #94a3b8;
    }

    /* Inner elements */
    :global(.acard-top) {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    :global(.acard-icon) {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255,255,255,0.65);
        border: 1px solid rgba(226,232,240,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    :global(.acard-count) {
        font-size: 22px;
        font-weight: 800;
        line-height: 1;
        color: #312e81;
    }
    :global(.acard-title) {
        font-size: 14px;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
    }
    :global(.acard-desc) {
        font-size: 12px;
        line-height: 1.45;
        flex: 1;
        opacity: 0.75;
    }
    :global(.acard-footer) {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 2px;
    }

    .soon-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        background: #fef3c7;
        color: #92400e;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
    }

    /* ── Right panel ────────────────────────────────────────── */
    .right-panel { display: flex; flex-direction: column; gap: 16px; }

    .panel-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .panel-card-title {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 14px;
        letter-spacing: -0.01em;
    }
    .panel-empty { font-size: 12px; color: #94a3b8; text-align: center; padding: 8px 0; }

    .program-list { display: flex; flex-direction: column; gap: 2px; }
    .prog-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .prog-item:last-of-type { border-bottom: none; }
    .prog-info { display: flex; flex-direction: column; min-width: 0; }
    .prog-name {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .prog-code { font-size: 10px; color: #94a3b8; margin-top: 1px; }
    .prog-status {
        display: flex;
        align-items: center;
        gap: 3px;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .prog-status--ok      { background: #d1fae5; color: #065f46; }
    .prog-status--pending { background: #fef3c7; color: #92400e; }

    :global(.panel-see-more) {
        font-size: 12px;
        color: #6366f1;
        font-weight: 600;
        text-decoration: none;
        display: block;
        margin-top: 8px;
    }
    :global(.panel-see-more:hover) { text-decoration: underline; }

    /* Gradient resources card */
    .panel-card--gradient {
        background: linear-gradient(140deg, #4f46e5 0%, #7c3aed 100%);
        border-color: transparent;
        color: #fff;
    }
    .panel-gradient-hdr {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .panel-gradient-links { display: flex; flex-direction: column; gap: 6px; }
    .grad-link {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 9px 11px;
        border-radius: 10px;
        font-size: 12.5px;
        font-weight: 500;
        background: rgba(255,255,255,0.13);
        border: none;
        color: rgba(255,255,255,0.9);
        cursor: not-allowed;
        text-align: left;
        width: 100%;
        transition: background 0.15s;
        font-family: inherit;
    }
    .grad-link[disabled] { opacity: 0.6; }

    /* ── Responsive ─────────────────────────────────────────── */
    @media (max-width: 1400px) {
        .actions-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 1200px) {
        .main-grid { grid-template-columns: 1fr; }
        .right-panel { display: grid; grid-template-columns: 1fr 1fr; }
        .metrics { grid-template-columns: repeat(2, 1fr); }
        .dash { max-width: 100%; padding: 24px 28px; }
    }
    @media (max-width: 900px) {
        .actions-grid { grid-template-columns: repeat(2, 1fr); }
        .dash { padding: 20px; }
    }
    @media (max-width: 640px) {
        .metrics { grid-template-columns: 1fr 1fr; }
        .actions-grid { grid-template-columns: 1fr; }
        .right-panel { grid-template-columns: 1fr; }
        .dash { padding: 16px; gap: 20px; }
        .dash-title { font-size: 22px; }
        .dash-header-actions { display: none; }
    }
    @media (min-width: 1600px) {
        .dash { max-width: 1600px; padding: 36px 44px; }
        .main-grid { grid-template-columns: 1fr 340px; }
    }
    @media (min-width: 1920px) {
        .dash { max-width: 1800px; padding: 40px 56px; }
        .main-grid { grid-template-columns: 1fr 380px; }
        .actions-grid { grid-template-columns: repeat(4, 1fr); }
    }
</style>
