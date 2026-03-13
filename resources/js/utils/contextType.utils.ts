/**
 * Utilities for ContextType enum operations
 * Mirrors backend functionality from App\Enums\ContextType
 *
 * Provides type-safe helpers for working with context hierarchies,
 * validation, and context management on the frontend.
 */

import { ContextType } from '@/types';

/**
 * Context hierarchy map — represents the parent relationships
 * Maps context type value → immediate parent type (or null for GLOBAL)
 *
 * Hierarchy structure:
 *   ACTIVIDAD → CURSO → CARRERA → DEPARTAMENTO → FACULTAD → GLOBAL → null
 */
const CONTEXT_HIERARCHY: Record<ContextType, ContextType | null> = {
  [ContextType.GLOBAL]: null,
  [ContextType.CARRERA]: ContextType.DEPARTAMENTO,
  [ContextType.DEPARTAMENTO]: ContextType.FACULTAD,
  [ContextType.FACULTAD]: ContextType.GLOBAL,
  [ContextType.ACTIVIDAD]: ContextType.CURSO,
  [ContextType.CURSO]: ContextType.CARRERA,
};

/**
 * All context types in hierarchical order
 */
const ALL_CONTEXT_TYPES = [
  ContextType.GLOBAL,
  ContextType.FACULTAD,
  ContextType.DEPARTAMENTO,
  ContextType.CARRERA,
  ContextType.CURSO,
  ContextType.ACTIVIDAD,
] as const;

/**
 * Get the immediate parent of a context type in the hierarchy
 *
 * @param contextType - The context type to get the parent for
 * @returns The immediate parent type, or null if this is the root (GLOBAL)
 *
 * @example
 *   getParent(ContextType.CARRERA)      // → ContextType.DEPARTAMENTO
 *   getParent(ContextType.GLOBAL)       // → null
 */
export function getParent(contextType: ContextType): ContextType | null {
  return CONTEXT_HIERARCHY[contextType] ?? null;
}

/**
 * Get the complete ancestor chain for a context type
 * Walks from immediate parent up to GLOBAL (inclusive)
 *
 * @param contextType - The context type to get ancestors for
 * @returns Array of ancestors from immediate parent to GLOBAL
 *
 * @example
 *   getAncestors(ContextType.CARRERA)  // → [ContextType.DEPARTAMENTO, ContextType.FACULTAD, ContextType.GLOBAL]
 *   getAncestors(ContextType.GLOBAL)   // → []
 */
export function getAncestors(contextType: ContextType): ContextType[] {
  const ancestors: ContextType[] = [];
  let current = getParent(contextType);

  while (current !== null) {
    ancestors.push(current);
    current = getParent(current);
  }

  return ancestors;
}

/**
 * Check if one context type is an ancestor of another
 * Does NOT include self (not reflexive)
 *
 * @param potential_ancestor - The type to check as ancestor
 * @param potential_descendant - The type to check as descendant
 * @returns true if ancestor is an ancestor of descendant
 *
 * @example
 *   isAncestor(ContextType.FACULTAD, ContextType.CARRERA)  // → true
 *   isAncestor(ContextType.CARRERA, ContextType.CARRERA)   // → false (not reflexive)
 */
export function isAncestor(potential_ancestor: ContextType, potential_descendant: ContextType): boolean {
  return getAncestors(potential_descendant).includes(potential_ancestor);
}

/**
 * Check if one context type is a descendant of another
 * Inverse of isAncestor (not reflexive)
 *
 * @param potential_descendant - The type to check as descendant
 * @param potential_ancestor - The type to check as ancestor
 * @returns true if descendant is a descendant of ancestor
 *
 * @example
 *   isDescendant(ContextType.CARRERA, ContextType.FACULTAD)  // → true
 *   isDescendant(ContextType.FACULTAD, ContextType.CARRERA)  // → false
 */
export function isDescendant(potential_descendant: ContextType, potential_ancestor: ContextType): boolean {
  return isAncestor(potential_ancestor, potential_descendant);
}

/**
 * Get all descendant types of a context type
 * Includes all subordinate types in the hierarchy
 *
 * @param contextType - The context type to get descendants for
 * @returns Array of all types that are descendants of this type
 *
 * @example
 *   getDescendants(ContextType.FACULTAD)  // → [ContextType.DEPARTAMENTO, ContextType.CARRERA, ContextType.CURSO, ContextType.ACTIVIDAD]
 *   getDescendants(ContextType.ACTIVIDAD) // → []  (leaf node, no descendants)
 */
export function getDescendants(contextType: ContextType): ContextType[] {
  return ALL_CONTEXT_TYPES.filter((type) => contextType !== type && isAncestor(contextType, type));
}

/**
 * Check if a given context type is valid from a list of allowed types
 * Useful for validation when assigning roles/permissions
 *
 * @param contextType - The type to validate
 * @param allowedTypes - Array of allowed context types
 * @returns true if contextType is in allowedTypes (checks both direct and ancestral match)
 *
 * @example
 *   isValidContext(ContextType.CARRERA, [ContextType.GLOBAL, ContextType.CARRERA])
 *   // → true
 */
