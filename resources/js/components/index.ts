/**
 * Main components barrel export
 * 
 * IMPORTANT: This file now serves as a compatibility layer.
 * New code should import from specific paths:
 * 
 * @example
 * // Preferred (specific imports)
 * import { DataTable } from '@/components/custom/admin';
 * import { Button } from '@/components/ui/button';
 * 
 * // Also works (for backwards compatibility)
 * import { DataTable } from '@/components';
 */

// Custom components (organized by category)
export * from './custom';

// Note: UI components from shadcn-svelte are in ./ui/
// Import them directly: import { Button } from '@/components/ui/button'
