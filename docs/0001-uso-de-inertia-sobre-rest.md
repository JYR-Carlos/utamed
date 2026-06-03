# ADR 0001: Uso del Patrón "Monolito Moderno" (Inertia.js) sobre API REST

**Fecha:** 2026-02-03
**Estado:** Aceptado

## 1. Contexto
El equipo necesita definir cómo se comunicará el frontend reactivo (Svelte 5) con el backend (Laravel 12). Tradicionalmente, esto se hace creando endpoints en `routes/api.php` y consumiéndolos con `fetch` o `axios` en el cliente. Esto genera duplicación de código (tipos, validaciones, rutas duplicadas) y requiere gestionar manualmente los estados de carga y errores de validación.

## 2. Decisión
Adoptamos **Inertia.js** como pegamento arquitectónico (The Glue) para implementar el patrón de "Monolito Moderno".
* **No construiremos una API REST separada** para el consumo interno de la aplicación.
* Laravel funcionará como controlador principal, retornando componentes de Svelte (`Inertia::render`) en lugar de JSONs puros, y redirecciones en las mutaciones (`to_route()`).
* Svelte se comunicará exclusivamente usando los helpers `@inertiajs/svelte` (`Link`, `router`, `useForm`).

## 3. Consecuencias
### Positivas:
* **Productividad:** Eliminamos la necesidad de escribir y mantener una capa de API.
* **Manejo de Errores:** Los Form Requests de Laravel inyectan automáticamente los errores de validación en el `$form.errors` de Svelte.
* **Estado Global Compartido:** Datos como el usuario y permisos viajan automáticamente en cada petición vía `HandleInertiaRequests`.

### Negativas / Restricciones:
* No podemos usar este backend para alimentar directamente una aplicación móvil nativa (iOS/Android) sin crear rutas API separadas en el futuro.
* Los desarrolladores nuevos deben desaprender el uso de `fetch`/`axios` y adaptarse al ciclo de vida de Inertia.