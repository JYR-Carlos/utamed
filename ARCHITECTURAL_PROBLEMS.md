# Architectural Problems

## Database Modeling and ORM Integration
- Composite primary keys widely used across domain tables cause friction with Eloquent’s single-PK assumptions. Mixed strategies (some models override to single PK, others keep composite) create inconsistency and hidden edge cases. See Seccion using single PK [Seccion.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Models/Curso/Seccion.php#L18-L23) versus composite bases [BaseInscripcionSeccion.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Models/Base/Curso/BaseInscripcionSeccion.php#L23-L25).
- Reliance on Compoships with local overrides in extensions to fix quoting/constraints introduces coupling to library internals and potential breakage on updates. See [BelongsTo](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Extensions/Compoships/BelongsTo.php) and [HasMany](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Extensions/Compoships/HasMany.php).
- Custom qualifyColumn and quoting strategies vary across models (direct quoting with escaped quotes versus plain qualify), making SQL generation brittle and harder to reason about. See [BaseInscripcionSeccion.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Models/Base/Curso/BaseInscripcionSeccion.php#L31-L41) and overrides in derived classes [InscripcionSeccion.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Models/Curso/InscripcionSeccion.php#L15-L23).
- Factories assume placeholder context values and defer foreign keys (e.g., id_contexto = 1). Coupling test data to global context magic numbers reduces reliability of domain tests. See [CursoFactory.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/database/factories/Curso/CursoFactory.php#L29-L32).

## Authorization and Role Semantics
- Authorization logic mixes Policies, custom PermissionValidator, and ad-hoc role checks in controllers/middleware. This leads to divergent access rules and makes the system harder to audit or change coherently. See Policies [CursoPolicy.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Policies/CursoPolicy.php) vs controller checks [InscripcionCursoController.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L45-L50).
- “Admin” is defined inconsistently: middleware validates explicit roles (“Administrador”, “SuperAdmin”), while controllers often infer admin as “no docente and no estudiante”. This heuristic can be bypassed or drift from role assignments. See [IsAdmin.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Http/Middleware/IsAdmin.php#L39-L61) vs [InscripcionCursoController.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L46-L50).
- Permission caching exists but invalidation strategy for role/permission changes is unclear; stale decisions are possible under high change rates. See cache usage in [PermissionValidator.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Services/Authorization/PermissionValidator.php#L121-L147).
- Multiple role name variants (“SuperAdmin”, “Super Admin”) are referenced in code, increasing the chance of mismatches or silent failures. See [CursoPolicy.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Policies/CursoPolicy.php#L37-L48).

## Context Resolution and Hierarchies
- ContextResolver depends on generated mappings and a configuration path, with a runtime exception that points to a script not present in the repository. Operational coupling to a generation step risks runtime failures if mappings are missing or stale. See [ContextResolver.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Services/ContextResolver.php#L41-L46).
- Context resolution relies on walking relationships and picking the “first” element of collections, which can mask ambiguity or multi-parent contexts. In multi-path scenarios, uniqueness/precedence rules are implicit rather than explicit. See [ContextResolver.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Services/ContextResolver.php#L216-L223).
- Controllers perform context creation (e.g., firstOrCreate Contexto when creating Curso), mixing application services with persistence and potentially duplicating context entries if naming is not unique. See [CursoController.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Http/Controllers/Admin/CursoController.php#L138-L147).

## Routing and Module Boundaries
- Duplicated endpoints between admin and docente areas increase maintenance overhead and the chance of divergent behavior. Some routes reuse Admin controllers under the docente prefix while others fork behavior, leading to inconsistency. See [routes/web.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/routes/web.php#L173-L223).
- Dashboard role redirects depend on user profiles and ad-hoc role checks rather than a consistent permission-based approach, making behavior implicit and hard to trace. See [routes/web.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/routes/web.php#L22-L47).

## Database Layer and Search Path
- search_path is set via DB::listen with a static guard; this is a non-standard place to perform connection-level initialization and silently fails on exceptions, which can hide misconfigurations. See [DatabaseServiceProvider.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Providers/DatabaseServiceProvider.php#L23-L40).
- Heavy reliance on search_path and unqualified table names reduces portability across environments and increases coupling to PostgreSQL schemas layout.

## Query Safety and Consistency
- Mixing raw expressions and bound parameters is present in search filters; while the value binding is used, the use of DB::raw concatenations increases complexity and potential for mistakes. See [InscripcionCursoController.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L67-L76).
- Case-sensitive columns and manual quoting introduce variability in how queries are constructed per model, raising the risk of subtle bugs (double quoting, mismatched identifiers).

## Frontend/SSR Operational Coupling
- Inertia SSR configuration points to a local fixed URL; the app’s initial rendering depends on an external process being alive. Operational coupling without health checks or fallback modes can degrade UX unpredictably. See [config/inertia.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/config/inertia.php#L22-L28).

## Maintainability and Generation Workflow
- Base models are “auto-generated” with special quoting logic, but the generation pipeline is not codified in the repo. Differences between generated base classes and derived customizations (e.g., key strategies, quoting) suggest drift risks during regeneration.
- Extensions to third-party libraries (Compoships) are maintained locally; updates upstream may invalidate assumptions in extension classes, increasing maintenance burden.

## Domain Consistency
- Route model binding strategies vary: some models expose composite semantics but override getRouteKeyName to a single field, others stick to composite keys. This inconsistency affects URL patterns and controller expectations. See [InscripcionCurso.php](file:///c:/Users/yampa/Trabajos/UTA/edit/utamed/utamed/app/Models/Curso/InscripcionCurso.php#L17-L21) vs composite bases.
- Context “global” handling uses magic IDs and ENV-configured TTLs but lacks clear invariants around lifecycle and mutation of permissions across contexts.

