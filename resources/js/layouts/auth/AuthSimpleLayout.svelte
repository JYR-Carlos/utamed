<script lang="ts">
  import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';
  import { home } from '@/routes';
  import { Link } from '@inertiajs/svelte';
  import type { Snippet } from 'svelte';

  interface Props {
    title: string;
    description: string;
    children?: Snippet;
  }

  let { title, description, children }: Props = $props();
</script>

<div
  class="flex min-h-screen flex-col items-center justify-center bg-gray-50 py-6 md:p-10 text-gray-900"
>
  <div class="w-full max-w-sm bg-white p-8 rounded-lg shadow border border-gray-200">
    <div class="flex flex-col gap-8">
      <div class="flex flex-col items-center gap-4">
        <Link href={home()} class="flex flex-col items-center gap-2 font-medium">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-600">
            <AppLogoIcon class="size-8 fill-current text-white" />
          </div>
        </Link>
        <div class="space-y-2 text-center">
          <h1 class="text-2xl font-bold tracking-tight text-gray-900">{title}</h1>
          <p class="text-center text-sm text-gray-500">{description}</p>
        </div>
      </div>
      {@render children?.()}
    </div>
  </div>
  <div class="mt-8 text-center text-xs text-gray-400">&copy; 2026 Universidad de Tarapacá</div>
</div>
