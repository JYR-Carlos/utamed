# Reorganización de Componentes

## Estructura Propuesta

```
resources/js/components/
├── custom/                    # Componentes personalizados reutilizables
│   ├── admin/                # Componentes específicos del módulo admin
│   │   ├── DataTable.svelte
│   │   ├── FormModal.svelte
│   │   ├── DeleteConfirmation.svelte
│   │   ├── CourseTeamModal.svelte
│   │   └── PermissionsModal.svelte
│   │
│   ├── layout/               # Componentes de layout
│   │   ├── AppShell.svelte
│   │   ├── AppHeader.svelte
│   │   ├── AppSidebar.svelte
│   │   ├── AppSidebarHeader.svelte
│   │   ├── AppContent.svelte
│   │   ├── AppLogo.svelte
│   │   └── AppLogoIcon.svelte
│   │
│   ├── navigation/           # Componentes de navegación
│   │   ├── NavMain.svelte
│   │   ├── NavUser.svelte
│   │   ├── NavFooter.svelte
│   │   └── Breadcrumbs.svelte
│   │
│   ├── auth/                 # Componentes de autenticación
│   │   ├── TwoFactorSetupModal.svelte
│   │   ├── TwoFactorRecoveryCodes.svelte
│   │   └── DeleteUser.svelte
│   │
│   ├── common/               # Componentes comunes reutilizables
│   │   ├── Heading.svelte
│   │   ├── HeadingSmall.svelte
│   │   ├── Icon.svelte
│   │   ├── InputError.svelte
│   │   ├── AlertError.svelte
│   │   ├── TextLink.svelte
│   │   ├── UserInfo.svelte
│   │   ├── UserMenuContent.svelte
│   │   ├── PlaceholderPattern.svelte
│   │   └── AppearanceTabs.svelte
│   │
│   └── index.ts              # Barrel export para componentes custom
│
└── ui/                       # Componentes de shadcn-svelte (generados)
    ├── button/
    ├── card/
    ├── dialog/
    └── ...
```

## Beneficios

1. **Separación clara**: Los componentes custom están separados de los generados por shadcn
2. **Organización por función**: Agrupados por su propósito (admin, layout, navigation, etc.)
3. **Fácil mantenimiento**: Es claro qué componentes son custom y cuáles son de la librería
4. **Imports más claros**: 
   - `import { DataTable } from '@/components/custom/admin'`
   - `import { Button } from '@/components/ui/button'`

## Migración

Se moverán todos los componentes custom de `components/` a `components/custom/` organizados por categoría.
