# Resumen y Revisión de Commits: <nombre-de-rama>

### Información de la Rama y Alcance
* **Rama analizada:** `<nombre-de-rama>`
* **Rango de commits:** `<hash-inicial>` .. `<hash-final>`
* **Total de commits:** `<N>`
* **Periodo de cambios:** `<YYYY-MM-DD>` al `<YYYY-MM-DD>`
* **Commit inicial:** `<hash-inicial>` (*"<mensaje-commit-inicial>"*) — `<Autor>` (`<YYYY-MM-DD HH:mm>`)
* **Último commit:** `<hash-final>` (*"<mensaje-commit-final>"*) — `<Autor>` (`<YYYY-MM-DD HH:mm>`)

---

## Contribuciones por Autor

| Autor | Commits | Áreas Principales Aportadas | Impacto General |
| :--- | :---: | :--- | :--- |
| **<Nombre Autor>** (`@<usuario>`) | `<N>` | `<Componentes o módulos trabajados>` | `<Resumen general de su impacto>` |
| **<Nombre Autor 2>** (`@<usuario>`) | `<N>` | `<Componentes o módulos trabajados>` | `<Resumen general de su impacto>` |

---

## Desglose de Cambios por Pilares y Temas

### <Letra>. <Nombre del Pilar o Tema> (Total: <X> commits)

#### <Título de la Funcionalidad o Cambio> (`<hash-commit>`)
* **Regla de negocio:** <Explicación concisa del impacto y regla a nivel de dominio o negocio>.
* **Backend:** <Explicación de controladores, middlewares, modelos, migraciones, endpoints o lógica interna>.
* **Frontend:** <Explicación de componentes, vistas, stores, reactividad o elementos visuales>.

*Por `<Nombre Autor>`*

#### <Título de Funcionalidad con Commits Agrupados> (`<hash1>`, `<hash2>`, `<hash3>`)
*(Commits iterativos y WIPs agrupados)*
* **Regla de negocio:** <Explicación concisa del impacto y regla a nivel de dominio o negocio>.
* **Backend:** <Explicación de controladores, middlewares, modelos, migraciones, endpoints o lógica interna>.
* **Frontend:** <Explicación de componentes, vistas, stores, reactividad o elementos visuales>.

*Por `<Nombre Autor>`*

---

## Evaluación de Calidad de Código

| Criterio | Estado | Observaciones |
| :--- | :---: | :--- |
| **Separación de Responsabilidades** | `Conforme` / `Requiere Atención` | <Análisis de acoplamiento, arquitectura y distribución de lógica> |
| **Manejo de Errores y Validaciones** | `Conforme` / `Requiere Atención` | <Análisis de validaciones en backend/frontend y feedback de error> |
| **Atomicidad del Historial** | `Conforme` / `Requiere Atención` | <Detección de commits WIP, mensajes genéricos o correcciones cruzadas> |
| **Riesgos y Casos Borde** | `Conforme` / `Advertencia` | <Casos no controlados, consultas costosas, seguridad o efectos colaterales> |

---

## Resumen General y Recomendación de Acción

### Resumen Ejecutivo
<Párrafo conciso con la visión general del avance, grado de madurez técnica de la rama y coherencia entre backend y frontend.>

### Recomendaciones de Acción
1. **Rebase interactivo y consolidación de WIPs:** <Instrucción específica de squash o reordenamiento si aplica>.
2. **Cobertura de pruebas:** <Casos de prueba esenciales que deben asegurarse antes de mezclar>.
3. **Dictamen final:** `<Aprobado / Aprobado condicionado / Requiere cambios>` con la justificación directa.
