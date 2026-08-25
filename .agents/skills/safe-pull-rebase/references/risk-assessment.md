# 🔬 Medición Cuantitativa de Riesgos con `git merge-tree`

Esta referencia documenta el funcionamiento de la herramienta `git merge-tree` para la detección precisa y matemática de colisiones antes de ejecutar un `git pull --rebase`.

---

## 1. ¿Cómo funciona `git merge-tree`?

`git merge-tree --write-tree --messages HEAD <remote-branch>` realiza un *3-way merge* completo en memoria (RAM), sin modificar ningún archivo del disco ni alterar el árbol de trabajo (`worktree`).

### Salida del comando:
1. **Línea 1**: El hash OID del árbol resultante en memoria.
2. **Mensajes de Diagnóstico**:
   - `Auto-merging <archivo>`: El archivo fue editado en ambos extremos, pero en líneas distintas; Git lo fusiona automáticamente con éxito.
   - `CONFLICT (content): Merge conflict in <archivo>`: Colisión real en las mismas líneas de código.
3. **Código de Salida (Exit Code)**:
   - `0`: Cero conflictos. Rebase o merge 100% garantizado sin intervención humana.
   - `1`: Uno o más conflictos reales de contenido.

---

## 2. Matriz Cuantitativa de Decisión

| Métrica | Condición | Riesgo | Veredicto | Acción Recomendada |
| :--- | :--- | :---: | :---: | :--- |
| **0 Conflictos** | Worktree limpio | `Nulo` | 🟢 **SEGURO** | `git pull --rebase origin <rama>` *(hace todo el trabajo directo)* |
| **0 Conflictos** | Worktree con cambios | `Bajo` | 🟡 **PRECAUCIÓN** | `git stash push -u` -> `git pull --rebase` -> `git stash pop` |
| **1 a 2 Conflictos** | Conflicto puntual | `Alto` | 🔴 **CANCELADO** | Informar archivos exactos para resolución |
| **≥ 3 Conflictos** | Conflicto masivo / Estructura compleja | `Crítico` | ⚠️ **ESTRUCTURA COMPLEJA** | Sugerir rama temporal de respaldo antes de resolver |

---

## 3. ¿Cuándo sugerir Rama Temporal de Respaldo?

**ÚNICAMENTE** en situaciones de complejidad estructural alta:
1. Más de 3 archivos con colisión real de código simultánea.
2. Divergencia histórica con múltiples merge commits cruzados.
3. Incertidumbre sobre el estado de la base de datos o migraciones incompatibles.

En el 95% de los casos cotidianos (0 conflictos reales), **`git pull --rebase origin <rama>` es la solución directa y limpia**.
