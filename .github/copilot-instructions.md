---
name: workspace-instructions
description: "Workspace-level instructions for the Copilot Chat agent. Use when: working on features, Laravel/Inertia architecture, Svelte frontend integration, or debugging."
applyTo:
  - "app/**"
  - "resources/**"
  - "routes/**"
  - "database/**"
---

Purpose
-------
This file provides strict, repository-scoped guidance for AI assistants to help contributors work productively in this project. 
**CRITICAL CONTEXT:** This project uses a "Modern Monolith" architecture (Laravel 12 + Inertia.js + SvelteKit/Svelte + PostgreSQL). **We are NOT building a REST API.**

Strict Architectural Conventions (The "Modern Monolith")
--------------------------------------------------------

### 1. Frontend Rules (Svelte + Inertia)
- **NO NATIVE APIS:** NEVER use `fetch`, `axios`, `XMLHttpRequest`, or the `URL` API to communicate with the Laravel backend. 
- **Navigation:** ALWAYS use the `<Link href="...">` component from `@inertiajs/svelte` for navigation.
- **Mutations (Forms):** ALWAYS use the `useForm` helper from `@inertiajs/svelte`. Call `$form.post()`, `$form.put()`, etc. Handle errors via `$form.errors`.
- **Programmatic Actions:** For standalone buttons, use the `router` object (e.g., `router.delete('/path')`).
- **Global State:** Consume global data (user, roles) from `$page.props.auth` (typed in `inertia.d.ts`). Do not fetch this data manually.
- **Styling:** Use Tailwind CSS utility classes. Avoid `<style>` tags in `.svelte` files unless for highly specific animations.

### 2. Backend Rules (Laravel 12)
- **Controller Responses:** NEVER return `response()->json()` for internal app flows.
  - For GET requests: Return `Inertia::render('Path/Component', ['prop' => $data]);`.
  - For POST/PUT/DELETE: Return redirects via `return to_route('route.name');` or `return back();`.
- **Validation:** Always validate via Form Requests. Never rely solely on client-side validation.
- **Typing:** Use strict PHP typing. Document arrays passed to Inertia using PHPStan array shapes in DocBlocks (e.g., in `HandleInertiaRequests.php`).
- **Database:** Prevent N+1 queries using Eager Loading (`with()`). 

Files & Areas of Interest
------------------------
- Backend: `app/`, `routes/web.php` (We rarely use `routes/api.php`), `config/`
- Frontend: `resources/js/Pages/` (Views), `resources/js/Components/` (UI Elements)
- Database: `database/migrations/`, `database/seeders/`

Example Prompts (Inertia-Aware)
-------------------------------
- "Find potential security issues in the `app/Http/Controllers` folder and suggest fixes ensuring controllers return Inertia redirects."
- "Refactor `resources/js/Pages/Admin/UserImport.svelte` to improve accessibility and use Inertia's `useForm`."
- "Summarize the database schema changes in `database/migrations`."
- "Add a new web route for program enrollment and return the controller returning an `Inertia::render`, the route, and a minimal feature test."

Contributing
------------
To update these instructions, create a small change with a descriptive PR and include a one-line reason in the PR description.