/**
 * Barrel export file for all custom components
 * Provides clean imports for custom components organized by category
 * 
 * Note: shadcn-svelte UI components are in @/components/ui/
 * 
 * @example
 * // Import from specific category
 * import { DataTable, FormModal } from '@/components/custom/admin';
 * import { AppHeader, AppSidebar } from '@/components/custom/layout';
 * 
 * // Or import from main barrel (re-exports all)
 * import { DataTable, AppHeader } from '@/components/custom';
 */

// Re-export all custom component categories
export * from './admin';
export * from './layout';
export * from './navigation';
export * from './auth';
export * from './common';
