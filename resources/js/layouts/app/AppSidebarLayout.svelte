<script lang="ts">
  import AppContent from '@/components/custom/layout/AppContent.svelte';
  import AppShell from '@/components/custom/layout/AppShell.svelte';
  import RoleSidebar from '@/components/custom/layout/RoleSidebar.svelte';
  import AppSidebarHeader from '@/components/custom/layout/AppSidebarHeader.svelte';
  import PageContentWrapper from '@/components/custom/layout/PageContentWrapper.svelte';
  import { Sidebar } from '@/components/ui/sidebar';

  import type { BreadcrumbItem } from '@/types';
  import type { Snippet } from 'svelte';

  interface Props {
    breadcrumbs?: BreadcrumbItem[];
    children?: Snippet;
  }

  let { breadcrumbs = [], children }: Props = $props();
  let showHeader = $state(true);
  let showSidebar = $state(true);
</script>

<AppShell variant="sidebar">
  <div class="flex h-svh flex-1 overflow-hidden">
    <!-- Sidebar desktop -->
    {#if showSidebar}
      <Sidebar
        collapsible="none"
        class="hidden lg:flex border-r border-slate-200 transition-all duration-300"
      >
        <RoleSidebar />
      </Sidebar>
    {:else}
      <!-- Show Sidebar Button (cuando está oculto) -->
      <button
        onclick={() => (showSidebar = true)}
        class="hidden lg:flex items-center justify-center w-16 bg-indigo-50 hover:bg-indigo-100 border-r border-slate-200 transition-all"
        title="Mostrar sidebar"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          class="text-indigo-600"
        >
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      </button>
    {/if}

    <!-- Main Content -->
    <AppContent variant="sidebar" class="flex-1 min-w-0 bg-white overflow-y-auto relative">
      {#if showHeader}
        <AppSidebarHeader {breadcrumbs} bind:showHeader bind:showSidebar />
      {:else}
        <!-- Show Header Button (cuando está oculto) -->
        <button
          onclick={() => (showHeader = true)}
          class="fixed top-4 left-4 lg:left-auto lg:right-4 z-40 flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-all shadow-lg"
          title="Mostrar encabezado"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <polyline points="18 15 12 9 6 15"></polyline>
          </svg>
        </button>
      {/if}
      <PageContentWrapper>
        {@render children?.()}
      </PageContentWrapper>
    </AppContent>
  </div>
</AppShell>
