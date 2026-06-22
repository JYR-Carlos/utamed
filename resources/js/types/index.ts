/**
 * Barrel export file for TypeScript types
 * Centralizes all type exports for easier imports
 *
 * @example
 * import type { Usuario, Asignatura, PaginatedResponse } from '@/types';
 * import { ContextType } from '@/types';
 */

// Re-export all admin types
export * from './admin.types';

// Re-export permission/context types
export * from './permissions';

// Re-export DTOs
export * from './controllers';

// Re-export global types
export type {
  BreadcrumbItem,
  BreadcrumbItemType,
  NavItem,
  PageProps,
  SharedAuth,
  SidebarCourse,
  User,
  Curso,
  Asignatura,
  Carrera,
} from './index.d';
