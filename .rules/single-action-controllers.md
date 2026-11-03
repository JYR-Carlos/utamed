# Single-Action Controllers Architecture Rule (API Module Only)

## 1. Principio Fundamental
Todo desarrollo de controladores para el **módulo de la API** (`app/Api/V1/Controllers/*` y `routes/api.php`), consumido por la aplicación móvil y clientes externos, debe implementarse exclusivamente bajo el patrón de **Single-Action Controllers** (Controladores de Acción Única / Invocables en Laravel).

* **Alcance estricto**: Esta regla aplica **únicamente al módulo de la API**. Los controladores web tradicionales existentes en `app/Http/Controllers/` que sirven vistas Inertia.js / Blade no están obligados a seguir este patrón.
* **Prohibición en la API**: Queda estrictamente prohibido crear controladores tradicionales multi-acción (con múltiples métodos como `index`, `show`, `store`, etc.) dentro de la estructura de la API.

---

## 2. Convenciones de Implementación en la API

### 2.1. Método Único `__invoke`
Cada controlador de la API representa un único endpoint / acción del sistema y debe implementar únicamente el método mágico `__invoke`:

```php
namespace App\Api\V1\Controllers\Student\Course;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Api\V1\Resources\Student\CourseDetailResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowCourseController extends Controller
{
    public function __invoke(Request $request, Curso $curso): JsonResponse
    {
        // 1. Verificación de permisos y scoping (anti-IDOR)
        // 2. Delegación a servicios de dominio
        // 3. Respuesta JSON estructurada mediante JsonResource / DTO
        return response()->json(new CourseDetailResource($curso));
    }
}
```

### 2.2. Nomenclatura y Organización de Directorios
* **Organización por Dominio dentro de Student**: Dentro del módulo `Student/` (`App\Api\V1\Controllers\Student\`), los controladores **no deben dejarse de forma plana en la raíz de `Student/`**. Deben organizarse obligatoriamente en subdirectorios por **Dominio** temático (ej.: `Student/Course/ShowCourseController`, `Student/Profile/GetProfileController`, `Student/Dashboard/GetDashboardController`, `Student/Activity/ShowActivityController`).
* **Estructura jerárquica de la API**:
  ```
  app/Api/V1/Controllers/
  ├── Auth/
  │   ├── ChangeExpiredPasswordController.php
  │   ├── LoginController.php
  │   ├── LogoutController.php
  │   └── MeController.php
  └── Student/
      ├── Activity/
      │   ├── DownloadEnunciadoController.php
      │   ├── DownloadEntregaController.php
      │   ├── ListActivitiesController.php
      │   └── ShowActivityController.php
      ├── Agenda/
      │   ├── DestroyEntregaController.php
      │   ├── ListAgendaMessagesController.php
      │   ├── MarkAgendaAsReadController.php
      │   ├── StoreAgendaMessageController.php
      │   └── StoreEntregaController.php
      ├── Course/
      │   ├── GetCoursesController.php
      │   ├── ShowCourseController.php
      │   ├── ShowCourseProgramaController.php
      │   └── ShowCourseRendimientoController.php
      ├── Dashboard/
      │   └── GetDashboardController.php
      ├── Mensajero/
      │   ├── ListChannelsController.php
      │   ├── ShowChannelMessagesController.php
      │   └── StoreChannelMessageController.php
      └── Profile/
          └── GetProfileController.php
  ```
* **Nombre de Dominio**: Usar nombres de dominio en singular o entidad coherente (`Course`, `Profile`, `Dashboard`, `Activity`, `Agenda`, `Mensajero`).
* **Nombre de Clase Semántico**: Verbo de acción + Recurso + `Controller` (ej.: `ShowCourseController`, `GetCoursesController`, `StoreEntregaController`, `DownloadEnunciadoController`, `GetDashboardController`).

### 2.3. Definición de Rutas API Limpia y Desacoplamiento de Rutas
En `routes/api.php`, registrar las rutas apuntando directamente a la clase del controlador sin sintaxis de array para métodos:

```php
use App\Api\V1\Controllers\Student\Course\GetCoursesController;
use App\Api\V1\Controllers\Student\Course\ShowCourseController;
use App\Api\V1\Controllers\Student\Dashboard\GetDashboardController;
use App\Api\V1\Controllers\Student\Profile\GetProfileController;

// En routes/api.php:
Route::middleware(['auth:sanctum', 'is_student_api', 'force_password_change_api'])->prefix('estudiante')->group(function () {
    Route::get('perfil', GetProfileController::class)->name('api.v1.estudiante.perfil');
    Route::get('dashboard', GetDashboardController::class)->name('api.v1.estudiante.dashboard');
    Route::get('cursos', GetCoursesController::class)->name('api.v1.estudiante.cursos');
    Route::get('cursos/{curso}', ShowCourseController::class)->name('api.v1.estudiante.cursos.show');
});
```

* **Regla estricta de desacoplamiento con `routes/api.php`**: La organización en subdirectorios por dominio (`Student/Course/ShowCourseController`) es un patrón interno de código. **No debe alterar ni modificar las rutas definidas en `routes/api.php`** (las URLs como `cursos/{curso}`, `perfil`, `dashboard`, los prefijos de grupo como `estudiante` y los nombres de ruta `api.v1.estudiante.*` permanecen exactamente iguales y desacoplados de la estructura de carpetas).

---

## 3. Responsabilidades y Límites Arquitectónicos de la API

1. **Inyección de Dependencias Acotada**: El controlador inyecta en el constructor o en `__invoke` únicamente las dependencias necesarias para esa acción concreta.
2. **Form Requests Específicos**: Las operaciones de escritura (`POST`, `PUT`, `PATCH`, `DELETE`) validan mediante un `FormRequest` dedicado en `app/Api/V1/Requests/...`.
3. **Delegación a Servicios de Dominio**: El controlador orquesta la petición y delega la lógica de negocio pesada a servicios (`AgendaArchiveHandler`, `DetalleCursoEstudiante`, `DashboardEstudiante`, `FotoIntranetService`, etc.).
4. **Respuestas JSON Consistentes**: Devolver siempre DTOs o `JsonResource` estructurados en `app/Api/V1/Resources/...` con códigos de estado HTTP semánticos (`200 OK`, `201 Created`, `204 No Content`, `401 Unauthorized`, `403 Forbidden`, `422 Unprocessable Entity`).
