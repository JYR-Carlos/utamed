# modules/shared/

Utilidades, hooks y constantes transversales usadas por varios módulos de recursos.

## Propósito

Concerns transversales que no pertenecen a una entidad concreta.

## Hogares canónicos de código compartido

> Esta sección documenta **dónde vive hoy** cada tipo de utilidad compartida.
> Antes de crear un helper nuevo, búscalo aquí para no duplicarlo. Si reimplementas
> uno de estos en un componente, prefiere importar el canónico.

| Tipo | Ubicación canónica | Notas |
|---|---|---|
| Formateo de dominio (fechas, estados, nombres, bytes, iniciales) | `@/utils/formatters` | **Locale estándar del producto: es-CL.** Fechas-solo-día vía `parseFechaSoloDia` para evitar el desfase UTC→hora local de Chile. |
| `cn` (merge de clases Tailwind) y type-utils | `@/lib/utils` | Convención shadcn-svelte. |
| Validadores (RUT, email, teléfono, password…) | `@/lib/validators` | |
| Permisos (constantes y helpers) | `@/lib/constants/permissions`, `@/lib/composables/usePermissions`, `@/services/permissionValidator` | |
| Listas filtrables | `@/lib/composables/useFilteredList` | |
| Iniciales de persona (primera + última palabra) | `getInitials` en `@/hooks/useInitials` | Para las **dos primeras** palabras usar `initials` de `@/utils/formatters`. |
| Constantes de administración | `@/constants/admin` | |

### Fechas: cuándo usar cada helper de `@/utils/formatters`

- `formatDate` → numérico corto es-CL: `15/01/2024`.
- `formatFechaCorta` → `03 mar 2026`.
- `formatFechaTextoLargo` → `3 de marzo de 2026` (sin día de la semana).
- `formatFechaLarga` → `lunes, 03 de marzo de 2026`.
- `formatFechaHora` → `03 mar 2026, 14:30` (cuando importa la hora).

## NO usar para

- Lógica específica de rol (los roles se manejan en `pages/{role}/` y rutas).
- Componentes específicos de entidad (usar `modules/resources/{entity}/`).
- Autorización del backend (usar Policies de Laravel).
