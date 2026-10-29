# Single-Action Controllers Architecture Rule (API Module Only)

## 1. Principio Fundamental
Todo desarrollo de controladores para el **módulo de la API** (`app/Http/Controllers/Api/*` y `routes/api.php`), consumido por la aplicación móvil y clientes externos, debe implementarse exclusivamente bajo el patrón de **Single-Action Controllers** (Controladores de Acción Única / Invocables en Laravel).

* **Alcance estricto**: Esta regla aplica **únicamente al módulo de la API**. Los controladores web tradicionales existentes en `app/Http/Controllers/` que sirven vistas Inertia.js / Blade no están obligados a seguir este patrón.
* **Prohibición en la API**: Queda estrictamente prohibido crear controladores tradicionales multi-acción (con múltiples métodos como `index`, `show`, `store`, etc.) dentro de la estructura de la API.

---

## 2. Convenciones de Implementación en la API

### 2.1. Método Único `__invoke`
Cada controlador de la API representa un único endpoint / acción del sistema y debe implementar únicamente el método mágico `__invoke`:

```php
namespace App\Http\Controllers\Api\V1\Student\Courses;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Http\Resources\Api\Student\CursoDetalleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowCourseController extends Controller
{
    public function __invoke(Request $request, Curso $curso): JsonResponse
    {
        // 1. Verificación de permisos y scoping (anti-IDOR)
        // 2. Delegación a servicios de dominio
        // 3. Respuesta JSON estructurada mediante JsonResource / DTO
        return response()->json(new CursoDetalleResource($curso));
    }
}
```

### 2.2. Nomenclatura y Organización de Directorios
* **Estructura jerárquica por dominio y versión**: Los controladores de la API deben agruparse en subdirectorios temáticos:
  ```
  app/Http/Controllers/Api/V1/Student/
  ├── Auth/
  │   ├── LoginController.php
  │   ├── LogoutController.php
  │   └── ShowAuthenticatedStudentController.php
  ├── Dashboard/
  │   └── ShowDashboardController.php
  ├── Profile/
  │   ├── ShowProfileController.php
  │   └── ForcePasswordChangeController.php
  ├── Courses/
  │   ├── ListCoursesController.php
  │   ├── ShowCourseController.php
  │   └── ShowCourseRendimientoController.php
  ├── Activities/
  │   ├── ListActivitiesController.php
  │   ├── ShowActivityController.php
  │   ├── DownloadEnunciadoController.php
  │   └── DownloadEntregaController.php
  ├── Agenda/
  │   ├── ListAgendaMessagesController.php
  │   ├── StoreAgendaMessageController.php
  │   ├── StoreEntregaController.php
  │   ├── DestroyEntregaController.php
  │   └── MarkAgendaAsReadController.php
  └── Mensajero/
      ├── ListChannelsController.php
      ├── ShowChannelMessagesController.php
      └── StoreChannelMessageController.php
  ```
* **Nombre de Clase Semántico**: Verbo de acción + Recurso + `Controller` (ej.: `StoreEntregaController`, `DownloadEnunciadoController`, `ShowDashboardController`).

### 2.3. Definición de Rutas API Limpia
En `routes/api.php`, registrar las rutas apuntando directamente a la clase del controlador sin sintaxis de array para métodos:

```php
// Correcto (Invocable):
Route::get('cursos/{curso}', ShowCourseController::class)->name('api.v1.student.cursos.show');
Route::post('grupos-asignados/{grupo}/entregas', StoreEntregaController::class)->name('api.v1.student.entregas.store');

// Incorrecto (Multi-acción / Array en API):
// Route::get('cursos/{curso}', [CourseController::class, 'show']);
```

---

## 3. Responsabilidades y Límites Arquitectónicos de la API

1. **Inyección de Dependencias Acotada**: El controlador inyecta en el constructor o en `__invoke` únicamente las dependencias necesarias para esa acción concreta.
2. **Form Requests Específicos**: Las operaciones de escritura (`POST`, `PUT`, `PATCH`, `DELETE`) validan mediante un `FormRequest` dedicado en `app/Http/Requests/Api/...`.
3. **Delegación a Servicios de Dominio**: El controlador orquesta la petición y delega la lógica de negocio pesada a servicios (`AgendaArchiveHandler`, `RendimientoCursoEstudiante`, `FotoIntranetService`, etc.).
4. **Respuestas JSON Consistentes**: Devolver siempre DTOs o `JsonResource` estructurados con códigos de estado HTTP semánticos (`200 OK`, `201 Created`, `204 No Content`, `401 Unauthorized`, `403 Forbidden`, `422 Unprocessable Entity`).
