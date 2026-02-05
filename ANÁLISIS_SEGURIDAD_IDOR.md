# Análisis de Seguridad - Vulnerabilidades IDOR (Insecure Direct Object Reference)

**Fecha de Análisis**: 5 Febrero 2026  
**Versión**: 1.0  
**Estado**: ⚠️ RIESGOS CRÍTICOS Y ALTOS IDENTIFICADOS

---

## 📋 Resumen Ejecutivo

El análisis de seguridad del sistema UTAMed ha identificado **vulnerabilidades IDOR críticas y altas** que podrían permitir a atacantes:

- ✋ Acceder a datos de otros usuarios sin autorización
- 🔄 Modificar registros de otros usuarios  
- 🗑️ Eliminar datos ajenos
- 👥 Escalar privilegios entre usuarios

**Nivel de Riesgo**: 🔴 **ALTO/CRÍTICO**

---

## 🔍 Vulnerabilidades Identificadas

### 1. **CRÍTICA**: Endpoints `/admin/usuarios/{id}` sin validación suficiente

**Ubicación**: [app/Http/Controllers/Admin/UsuarioController.php](app/Http/Controllers/Admin/UsuarioController.php#L303-L470)

**Métodos afectados**:
- `show($id, Request $request)` - Línea 303
- `update(Request $request, $id)` - Línea 321  
- `destroy($id, Request $request)` - Línea 451

**Vulnerabilidad**:
```php
public function show($id, Request $request)
{
    $tipo = $request->input('tipo', 'estudiante');
    
    // ❌ IDOR: Cualquier admin puede ver cualquier usuario
    if ($tipo === 'estudiante') {
        $usuario = Estudiante::with(['carrera', 'usuario'])->findOrFail($id);
    }
    return response()->json($usuario);
}
```

**Riesgo**:
- ❌ No hay validación de que el admin solo vea usuarios en su jurisdicción
- ❌ El parámetro `tipo` viene del usuario sin validación de contexto
- ❌ Cualquier admin puede ver, editar o eliminar datos de cualquier usuario
- ❌ No hay auditoría de quién realizó cada acción

**Impacto**: 🔴 CRÍTICO - Acceso a datos sensibles de todos los usuarios

---

---

### 2. ✅ **RESUELTO**: Rutas compartidas entre Admin y Docente sin autorización correcta

**Ubicación**: [routes/web.php](routes/web.php#L75-L130)

**Rutas afectadas**:
```php
// Admin routes (líneas 75-97)
Route::get('cursos/{curso}/team', [CourseTeamController::class, 'index'])
Route::post('cursos/{curso}/team', [CourseTeamController::class, 'store'])
Route::delete('cursos/{curso}/team/{usuario}', [CourseTeamController::class, 'destroy'])

// Docente routes (líneas 118-120) - Reutilizan el MISMO controlador
Route::get('cursos/{curso}/team', [CourseTeamController::class, 'index'])
Route::post('cursos/{curso}/team', [CourseTeamController::class, 'store'])
Route::delete('cursos/{curso}/team/{usuario}', [CourseTeamController::class, 'destroy'])
```

**Vulnerabilidad**:
- ⚠️ El controlador usa `str_contains(request()->path(), '/docente/')` para detectar si es docente
- ❌ Un atacante podría construir URLs amiguas que burlen esta validación
- ❌ Falta validación robusta en el controlador

**Código actual**:
```php
// app/Http/Controllers/Admin/CourseTeamController.php - Línea 35
private function authorizeDocenteAccess(Curso $curso)
{
    if (request()->path() !== null && str_contains(request()->path(), '/docente/')) {
        // Validación basada en PATH - ¡Poco seguro!
    }
}
```

**Riesgo**: 🟠 ALTO - Un docente podría intentar acceder a cursos que no son suyos modificando la lógica del cliente

**Impacto**: Acceso no autorizado a equipos de otros cursos

**Solución**: Se implementó `CursoPolicy` y se refactorizó el controlador para usar la autorización nativa de Laravel (`$this->authorize('manageTeam', $curso)`), eliminando la validación insegura basada en path.

---

### 3. **ALTA**: Validación incompleta en DocenteCursoController

**Ubicación**: [app/Http/Controllers/Docente/DocenteCursoController.php](app/Http/Controllers/Docente/DocenteCursoController.php#L320-L335)

**Método afectado**: `authorizeAccess(Curso $curso)` - Línea 320

**Vulnerabilidad**:
```php
private function authorizeAccess(Curso $curso)
{
    $user = auth()->user();
    // ❌ Solo compara id_docente directo
    if (!$user->docente || $curso->id_docente !== $user->docente->id_docente) {
        abort(403, 'No tienes permiso para gestionar este curso.');
    }
}
```

**Problema**: 
- ⚠️ `$curso->id_docente` podría no estar sincronizado con `Seccion`
- ❌ Si un docente está en múltiples secciones, solo valida por docente principal
- ❌ No valida contexto ni permisos específicos

**Riesgo**: 🟠 ALTO - Escalación de privilegios si hay desincronización entre Curso y Seccion

---

### 4. **MEDIA**: Endpoints de permisos sin validación contextual

**Ubicación**: [routes/web.php](routes/web.php#L123-L126)

**Rutas**:
```php
Route::get('cursos/{curso}/team/{usuario}/permissions', [DocenteCursoController::class, 'getMemberPermissions'])
Route::post('cursos/{curso}/team/{usuario}/sync-permissions', [DocenteCursoController::class, 'syncMemberPermissions'])
```

**Vulnerabilidad**:
- ⚠️ No hay validación de que `{usuario}` sea realmente un miembro del equipo
- ⚠️ Un docente podría intentar modificar permisos de usuarios en otros cursos

**Riesgo**: 🟡 MEDIO - Modificación de permisos fuera del scope autorizado

---

### 5. **MEDIA**: Método `show()` en CoursseTeamController retorna JSON sin paginar

**Ubicación**: [app/Http/Controllers/Admin/CourseTeamController.php](app/Http/Controllers/Admin/CourseTeamController.php#L50-L95)

**Vulnerabilidad**:
```php
public function index(Curso $curso)
{
    // ...
    $team = $assignments->map(function ($assignment) {
        // Expone toda la información del usuario
        return [
            'id_usuario' => $user->id_usuario,
            'nombre_completo' => $name,
            'role_name' => $assignment->rol->nombre,
            'rut' => $rut  // ❌ Expone RUT sin necesidad
        ];
    });
    
    return response()->json($team->values()); // ❌ Sin paginación
}
```

**Riesgo**: 🟡 MEDIO - Information disclosure: expone RUT y datos personales innecesariamente

---

### 6. **MEDIA**: Falta de rate limiting en endpoints de autorización

**Ubicación**: Todos los middlewares y controladores

**Vulnerabilidad**:
- ❌ No hay rate limiting para intentos de acceso no autorizado
- ❌ Un atacante podría hacer fuerza bruta de IDs de recursos

**Riesgo**: 🟡 MEDIO - Enumeración de recursos y fuerza bruta

---

## 📊 Tabla de Resumen de Vulnerabilidades

| # | Ubicación | Tipo | Severidad | Descripción |
|---|-----------|------|-----------|-------------|
| 1 | UsuarioController.php | IDOR | 🔴 CRÍTICO | show(), update(), destroy() sin validación |
| 2 | RouteS/CourseTeamController | IDOR | ✅ RESUELTO | Implementada `CursoPolicy` robusta |
| 3 | DocenteCursoController | IDOR | 🟠 ALTO | Validación incompleta de docente/curso |
| 4 | Permissions endpoints | IDOR | 🟡 MEDIO | Sin validación contextual de usuario |
| 5 | CourseTeamController.index | Info Disc | 🟡 MEDIO | Exposición innecesaria de datos |
| 6 | Global | Brute Force | 🟡 MEDIO | Sin rate limiting |

---

## 🔐 Recomendaciones de Remediación

### Prioridad 1: CRÍTICO (Implementar inmediatamente)

#### 1.1 Refactorizar UsuarioController

```php
// ❌ ANTES
public function show($id, Request $request)
{
    $usuario = Usuario::findOrFail($id);
    return response()->json($usuario);
}

// ✅ DESPUÉS
public function show(Usuario $usuario, Request $request)
{
    // Route model binding + middleware validation
    $this->authorize('view', $usuario);
    
    // Usar Gate o Policy
    return response()->json($usuario);
}
```

**Acciones**:
- [ ] Implementar Laravel Policies para UsuarioController
- [ ] Agregar validaciones por rol (admin solo puede ver usuarios en su institución)
- [ ] Usar route model binding en lugar de $id manual
- [ ] Agregar auditoría de acceso

---

#### 1.2 Crear una Policy de Usuario

```php
// app/Policies/UsuarioPolicy.php
class UsuarioPolicy
{
    public function view(Usuario $user, Usuario $model): bool
    {
        // Solo admins pueden ver otros usuarios
        return $user->is_admin && $user->id_institucion === $model->id_institucion;
    }
    
    public function update(Usuario $user, Usuario $model): bool
    {
        return $this->view($user, $model);
    }
    
    public function delete(Usuario $user, Usuario $model): bool
    {
        return $user->is_admin && $user->is_super_admin;
    }
}
```

---

### Prioridad 2: ALTO (Próxima semana)

#### 2.1 Refactorizar CourseTeamController

```php
// ❌ ANTES
private function authorizeDocenteAccess(Curso $curso)
{
    if (request()->path() !== null && str_contains(request()->path(), '/docente/')) {
        // Inseguro: basado en PATH
    }
}

// ✅ DESPUÉS
private function authorizeDocenteAccess(Curso $curso)
{
    /** @var Usuario $user */
    $user = auth()->user();
    
    // Validación robusta
    if (!$user->docente) {
        abort(403);
    }
    
    // Verificar que el docente realmente dicta este curso
    $ownsCourse = $user->docente->seccionesQueDicta()
        ->where('id_curso', $curso->id_curso)
        ->exists();
    
    if (!$ownsCourse && !$user->is_admin) {
        abort(403, 'No tienes permiso para gestionar este curso.');
    }
}
```

#### 2.2 Crear Policy para Curso

```php
// app/Policies/CursoPolicy.php
class CursoPolicy
{
    public function manageTeam(Usuario $user, Curso $curso): bool
    {
        if ($user->is_admin) {
            return true;
        }
        
        if (!$user->docente) {
            return false;
        }
        
        return $user->docente->seccionesQueDicta()
            ->where('id_curso', $curso->id_curso)
            ->exists();
    }
}
```

---

#### 2.3 Proteger endpoints de permisos

```php
// Antes de procesar permisos:
public function syncMemberPermissions(Request $request, Curso $curso, Usuario $usuario)
{
    $this->authorize('manageTeam', $curso);
    
    // ✅ Validar que usuario es miembro del equipo
    $isMember = UsuarioRolAsignación::where('id_contexto', $curso->id_contexto)
        ->where('id_usuario_recipiente', $usuario->id_usuario)
        ->exists();
    
    if (!$isMember) {
        abort(404, 'Usuario no es miembro del equipo de este curso');
    }
    
    // ... resto del código
}
```

---

### Prioridad 3: MEDIO (Próximo mes)

#### 3.1 Implementar Rate Limiting

```php
// app/Http/Middleware/ThrottleRequests.php o en routes
Route::middleware('throttle:60,1')->group(function () {
    Route::resource('usuarios', UsuarioController::class);
});

// Para endpoints sensibles
Route::middleware('throttle:10,1')->post('/cursos/{curso}/team/{usuario}/sync-permissions', ...);
```

#### 3.2 Implementar Auditoría

```php
// app/Models/AuditLog.php
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'changes',
        'ip_address',
        'user_agent'
    ];
}

// Middleware o Observer
UsuarioObserver::created(function ($usuario) {
    AuditLog::create([
        'user_id' => auth()->id(),
        'action' => 'create',
        'model_type' => 'Usuario',
        'model_id' => $usuario->id,
        'ip_address' => request()->ip(),
    ]);
});
```

#### 3.3 Validar y sanitizar entrada

```php
// Siempre validar parámetros
public function show($id, Request $request)
{
    $id = (int) $id; // Asegura que es número
    $tipo = $request->validate(['tipo' => 'in:estudiante,docente,administrador'])['tipo'];
    // ...
}
```

#### 3.4 Implementar CORS y CSRF correctamente

```php
// Verificar que las solicitudes sean del mismo origen
// Ya está en: app/Http/Middleware/VerifyCsrfToken.php
```

---

## 🧪 Casos de Prueba para IDOR

### Caso 1: Intentar ver usuario con ID diferente (sin privilegios)

```bash
# Atacante logueado como docente intenta ver otro usuario
GET /admin/usuarios/5?tipo=estudiante
# Esperado: 403 Forbidden (falla actualmente - ✗ VULNERABLE)
```

### Caso 2: Intentar modificar equipo de otro docente

```bash
# Docente 1 intenta modificar equipo del Docente 2
POST /docente/cursos/99/team
{
    "id_usuario": 10,
    "role_name": "Ayudante"
}
# Esperado: 403 Forbidden (parece OK en código, necesita prueba)
```

### Caso 3: Intentar acceder a permisos de usuario no en el curso

```bash
POST /docente/cursos/1/team/999/sync-permissions
# Usuario 999 no está en el equipo del curso 1
# Esperado: 404 o 403 (actualmente falla - ✗ VULNERABLE)
```

---

## ✅ Checklist de Remediación

### Inmediato (Hoy - Semana 1)

- [ ] Refactorizar `UsuarioController` con route model binding
- [ ] Implementar `UsuarioPolicy`
- [ ] Implementar `CursoPolicy`
- [ ] Validar autorización en `CourseTeamController`
- [ ] Pruebas de IDOR en endpoints críticos

### Corto Plazo (Semanas 2-3)

- [ ] Refactorizar `DocenteCursoController`
- [ ] Validar contexto en endpoints de permisos
- [ ] Remover exposición innecesaria de datos (RUT en JSON)
- [ ] Implementar rate limiting
- [ ] Testing: casos de IDOR

### Mediano Plazo (Mes 1)

- [ ] Implementar auditoría de cambios
- [ ] Documentar autorización para cada endpoint
- [ ] Security training para equipo
- [ ] Pruebas de penetración
- [ ] Revisión de código de seguridad

---

## 📚 Referencias y Estándares

- **OWASP Top 10**: A01:2021 – Broken Access Control
- **CWE-639**: Authorization Bypass Through User-Controlled Key
- **OWASP Guide**: Insecure Direct Object Reference (IDOR)

---

## 🔗 Endpoints Verificados

✅ **Seguros** (con autorización):
- DocenteActivityController: show(), store(), update(), destroy() - Con validación de docente/curso
- DocenteCursoController: index() - Filtra solo cursos del docente
- DashboardController: index() - Redirige según rol

⚠️ **Parcialmente Seguros** (necesitan mejora):
- CourseTeamController: index(), store(), destroy() - Path-based auth
- Endpoints de permisos - Sin validación de pertenencia

❌ **Críticos** (vulnerables):
- UsuarioController: show(), update(), destroy() - Sin validación por rol/institución

---

## 📞 Contacto de Seguridad

Si descubres una vulnerabilidad de seguridad, por favor contacta a:
- **Email**: security@utamed.local
- **No reportes públicamente** hasta que sea parcheado

---

**Documento generado**: 2026-02-05  
**Próxima revisión recomendada**: 2026-03-05
