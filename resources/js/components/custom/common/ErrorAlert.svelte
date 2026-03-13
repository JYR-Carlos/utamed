<script lang="ts">
  /**
   * Componente de alerta de error para login diferenciado.
   *
   * Muestra errores de autenticación con:
   * - Ícono contextualizado (AlertCircle, Lock, Ban, Mail, Clock)
   * - Mensaje amigable en español
   * - Tooltip con detalles técnicos más específicos
   * - Color de fondo y borde diferenciados
   * - información de retry para rate limit
   */

  import { AlertCircle, Lock, Ban, Mail, Clock } from 'lucide-svelte';

  type ErrorCode = 'RUT_NOT_FOUND' | 'PASSWORD_INCORRECT' | 'USER_INACTIVE' | 'EMAIL_NOT_VERIFIED' | 'RATE_LIMIT_EXCEEDED';

  interface Props {
    /** Código de error específico */
    errorCode: ErrorCode;
    /** Segundos restantes para retry (solo para RATE_LIMIT_EXCEEDED) */
    retryAfter?: number;
  }

  let { errorCode, retryAfter }: Props = $props();

  // Mapeo de códigos a detalles visuales y mensajes (computed reactively)
  const errorDetails = $derived.by(() => ({
    RUT_NOT_FOUND: {
      message: 'El RUT no existe en el sistema',
      tooltip: 'Verifica que el RUT sea correcto. Intenta sin espacios (ej: 12345678-9)',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-300',
      icon: AlertCircle,
    },
    PASSWORD_INCORRECT: {
      message: 'La contraseña es incorrecta',
      tooltip: 'Verifica la contraseña. Si la olvidaste, usa "¿Olvidaste tu contraseña?"',
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-300',
      icon: Lock,
    },
    USER_INACTIVE: {
      message: 'Tu cuenta ha sido desactivada',
      tooltip: 'Tu cuenta ya no está activa. Contacta con soporte@uta.cl para reactivarla.',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-300',
      icon: Ban,
    },
    EMAIL_NOT_VERIFIED: {
      message: 'Debes verificar tu email',
      tooltip: 'Se ha enviado un enlace de verificación a tu correo institucional. Revisa tu bandeja de entrada.',
      bgColor: 'bg-amber-50',
      borderColor: 'border-amber-300',
      icon: Mail,
    },
    RATE_LIMIT_EXCEEDED: {
      message: `Demasiados intentos fallidos${retryAfter ? `. Intenta en ${retryAfter}s` : ''}`,
      tooltip: 'Por tu seguridad, hemos limitado los intentos de login. Espera un momento e intenta de nuevo.',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-300',
      icon: Clock,
    },
  }));

  const details = $derived(errorDetails[errorCode]);
  const Icon = $derived(details.icon);
</script>

<div class={`flex items-start gap-3 p-4 rounded-xl border-2 ${details.bgColor} ${details.borderColor}`} role="alert" aria-live="polite">
  <div class="flex-shrink-0 mt-0.5">
    <Icon class="h-5 w-5 text-red-600" />
  </div>

  <div class="flex-1">
    <p class="text-sm font-semibold text-red-900">{details.message}</p>
    <p class="text-xs text-red-700 mt-1 leading-relaxed">{details.tooltip}</p>
  </div>
</div>
