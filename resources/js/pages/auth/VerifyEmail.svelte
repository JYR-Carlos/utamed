<script lang="ts">
  /**
   * Página para verificar email del usuario.
   *
   * Muestra instrucciones para completar verificación de email
   * y permite reenviar enlace de verificación.
   *
   * Características:
   * - Mensaje de instrucciones de verificación
   * - Botón para reenviar enlace si es necesario
   * - Opción para cambiar email
   * - Logout para cambiar de cuenta
   */
  import TextLink from '@/components/custom/common/TextLink.svelte';
  import { Button } from '@/components/ui/button';
  import AuthLayout from '@/layouts/AuthLayout.svelte';
  import { logout } from '@/routes';
  import { Form } from '@inertiajs/svelte';
  import { LoaderCircle } from 'lucide-svelte';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Mensaje de estado (ej: "Enlace reenviado") */
    status?: string;
  }

  let { status }: Props = $props();
</script>

<svelte:head>
  <title>Verify Email</title>
</svelte:head>

<AuthLayout title="Verify email" description="Please verify your email address by clicking on the link we just emailed to you.">
  {#if status === 'verification-link-sent'}
    <div class="mb-4 text-center text-sm font-medium text-green-600">
      A new verification link has been sent to the email address you provided during registration.
    </div>
  {/if}

  <Form method="post" action="/email/verification-notification" className="space-y-6 text-center">
    {#snippet children({ processing }: { processing: boolean })}
      <Button type="submit" disabled={processing} variant="secondary">
        {#if processing}
          <LoaderCircle class="h-4 w-4 animate-spin" />
        {/if}
        Resend verification email
      </Button>

      <TextLink href={logout()} method="post" as="button" class="mx-auto block text-sm">Log out</TextLink>
    {/snippet}
  </Form>
</AuthLayout>