export function isValidContext(contextType: ContextType, allowedTypes: ContextType[]): boolean {
  // Direct match
  if (allowedTypes.includes(contextType)) {
    return true;
  }

  // Check if any allowed type is an ancestor
  return allowedTypes.some((allowed) => isAncestor(allowed, contextType));
}

/**
 * Get a human-readable label for a context type
 *
 * @param contextType - The context type to get label for
 * @returns Spanish label for the context type
 *
 * @example
 *   getLabel(ContextType.CARRERA)  // → "Carrera"
 *   getLabel(ContextType.GLOBAL)   // → "Global (Sistema Completo)"
 */
export function getLabel(contextType: ContextType): string {
  const labels: Record<ContextType, string> = {
    [ContextType.GLOBAL]: 'Global (Sistema Completo)',
    [ContextType.ACTIVIDAD]: 'Actividad',
    [ContextType.CARRERA]: 'Carrera',
    [ContextType.CURSO]: 'Curso',
    [ContextType.DEPARTAMENTO]: 'Departamento',
    [ContextType.FACULTAD]: 'Facultad',
  };

  return labels[contextType] || contextType;
}

/**
 * Get a short description for a context type
 *
 * @param contextType - The context type to get description for
 * @returns Description of the context type
 *
 * @example
 *   getDescription(ContextType.CARRERA)  // → "Restricción a una carrera específica"
 */
export function getDescription(contextType: ContextType): string {
  const descriptions: Record<ContextType, string> = {
    [ContextType.GLOBAL]: 'Aplica en todos los contextos del sistema',
    [ContextType.ACTIVIDAD]: 'Restricción a una actividad específica',
    [ContextType.CARRERA]: 'Restricción a una carrera específica',
    [ContextType.CURSO]: 'Restricción a un curso específico',
    [ContextType.DEPARTAMENTO]: 'Restricción a un departamento específico',
    [ContextType.FACULTAD]: 'Restricción a una facultad específica',
  };

  return descriptions[contextType] || '';
}

/**
 * Compare two context types for ordering (hierarchical order)
 * Useful for sorting context types in dropdowns/lists
 *
 * @param a - First context type
 * @param b - Second context type
 * @returns negative if a < b, 0 if equal, positive if a > b
 *
 * @example
 *   [ContextType.CARRERA, ContextType.GLOBAL, ContextType.FACULTAD]
 *     .sort(compareContextTypes)
 *   // → [ContextType.GLOBAL, ContextType.FACULTAD, ContextType.DEPARTAMENTO, ContextType.CARRERA, ContextType.CURSO, ContextType.ACTIVIDAD]
 */
export function compareContextTypes(a: ContextType, b: ContextType): number {
  const hierarchy = ALL_CONTEXT_TYPES;
  const aIdx = hierarchy.indexOf(a);
  const bIdx = hierarchy.indexOf(b);

  return (aIdx === -1 ? hierarchy.length : aIdx) - (bIdx === -1 ? hierarchy.length : bIdx);
}

/**
 * Get all context types in hierarchical order (top-down)
 *
 * @returns Array of all context type values in hierarchy order
 *
 * @example
 *   getAllInOrder()  // → [ContextType.GLOBAL, ContextType.FACULTAD, ContextType.DEPARTAMENTO, ContextType.CARRERA, ContextType.CURSO, ContextType.ACTIVIDAD]
 */
export function getAllInOrder(): ContextType[] {
  return [...ALL_CONTEXT_TYPES];
}

/**
 * Check if a context type is a "leaf" (has no children)
 *
 * @param contextType - The context type to check
 * @returns true if this type has no descendants
 *
 * @example
 *   isLeaf(ContextType.ACTIVIDAD)  // → true
 *   isLeaf(ContextType.FACULTAD)   // → false
 */
export function isLeaf(contextType: ContextType): boolean {
  return getDescendants(contextType).length === 0;
}

/**
 * Check if a context type is a "root" (has no parents)
 *
 * @param contextType - The context type to check
 * @returns true if this type has no ancestors (is GLOBAL)
 *
 * @example
 *   isRoot(ContextType.GLOBAL)    // → true
 *   isRoot(ContextType.FACULTAD)  // → false
 */
export function isRoot(contextType: ContextType): boolean {
  return getParent(contextType) === null;
}

/**
 * Calculate the depth of a context type in the hierarchy
 * global = 0, facultad = 1, departamento = 2, etc.
 *
 * @param contextType - The context type to get depth for
 * @returns Depth level (0 = root)
 */
export function getDepth(contextType: ContextType): number {
  return getAncestors(contextType).length;
}

/**
 * Checkea si el tipo de contexto es GLOBAL
 *
 * @param contextType - El contexto a validar
 * @returns true si es GLOBAL
 */
export function isGlobalContext(contextType: ContextType): boolean {
  return isRoot(contextType); // semanticamente correcto
  // return contextType === ContextType.GLOBAL; // es más hardcoded
}