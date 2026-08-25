---
name: safe-pull-rebase
description: >-
  Realiza pull y rebase de cambios remotos de forma segura y cuantitativa. Mide colisiones
  reales en memoria con 'git merge-tree' antes de tocar archivos. Si hay 0 conflictos reales,
  'git pull --rebase' realiza todo el trabajo de forma directa. Si detecta conflictos reales,
  CANCELA la operación reportando el número exacto de colisiones y sugiriendo rama temporal
  solo ante estructuras complejas.
---

# Safe Pull Rebase Skill (Actualización y Rebase Cuantitativo)

Esta skill analiza la seguridad de traer cambios del repositorio remoto (`git pull --rebase` o `git rebase origin/<branch>`) simulando la integración en memoria mediante **`git merge-tree`**.

---

## 🔬 Algoritmo de Medición Cuantitativa de Colisiones

```mermaid
flowchart TD
    A[git fetch -p origin] --> B[git merge-tree --write-tree --messages HEAD origin/rama]
    B --> C{¿Conflictos reales de código?}
    C -- "0 conflictos (Exit code 0)" --> D{¿Worktree limpio?}
    D -- Sí --> R1["🟢 SEGURO: git pull --rebase hace todo el trabajo"]
    D -- No --> R2["🟡 PRECAUCIÓN: Stash rápido -> git pull --rebase -> Stash pop"]
    C -- "≥ 1 conflicto real (Exit code 1)" --> R3["🔴 CANCELADO: Reportar colisiones exactas"]
    R3 --> E{¿Estructura excesivamente compleja?}
    E -- Sí (≥ 3 conflictos o historial enredado) --> R4["⚠️ Sugerir rama temporal de respaldo"]
    E -- No (1-2 conflictos puntuales) --> R5["Resolver archivos colisionados"]
```

---

## 📋 Flujo de Trabajo Paso a Paso

### Paso 1: Actualización de Referencias Remotas
```powershell
git fetch --prune origin
```

---

### Paso 2: Medición de Colisiones en Memoria (`git merge-tree`)

Se ejecuta la simulación en memoria sin tocar ningún archivo ni alterar el índice:

```powershell
powershell -File .agents/skills/safe-pull-rebase/scripts/safe-pull-check.ps1
```

#### Métricas Cuantitativas Obtenidas:
1. **Archivos Compartidos**: Archivos modificados simultáneamente en local y remoto.
2. **Archivos Auto-Fusionables**: Archivos compartidos donde Git resuelve las diferencias automáticamente (`Auto-merging`, 0 colisiones de líneas).
3. **Conflictos Reales de Código**: Ocurrencias de `CONFLICT (content)` donde las mismas líneas colisionan.

---

### Paso 3: Protocolo según el Veredicto

Consulte la guía completa en [`risk-assessment.md`](./references/risk-assessment.md).

#### 🟢 CASO 1: SEGURO (0 Conflictos Reales de Código)
`git pull --rebase` **hace todo el trabajo directamente**:
```bash
git pull --rebase origin <rama>
```
*No requiere ramas temporales ni pasos manuales extras; Git reubica tus commits locales de forma transparente sobre la punta del remoto.*

#### 🟡 CASO 2: PRECAUCIÓN (0 Conflictos pero Worktree Sucio)
Tus commits son compatibles, pero tienes archivos sin commitear en el worktree:
```bash
git stash push -u -m "pre-pull-stash"
git pull --rebase origin <rama>
git stash pop
```

#### 🔴 CASO 3: CANCELADO POR COLISIÓN REAL (≥ 1 Conflicto Real)
**Acción del Agente**:
1. **Detener inmediatamente** el intento de rebase.
2. Reportar la cantidad exacta de colisiones y listar los archivos en conflicto.
3. **Solo si la estructura es muy compleja** (múltiples branches cruzados o ≥ 3 conflictos severos), sugerir rama temporal de respaldo antes de intervenir.

---

## 🛡️ Formato del Reporte de Diagnóstico

```text
# 🛡️ Diagnóstico Cuantitativo: Pull & Rebase

- Rama actual: `<rama>`
- Rama remota: `origin/<rama>`
- Commits locales (ahead): `X` | Commits remotos (behind): `Y`
- Archivos compartidos en ambos lados: `N`
- Archivos auto-fusionables (sin colisión de líneas): `N`
- Conflictos reales de código detectados: `0` (o lista de archivos)

> **Veredicto**: 🟢 SEGURO | 🟡 PRECAUCIÓN | 🔴 CANCELADO POR COLISIÓN REAL

[Comandos de ejecución recomendados]
```
