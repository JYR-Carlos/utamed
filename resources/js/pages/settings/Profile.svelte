<script lang="ts">
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import HeadingSmall from '@/components/custom/common/HeadingSmall.svelte';
    import InputError from '@/components/custom/common/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';
    import { titleCase } from '@/lib/utils';
    import { edit } from '@/routes/profile';
    import { type BreadcrumbItem, type User } from '@/types';
    import type { ProfileFormSnippetProps } from '@/types/forms';
    import { Form, Link, page } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';

    interface Props {
        mustVerifyEmail: boolean;
        status?: string;
    }

    let { mustVerifyEmail, status }: Props = $props();

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Perfil',
            href: edit().url,
        },
    ];

    const user = $page.props.auth.user as User;

    // El nombre completo, no sólo nombre1: el servidor reparte lo que llegue
    // entre las cuatro columnas, y con sólo el primer nombre borraba los apellidos.
    const nombreCompleto = titleCase([user.nombre1, user.nombre2, user.apellido1, user.apellido2].filter(Boolean).join(' '));
</script>

<svelte:head>
    <title>Perfil</title>
</svelte:head>

<AppLayout breadcrumbs={breadcrumbItems}>
    <SettingsLayout>
        <div class="flex flex-col space-y-6">
            <HeadingSmall title="Información del perfil" description="Actualiza tu nombre y tu correo electrónico" />

            <Form {...ProfileController.update.form()} options={{ preserveScroll: true }} class="space-y-6">
                {#snippet children({ errors, processing, recentlySuccessful }: ProfileFormSnippetProps)}
                    <div class="grid gap-2">
                        <Label for="name">Nombre completo</Label>
                        <Input name="name" id="name" class="mt-1 block w-full" defaultValue={nombreCompleto} required autocomplete="name" placeholder="Nombres y apellidos" />
                        <InputError class="mt-2" message={errors.name} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            name="email"
                            class="mt-1 block w-full"
                            defaultValue={user.email ?? ''}
                            required
                            autocomplete="username"
                            placeholder="Correo electrónico"
                        />
                        <InputError class="mt-2" message={errors.email} />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>Guardar</Button>

                        {#if recentlySuccessful}
                            <p class="text-sm text-neutral-600" transition:fade={{ duration: 150 }}>Guardado.</p>
                        {/if}
                    </div>
                {/snippet}
            </Form>
        </div>
    </SettingsLayout>
</AppLayout>
