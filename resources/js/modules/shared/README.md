# modules/shared/

Shared utilities, hooks, and constants used across multiple resource modules.

## Purpose

This directory is for cross-cutting concerns that don't belong to a specific resource entity.

## Structure

- **hooks/** - Reusable Svelte hooks (useFilteredList, useDebounce, etc.)
- **utils/** - Utility functions (validators, formatters, helpers)
- **constants/** - Shared constants and re-exports

## Current State

Currently mostly empty. As modules grow, extract common patterns here.

## Do NOT use for

- Role-specific logic (roles are handled by pages/{role}/ and routes)
- Entity-specific components (use modules/resources/{entity}/)
- Backend authorization (use Laravel Policies)
