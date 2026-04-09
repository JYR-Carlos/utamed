---
name: workspace-instructions
description: "Workspace-level instructions for the Copilot Chat agent and local helper agents. Use when: working on features, debugging permissions, frontend integration, or producing tests/docs for this repo."
applyTo:
  - "app/**"
  - "resources/**"
  - "routes/**"
  - "database/**"
---

Purpose
-------

This file provides concise, repository-scoped guidance for AI assistants (Copilot Chat and local agents) to help contributors work productively in this project.

How to use
----------

- Ask the assistant to operate within a specific scope (example: `app/Http/Controllers` or `resources/js/components`).
- Use the example prompts below to perform common tasks.
- When proposing code changes, include a short test plan and any commands needed to run them.

Conventions (high level)
------------------------

- Branches: feature/* for new features, fix/* for bug fixes.
- Testing: run unit tests with `composer test` or `phpunit` where applicable.
- Formatting: run `composer run format` or the repo's configured formatter before PRs.

Files & Areas of Interest
------------------------

- Backend: `app/`, `routes/`, `config/`
- Frontend: `resources/js/`, `resources/views/`
- Database: `database/migrations/`, `database/seeders/`
- Docs: `docs/`

When to load these instructions
-------------------------------

- Use for cross-cutting tasks that affect multiple areas (permissions, auth, deployment, architecture changes).
- For very specific UI components or small utility functions, prefer file-scoped prompts or `applyTo` patterns.

Example Prompts
---------------

- "Find potential security issues related to IDOR patterns in the `app/Http/Controllers` folder and suggest fixes." 
- "Refactor `resources/js/components/admin/UserImport.svelte` to improve accessibility and add unit tests." 
- "Summarize the database schema changes in `database/migrations` and generate a migration plan for production." 
- "Add a new API route for program enrollment and return the controller, route, and a minimal feature test." 

Contributing
------------

To update these instructions, create a small change with a descriptive PR and include a one-line reason in the PR description (what's improved or why it's needed).

Notes & Links
------------

- Keep `description` enriched with trigger phrases the assistant should match (e.g., "permissions", "frontend", "migrations").
- See internal skill references for templates: `.github/references/` (if present).

If you'd like, I can also:
- Add file-scoped instructions for the frontend (`resources/js/**`).
- Add a few ready-to-use example prompts in the repo README.
