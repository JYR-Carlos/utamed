/**
 * Barrel export file for TypeScript types
 * Centralizes all type exports for easier imports
 * 
 * @example
 * import type { Usuario, Asignatura, PaginatedResponse } from '@/types';
 */

// Re-export all admin types
export * from './admin.types';

// Re-export global types
export type { User, SharedAuth, PageProps, BreadcrumbItem, NavItem } from './index.d';
