<script lang="ts">
    import NavFooter from '@/components/custom/navigation/NavFooter.svelte';
    import NavUser from '@/components/custom/navigation/NavUser.svelte';
    import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarGroup, SidebarGroupLabel, SidebarGroupContent, SidebarInput, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubItem, SidebarMenuSubButton, SidebarMenuBadge } from '@/components/ui/sidebar';
    import { Link, page } from '@inertiajs/svelte';
    import { BookOpen, LayoutGrid, Users, Calendar, Library, LifeBuoy, FileText, MessageSquare, Folder, ClipboardList } from 'lucide-svelte';
    import AppLogo from '@/components/custom/layout/AppLogo.svelte';
    import AppShell from '@/components/custom/layout/AppShell.svelte';
    import AppContent from '@/components/custom/layout/AppContent.svelte';
    import AppSidebarHeader from '@/components/custom/layout/AppSidebarHeader.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();

    const cursos = $derived<any[]>((page as any)?.props?.cursos ?? []);
    const parseDate = (d: string | undefined) => d ? new Date(d) : new Date();
    const currentYear = $derived(cursos.length ? parseDate(cursos[0]?.fecha_inicio).getFullYear() : new Date().getFullYear());
    const cursosActuales = $derived(cursos.filter(c => parseDate(c.fecha_inicio).getFullYear() === currentYear));
    const semestre1 = $derived(cursosActuales.filter(c => parseDate(c.fecha_inicio).getMonth() <= 5));
    const semestre2 = $derived(cursosActuales.filter(c => parseDate(c.fecha_inicio).getMonth() > 5));
</script>

<AppShell variant="sidebar">
    <Sidebar collapsible="icon" variant="inset" class="soft-sidebar">
        <SidebarHeader class="border-b border-sidebar-border/50 py-4 px-6">
            <SidebarMenu>
                <SidebarMenuItem>
                    <div class="flex items-center gap-3">
                        <AppLogo />
                        <div class="logo-text group-data-[state=collapsed]:hidden flex flex-col">
                            <span class="font-bold text-slate-900 text-lg md:text-xl lg:text-2xl leading-none">UTAMED</span>
                            <span class="text-[10px] md:text-xs lg:text-xs text-slate-500 font-bold tracking-wider uppercase mt-1">Portal Estudiante</span>
                        </div>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-2 pt-4 space-y-2">
            <SidebarInput placeholder="Buscar cursos, recursos..." />

            <SidebarGroup>
                <SidebarGroupLabel>ACADEMIC</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton>
                                <Link href="/estudiante/cursos" class="flex items-center gap-2 w-full">
                                    <LayoutGrid class="h-4 w-4" />
                                    <span>My History & Courses</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem>
                            <SidebarMenuButton>
                                <div class="flex items-center gap-2 w-full">
                                    <Calendar class="h-4 w-4" />
                                    <span>{currentYear}</span>
                                </div>
                            </SidebarMenuButton>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem>
                                    <SidebarMenuSubButton>
                                        <span class="text-xs text-slate-500">Semester 1</span>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>

                                {#each semestre1 as c (c.id_curso)}
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton>
                                            <Link href={`/estudiante/cursos/${c.id_curso}`} class="flex items-center gap-2 w-full">
                                                <BookOpen class="h-4 w-4" />
                                                <span>{c.asignatura_nombre}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                        <SidebarMenuSub>
                                            <SidebarMenuSubItem>
                                                <SidebarMenuSubButton>
                                                    <FileText class="h-4 w-4" />
                                                    <span>Syllabus</span>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                            <SidebarMenuSubItem>
                                                <SidebarMenuSubButton>
                                                    <Users class="h-4 w-4" />
                                                    <span>Professor</span>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                            <SidebarMenuSubItem>
                                                <SidebarMenuSubButton>
                                                    <MessageSquare class="h-4 w-4" />
                                                    <span>Messages</span>
                                                    <SidebarMenuBadge>2 new</SidebarMenuBadge>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                            <SidebarMenuSubItem>
                                                <SidebarMenuSubButton>
                                                    <Folder class="h-4 w-4" />
                                                    <span>Resources</span>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                            <SidebarMenuSubItem>
                                                <SidebarMenuSubButton>
                                                    <ClipboardList class="h-4 w-4" />
                                                    <span>Activities/Grades</span>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        </SidebarMenuSub>
                                    </SidebarMenuSubItem>
                                {/each}

                                <SidebarMenuSubItem>
                                    <SidebarMenuSubButton>
                                        <span class="text-xs text-slate-500">Semester 2</span>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>

                                {#each semestre2 as c (c.id_curso)}
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton>
                                            <Link href={`/estudiante/cursos/${c.id_curso}`} class="flex items-center gap-2 w-full">
                                                <BookOpen class="h-4 w-4" />
                                                <span>{c.asignatura_nombre}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                {/each}
                            </SidebarMenuSub>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup>
                <SidebarGroupLabel>TEACHING ASSISTANT (Ayudantía)</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton>
                                <div class="flex items-center gap-2 w-full">
                                    <Users class="h-4 w-4" />
                                    <span>My Assistantships</span>
                                </div>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <div class="border border-dashed border-slate-200 rounded-lg p-3 text-xs text-slate-500 ml-3 mr-3 mt-2">No active assistantships assigned.</div>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton>
                                <Link href="/calendar" class="flex items-center gap-2 w-full">
                                    <Calendar class="h-4 w-4" />
                                    <span>Calendar</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton>
                                <Link href="/library" class="flex items-center gap-2 w-full">
                                    <Library class="h-4 w-4" />
                                    <span>Library</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton>
                                <Link href="/support" class="flex items-center gap-2 w-full">
                                    <LifeBuoy class="h-4 w-4" />
                                    <span>Support</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="p-4 mt-auto">
            <div class="footer-card group-data-[collapsible=icon]:hidden">
                <p class="text-xs font-bold text-slate-700">UTAMED Support</p>
                <p class="text-[10px] text-slate-500 mt-1 leading-tight">Accede a nuestra documentación centralizada.</p>
                <div class="mt-3 flex justify-end">
                    <span class="text-xl">🚀</span>
                </div>
            </div>
            <div class="mt-4">
                <NavUser />
            </div>
        </SidebarFooter>
    </Sidebar>

    <AppContent variant="sidebar" class="overflow-x-hidden">
        <AppSidebarHeader {breadcrumbs} />
        {@render children?.()}
    </AppContent>
</AppShell>

<style>
    :global(.soft-sidebar) {
        box-shadow: 4px 0 24px -12px rgba(0, 0, 0, 0.08);
    }

    .logo-text {
        display: flex;
        flex-direction: column;
    }

    .footer-card {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 1rem;
        position: relative;
        overflow: hidden;
    }

    .footer-card::after {
        content: '';
        position: absolute;
        top: -10px;
        right: -10px;
        width: 40px;
        height: 40px;
        background: rgba(59, 130, 246, 0.03);
        border-radius: 50%;
    }
</style>
