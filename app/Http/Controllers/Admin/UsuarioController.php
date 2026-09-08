<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LimitsPageSize;
use App\Http\Controllers\Controller;
use App\Rules\RutValido;
use App\Services\Authorization\DelegationAuthorizer;
use App\Services\Authorization\GlobalContextService;
use App\Services\Authorization\PermissionCache;
use App\Support\Permissions;
use App\Support\Rut;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Docente;
use App\Models\Administrativo\Carrera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\UsuarioPermisoEspecial;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\UsuarioResource;
use App\Imports\UsuariosImport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Controlador para la gestión integral de usuarios del sistema.
 * 
 * Tablas implicadas:
 * - usuario.usuario: Base de todos los usuarios del sistema
 * - usuario.estudiante: Perfil específico de estudiantes con carrera asignada
 * - usuario.docente: Perfil específico de docentes con grado, título y cargo
 * - usuario.rol: Definiciones de roles disponibles (Docente, Estudiante, Ayudante, etc.)
 * - usuario.permiso: Definiciones de permisos especiales asignables
 * - usuario.usuario_rol_asignación: Asignaciones de roles a usuarios en contextos específicos
 * - usuario.usuario_permiso_especial: Asignaciones de permisos especiales a usuarios
 * - usuario.contexto: Contextos (cursos) en los que roles y permisos se aplican
 * 
 * SEGURIDAD CRÍTICA: Este controlador maneja Control de Acceso Basado en Roles (RBAC).
 * Históricamente se encontraron vulnerabilidades IDOR en esta gestión.
 * Siempre validar que el usuario autenticado tenga permisos de administración para las operaciones.
 * 
 * Gestiona:
 * - Creación/actualización/eliminación de usuarios (Estudiantes, Docentes, Administradores)
 * - Asignación de roles y permisos a usuarios
 * - Sincronización de datos de usuario y sus roles en contextos
 * - Búsqueda filtrada por tipo de usuario
 * 
 * Arquitectura:
 * - index(): Lista usuarios filtrados por tipo con paginación y búsqueda
 * - store(): Dispatcher que delega a métodos específicos por tipo
 * - storeEstudiante/storeDocente/storeAdministrador: Creación transaccional por tipo
 * - show/update/destroy: Operaciones estándar con validaciones RBAC
 */
class UsuarioController extends Controller
{
    use LimitsPageSize;

    /**
     * Tope de filas de datos por importación masiva.
     *
     * Acota memoria y duración de la transacción: sin él, un `.xlsx` de 5 MB
     * descomprime a cientos de miles de filas y bloquea la tabla `usuario`
     * mientras dura la inserción.
     */
    private const MAX_FILAS_IMPORTACION = 1000;

    /**
     * Obtiene listado paginado y filtrable de usuarios por tipo.
     * 
     * Los usuarios pueden tener datos asociados en las tablas de estudiante o docente, o ninguno (no es funcionario).
     * 
     * La búsqueda la resuelve {@see Usuario::scopeBuscar()}: palabra a palabra
     * sobre nombres, apellidos, username, email y RUT, sin distinguir mayúsculas,
     * acentos ni formato de RUT.
     * Retorna HTML Inertia con usuarios, roles, permisos disponibles, y datos de carreras.
     * 
     * @param  Request  $request  Parámetros: tipo (estudiante|docente|administrador), search, per_page
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse  Vista HTML o JSON según Accept header
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Usuario::class);

        // Determinar qué tipo de usuario listar
        $tipo = $request->input('tipo', 'estudiante'); // estudiante, docente, or administrador

        // Parámetros de ordenamiento (whitelisted por tipo más abajo)
        $sortKey = $request->input('sort_key');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        // Término del buscador: vacío o sólo espacios equivale a no filtrar
        $search = trim((string) $request->input('search', ''));

        // ==================== ESTUDIANTES ====================
        // Recuperar estudiantes con sus datos de usuario y carrera
        if ($tipo === 'estudiante') {
            $query = Estudiante::with(['usuario', 'usuario.docente', 'carrera']);

            // Buscar por nombre completo, RUT, username o email
            if ($search !== '') {
                $query->whereHas('usuario', fn($q) => $q->buscar($search));
            }

            // Whitelist: frontend dot-key → columna SQL real
            $estudianteSortWhitelist = [
                'estudiante.id_estudiante' => 'estudiante.id_estudiante',
                'usuario.rut' => 'usuario.rut',
                'usuario.nombre1' => 'usuario.nombre1',
                'usuario.apellido1' => 'usuario.apellido1',
                'estudiante.agno_ingreso' => 'estudiante.agno_ingreso',
                'estudiante.carrera.nombre' => 'carrera.nombre',
            ];

            // Unir opcionalmente con carrera si se ordena por nombre de carrera
            if ($sortKey === 'estudiante.carrera.nombre') {
                $query->leftJoin('carrera', 'estudiante.id_carrera', '=', 'carrera.id_carrera');
            }

            $sqlColumn = $estudianteSortWhitelist[$sortKey] ?? null;

            // Unir con tabla usuario, ordenar y paginar
            $q = $query->join('usuario', 'estudiante.id_usuario', '=', 'usuario.id_usuario')
                ->select('estudiante.*');

            $q->orderByDesc('usuario.esta_activo'); // Activos primero, inactivos al final

            if ($sqlColumn) {
                $q->orderBy($sqlColumn, $sortDir);
            } else {
                $q->orderBy('usuario.apellido1')
                  ->orderBy('usuario.apellido2')
                  ->orderBy('usuario.nombre1')
                  ->orderBy('usuario.nombre2');
            }
            $usuarios = $q->paginate($this->perPage($request))->withQueryString();
        }

        // ==================== DOCENTES ====================
        // Recuperar docentes con sus datos de usuario
        elseif ($tipo === 'docente') {
            $query = Docente::with(['usuario', 'usuario.estudiante']);

            // Buscar por nombre completo, RUT, username o email
            if ($search !== '') {
                $query->whereHas('usuario', fn($q) => $q->buscar($search));
            }

            $docenteSortWhitelist = [
                'docente.id_docente' => 'docente.id_docente',
                'usuario.rut' => 'usuario.rut',
                'usuario.nombre1' => 'usuario.nombre1',
                'usuario.apellido1' => 'usuario.apellido1',
                'docente.grado' => 'docente.grado',
                'docente.cargo' => 'docente.cargo',
            ];

            $sqlColumn = $docenteSortWhitelist[$sortKey] ?? null;

            // Unir con tabla usuario, ordenar y paginar
            $q = $query->join('usuario', 'docente.id_usuario', '=', 'usuario.id_usuario')
                ->select('docente.*');

            $q->orderByDesc('usuario.esta_activo'); // Activos primero, inactivos al final

            if ($sqlColumn) {
                $q->orderBy($sqlColumn, $sortDir);
            } else {
                $q->orderBy('usuario.apellido1')
                    ->orderBy('usuario.apellido2')
                    ->orderBy('usuario.nombre1')
                    ->orderBy('usuario.nombre2');
            }
            $usuarios = $q->paginate($this->perPage($request))->withQueryString();
        }
        // ==================== ADMINISTRADORES ====================
        // Recuperar usuarios que no son docente ni estudiante
        else {
            $query = Usuario::query()
                ->whereDoesntHave('docente')
                ->whereDoesntHave('estudiante');

            // Buscar por nombre completo, RUT, username o email
            if ($search !== '') {
                $query->buscar($search);
            }

            $adminSortWhitelist = [
                'usuario.id_usuario' => 'id_usuario',
                'usuario.rut' => 'rut',
                'usuario.username' => 'username',
                'usuario.nombre1' => 'nombre1',
                'usuario.apellido1' => 'apellido1',
                'usuario.email' => 'email',
            ];

            $sqlColumn = $adminSortWhitelist[$sortKey] ?? null;

            $query->orderByDesc('esta_activo'); // Activos primero, inactivos al final

            // Ordenar y paginar
            if ($sqlColumn) {
                $query->orderBy($sqlColumn, $sortDir);
            } else {
                $query->orderBy('nombre1')->orderBy('apellido1');
            }
            $usuarios = $query->paginate($this->perPage($request))->withQueryString();
        }

        // Normalizar cada item al contrato { usuario, estudiante, docente } sin romper
        // la estructura del paginador (current_page, last_page, links, etc.)
        $usuarios->through(fn($item) => (new UsuarioResource($item))->toArray(request()));

        // Retornar JSON si lo solicita el cliente
        if ($request->wantsJson()) {
            return response()->json($usuarios);
        }

        // Cargar datos complementarios para el frontend
        $carreras = Carrera::orderBy('nombre')->get();

        // Renderizar vista Inertia con todos los datos
        return Inertia::render('admin/Usuarios', [
            'usuarios' => $usuarios,
            'tipo' => $tipo,
            'carreras' => $carreras,
            // El formato del archivo de importación se declara en el servidor
            // y se muestra en el modal, de modo que la pantalla y la plantilla
            // descargable no puedan describir columnas distintas.
            'columnasImportacion' => self::columnasImportacion($tipo),
        ]);
    }

    /**
     * Dispatcher para crear nuevo usuario según tipo especificado.
     * 
     * Valida que el request incluya 'tipo' y delega a método específico:
     * - 'estudiante' → storeEstudiante()
     * - 'docente' → storeDocente()
     * - otro → storeAdministrador()
     * 
     * @param  Request  $request  Datos del usuario: tipo, rut, nombres, etc.
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    public function store(Request $request)
    {
        $this->authorize('create', Usuario::class);

        // Determinar tipo de usuario a crear y delegar al método correspondiente
        $tipo = $request->input('tipo');

        $this->normalizarRutDelRequest($request);

        if ($tipo === 'estudiante') {
            return $this->storeEstudiante($request);
        } elseif ($tipo === 'docente') {
            return $this->storeDocente($request);
        } else {
            return $this->storeAdministrador($request);
        }
    }

    /**
     * Dispatcher para importar usuarios según tipo especificado.
     *
     * Valida que el request incluya el archivo con los datos correspondientes a
     * cada rol y lo procesa **por bloques** con un tope de filas
     * ({@see self::MAX_FILAS_IMPORTACION}).
     *
     * La importación sigue siendo síncrona y transaccional —el administrador ve
     * el resultado en la misma respuesta y un archivo con una fila mala no deja
     * usuarios a medias—, pero ya no carga el archivo entero en memoria ni puede
     * mantener abierta una transacción de duración arbitraria: el tope acota el
     * trabajo. Para volúmenes mayores hay que pasar a cola, lo que exige además
     * una pantalla de estado que hoy no existe.
     *
     * @param  \Illuminate\Http\Request  $request  Datos: file, tipo
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    public function import(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('create', Usuario::class);

        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
            'tipo' => 'required|in:estudiante,docente,administrador'
        ]);

        $tipo = $request->input('tipo');

        $importacion = new UsuariosImport(
            procesarFila: function (array $fila, int $numeroFilaExcel) use ($tipo): void {
                $datos = $this->mapearFila($fila, $tipo);
                $this->validarFila($datos, $tipo, $numeroFilaExcel);

                match ($tipo) {
                    'estudiante'    => $this->insertarEstudianteBd($datos),
                    'docente'       => $this->insertarDocenteBd($datos),
                    'administrador' => $this->insertarAdministradorBd($datos),
                };
            },
            maxFilas: self::MAX_FILAS_IMPORTACION
        );

        try {
            DB::beginTransaction();

            Excel::import($importacion, $request->file('file'));

            DB::commit();

            $contador = $importacion->totalProcesadas();

            // Redirección exitosa a la vista del tipo de usuario importado
            return redirect()->route('admin.usuarios.index', ['tipo' => $tipo])
                ->with('success', "Se han importado {$contador} usuarios correctamente.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            // Retorna a la vista anterior con el primer error de validación encontrado
            $primerError = collect($e->errors())->flatten()->first();
            return back()->withErrors(['error' => $primerError])->withInput();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error en importación masiva', [
                'tipo'  => $tipo,
                'error' => $e->getMessage(),
            ]);

            // El mensaje interno describe el servidor (SQL, rutas, nombres de
            // tabla): va al log, no a la pantalla del administrador.
            return back()->withErrors([
                'error' => 'No se pudo procesar el archivo. Revisa que el formato y las columnas sean los esperados.',
            ]);
        }
    }

    /**
     * Columnas que espera el archivo de importación, en orden, por tipo.
     *
     * Es la única definición del formato: de aquí salen la plantilla que se
     * descarga y la especificación que ve el administrador en pantalla, de
     * modo que no puedan desincronizarse con {@see self::mapearFila()}.
     *
     * @return array<int, array{campo: string, etiqueta: string, obligatorio: bool, ejemplo: string}>
     */
    public static function columnasImportacion(string $tipo): array
    {
        $comunes = [
            ['campo' => 'rut',       'etiqueta' => 'RUT',             'obligatorio' => true,  'ejemplo' => '12345678-9'],
            ['campo' => 'nombre1',   'etiqueta' => 'Primer nombre',   'obligatorio' => true,  'ejemplo' => 'Juan'],
            ['campo' => 'nombre2',   'etiqueta' => 'Segundo nombre',  'obligatorio' => false, 'ejemplo' => 'Carlos'],
            ['campo' => 'apellido1', 'etiqueta' => 'Primer apellido', 'obligatorio' => true,  'ejemplo' => 'González'],
            ['campo' => 'apellido2', 'etiqueta' => 'Segundo apellido', 'obligatorio' => false, 'ejemplo' => 'Pérez'],
            ['campo' => 'email',     'etiqueta' => 'Email',           'obligatorio' => false, 'ejemplo' => 'juan@ejemplo.cl'],
            ['campo' => 'username',  'etiqueta' => 'Usuario',         'obligatorio' => true,  'ejemplo' => 'jgonzalez'],
            ['campo' => 'password',  'etiqueta' => 'Contraseña',      'obligatorio' => true,  'ejemplo' => 'Utamed2026'],
        ];

        return match ($tipo) {
            'estudiante' => [
                ...$comunes,
                ['campo' => 'agno_ingreso', 'etiqueta' => 'Año de ingreso', 'obligatorio' => false, 'ejemplo' => '2026'],
                ['campo' => 'id_carrera',   'etiqueta' => 'ID de carrera',  'obligatorio' => false, 'ejemplo' => '1'],
            ],
            'docente' => [
                ...$comunes,
                ['campo' => 'grado',  'etiqueta' => 'Grado académico', 'obligatorio' => false, 'ejemplo' => 'Magíster'],
                ['campo' => 'titulo', 'etiqueta' => 'Título',          'obligatorio' => false, 'ejemplo' => 'Ingeniero Civil'],
                ['campo' => 'cargo',  'etiqueta' => 'Cargo',           'obligatorio' => false, 'ejemplo' => 'Profesor Titular'],
            ],
            default => $comunes,
        };
    }

    /**
     * GET /admin/usuarios/plantilla-importacion
     *
     * Devuelve un CSV con la fila de encabezados y una fila de ejemplo. Sin
     * esto, el administrador tenía que adivinar el orden de las columnas y
     * sólo descubría el error cuando la importación fallaba entera.
     */
    public function plantillaImportacion(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('create', Usuario::class);

        $tipo = $request->input('tipo', 'estudiante');
        abort_unless(in_array($tipo, ['estudiante', 'docente', 'administrador'], true), 404);

        $columnas = self::columnasImportacion($tipo);

        return response()->streamDownload(function () use ($columnas) {
            $salida = fopen('php://output', 'w');
            // BOM para que Excel abra el CSV en UTF-8 y no rompa los acentos.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, array_column($columnas, 'etiqueta'));
            fputcsv($salida, array_column($columnas, 'ejemplo'));
            fclose($salida);
        }, "plantilla-{$tipo}s.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * POST /admin/usuarios/importar/previsualizar
     *
     * Ejecuta la misma validación que la importación real pero sin escribir
     * nada, y devuelve TODOS los errores encontrados en vez de detenerse en
     * el primero. Es lo que permite que el administrador vea qué filas están
     * mal —y en qué— antes de confirmar.
     *
     * @return JsonResponse
     */
    public function previsualizarImportacion(Request $request): JsonResponse
    {
        $this->authorize('create', Usuario::class);

        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
            'tipo' => 'required|in:estudiante,docente,administrador',
        ]);

        $tipo = $request->input('tipo');

        $filasValidas = 0;
        $errores      = [];
        // Los RUT y usuarios repetidos DENTRO del archivo no los detecta
        // `Rule::unique`, que sólo mira la base de datos.
        $rutsVistos      = [];
        $usernamesVistos = [];

        $importacion = new UsuariosImport(
            procesarFila: function (array $fila, int $numeroFilaExcel) use (
                $tipo,
                &$filasValidas,
                &$errores,
                &$rutsVistos,
                &$usernamesVistos
            ): void {
                $datos = $this->mapearFila($fila, $tipo);

                $mensajes = $this->erroresDeFila($datos, $tipo);

                if ($datos['rut'] && isset($rutsVistos[$datos['rut']])) {
                    $mensajes[] = "El RUT se repite en la fila {$rutsVistos[$datos['rut']]} del archivo.";
                } elseif ($datos['rut']) {
                    $rutsVistos[$datos['rut']] = $numeroFilaExcel;
                }

                if ($datos['username'] && isset($usernamesVistos[$datos['username']])) {
                    $mensajes[] = "El usuario se repite en la fila {$usernamesVistos[$datos['username']]} del archivo.";
                } elseif ($datos['username']) {
                    $usernamesVistos[$datos['username']] = $numeroFilaExcel;
                }

                if ($mensajes === []) {
                    $filasValidas++;
                    return;
                }

                $errores[] = [
                    'fila'      => $numeroFilaExcel,
                    'rut'       => $datos['rut'],
                    'nombre'    => trim(($datos['nombre1'] ?? '') . ' ' . ($datos['apellido1'] ?? '')),
                    'problemas' => $mensajes,
                ];
            },
            maxFilas: self::MAX_FILAS_IMPORTACION
        );

        try {
            Excel::import($importacion, $request->file('file'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al previsualizar importación', ['tipo' => $tipo, 'error' => $e->getMessage()]);

            return response()->json([
                'ok'      => false,
                'mensaje' => 'No se pudo leer el archivo. Revisa que sea .xlsx o .csv y que tenga la fila de encabezados.',
            ], 422);
        }

        return response()->json([
            'ok'            => true,
            'total'         => $importacion->totalProcesadas(),
            'validas'       => $filasValidas,
            'con_problemas' => count($errores),
            // Se acotan para no devolver miles de errores a la pantalla.
            'errores'       => array_slice($errores, 0, 50),
            'errores_omitidos' => max(0, count($errores) - 50),
        ]);
    }

    /**
     * Errores de validación de una fila, sin lanzar excepción.
     *
     * {@see self::validarFila()} corta en el primer fallo porque la
     * importación real es transaccional; la previsualización necesita
     * justamente lo contrario: verlos todos.
     *
     * @return array<int, string>
     */
    private function erroresDeFila(array $datos, string $tipo): array
    {
        $validador = Validator::make($datos, $this->reglasDeFila($tipo));

        return $validador->fails() ? $validador->errors()->all() : [];
    }

    /**
     * Deja el RUT del request en el formato con el que se guarda ({@see Rut}).
     *
     * Tiene que pasar ANTES de validar: las reglas `unique` comparan texto, así
     * que un "12.345.678-9" sin normalizar no choca con el "12345678-9" que ya
     * está en la base y la persona termina duplicada.
     */
    private function normalizarRutDelRequest(Request $request): void
    {
        if ($request->has('rut')) {
            $request->merge(['rut' => Rut::normalizar($request->input('rut'))]);
        }
    }

    /**
     * Reglas del RUT al editar: formato conocido y que no sea el de otra persona.
     *
     * Al crear no se exige único para estudiante y docente, porque ahí un RUT
     * repetido significa "esta persona ya existe, súmale este perfil". Editar es
     * otra cosa: cambiar el RUT al de otro usuario sólo puede ser un error.
     *
     * @return array<int,mixed>
     */
    private function reglasRutUnico(Usuario $usuario): array
    {
        return [
            'required',
            'string',
            'max:20',
            new RutValido,
            Rule::unique(Usuario::class, 'rut')->ignore($usuario->id_usuario, 'id_usuario'),
        ];
    }
    /**
     * Crea nuevo usuario Estudiante de forma transaccional.
     * 
     * Crea registro base Usuario y vincula perfil Estudiante con carrera.
     * Valida RUT único, username único (máx 10 caracteres).
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos: rut, nombre1, apellido1, email, agno_ingreso, id_carrera, username, password
     * @return \Illuminate\Http\RedirectResponse  Redirección a lista estudiantes con mensaje
     */
    private function storeEstudiante(Request $request)
    {
        $validated = $request->validate([
            'rut'          => ['required', 'string', 'max:20', new RutValido],
            'nombre1'      => 'required|string|max:100',
            'nombre2'      => 'nullable|string|max:100',
            'apellido1'    => 'required|string|max:100',
            'apellido2'    => 'nullable|string|max:100',
            'email'        => 'nullable|email|max:255',
            'agno_ingreso' => 'nullable|integer|min:1900|max:2100',
            'id_carrera'   => ['nullable', Rule::exists(Carrera::class, 'id_carrera')],
            'username'     => ['required', 'string', 'max:10', Rule::unique(Usuario::class, 'username')],
            // Password::defaults() es la política del sistema (AppServiceProvider).
            // Antes aquí había min:6 y en cambio-de-contraseña min:8+letras+números,
            // de modo que el alta aceptaba claves que luego el propio sistema
            // rechazaba al primer cambio.
            'password'     => ['required', 'string', Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            $this->insertarEstudianteBd($validated);
            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'estudiante'])
                ->with('success', 'Estudiante creado exitosamente.');
        } catch (ValidationException $e) {
            // "Ese RUT ya es estudiante" es un problema del formulario, no del
            // servidor: vuelve al campo en vez de disfrazarse de error interno.
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating estudiante: ' . $e->getMessage(), ['data' => $validated]);
            return back()->withErrors(['error' => 'Error al crear estudiante: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Crea nuevo usuario Docente de forma transaccional.
     * 
     * Crea registro base Usuario y vincula perfil Docente con grado, título, cargo.
     * Valida RUT único, username único (máx 10 caracteres).
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos: rut, nombre1, apellido1, email, grado, titulo, cargo, username, password
     * @return \Illuminate\Http\RedirectResponse  Redirección a lista docentes con mensaje
     */
    private function storeDocente(Request $request)
    {
        $validated = $request->validate([
            'rut'       => ['required', 'string', 'max:20', new RutValido],
            'nombre1'   => 'required|string|max:100',
            'nombre2'   => 'nullable|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'email'     => 'nullable|email|max:255',
            'grado'     => 'nullable|string|max:100',
            'titulo'    => 'nullable|string|max:255',
            'cargo'     => 'nullable|string|max:100',
            'username'  => ['required', 'string', 'max:10', Rule::unique(Usuario::class, 'username')],
            'password'  => ['required', 'string', Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            $this->insertarDocenteBd($validated);
            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'docente'])
                ->with('success', 'Docente creado exitosamente.');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating docente: ' . $e->getMessage(), ['data' => $validated]);
            return back()->withErrors(['error' => 'Error al crear docente: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Crea nuevo usuario Administrador.
     * 
     * Crea registro base Usuario sin perfil específico (diferenciador de Estudiante/Docente).
     * Valida RUT único, username único (máx 30 caracteres para mayor flexibilidad).
     * 
     * @param  Request  $request  Datos: rut, nombre1, apellido1, email, username, password
     * @return \Illuminate\Http\RedirectResponse  Redirección a lista administradores con mensaje
     */
    private function storeAdministrador(Request $request)
    {
        $validated = $request->validate([
            'rut'       => ['required', 'string', 'max:20', new RutValido, Rule::unique(Usuario::class, 'rut')],
            'nombre1'   => 'required|string|max:255',
            'nombre2'   => 'nullable|string|max:255',
            'apellido1' => 'required|string|max:255',
            'apellido2' => 'nullable|string|max:255',
            'email'     => 'nullable|email|max:255',
            'username'  => ['required', 'string', 'max:30', Rule::unique(Usuario::class, 'username')],
            'password'  => ['required', 'string', Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            $this->insertarAdministradorBd($validated);
            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'administrador'])
                ->with('success', 'Administrador creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating administrador: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error al crear administrador: ' . $e->getMessage()])->withInput();
        }
    }

    
    /**
     * BLOQUE DE INSERSIONES EN DB,
     * USADO EN -storeEstudiante()
     *          -storeDocente()
     *          -storeAdministrador()
     * 
     * 
     * 
     */

    
    private function insertarUsuarioBaseBd(array $datos): Usuario
    {   
        $usuario = Usuario::
            where('rut', $datos['rut'])->first();

        if (!$usuario) {
            $usuario = Usuario::create([
                'username'    => $datos['username'],
                'passhash'    => Hash::make($datos['password']),
                'rut'         => $datos['rut'],
                'nombre1'     => $datos['nombre1'],
                'nombre2'     => $datos['nombre2'] ?? null,
                'apellido1'   => $datos['apellido1'],
                'apellido2'   => $datos['apellido2'] ?? null,
                'email'       => $datos['email'] ?? null,
                'esta_activo' => true
            ]);
        }
        return $usuario;
    }

    private function insertarEstudianteBd(array $datos): Usuario
    {
        $usuario = $this->insertarUsuarioBaseBd($datos);

        // El usuario base se reutiliza a propósito (una misma persona puede ser
        // docente y estudiante), pero el perfil no: sin este corte, cargar dos
        // veces el mismo RUT deja dos filas en usuario.estudiante y el listado
        // muestra al alumno duplicado.
        $this->exigirPerfilInexistente(
            Estudiante::where('id_usuario', $usuario->id_usuario)->exists(),
            "Ya existe un estudiante con el RUT {$usuario->rut}."
        );

        Estudiante::create([
            'id_usuario'   => $usuario->id_usuario,
            'agno_ingreso' => $datos['agno_ingreso'] ?? null,
            'id_carrera'   => $datos['id_carrera'] ?? null,
        ]);

        $this->assignRole($usuario, 'estudiante');
        return $usuario;
    }

    private function insertarDocenteBd(array $datos): Usuario
    {
        $usuario = $this->insertarUsuarioBaseBd($datos);

        $this->exigirPerfilInexistente(
            Docente::where('id_usuario', $usuario->id_usuario)->exists(),
            "Ya existe un docente con el RUT {$usuario->rut}."
        );

        Docente::create([
            'id_usuario' => $usuario->id_usuario,
            'grado'      => $datos['grado'] ?? null,
            'titulo'     => $datos['titulo'] ?? null,
            'cargo'      => $datos['cargo'] ?? null,
        ]);

        $this->assignRole($usuario, 'docente');
        return $usuario;
    }

    private function insertarAdministradorBd(array $datos): Usuario
    {
        $usuario = $this->insertarUsuarioBaseBd($datos);
        $this->assignRole($usuario, 'SuperAdmin');
        return $usuario;
    }

    /**
     * Corta la creación cuando la persona ya tiene ese perfil, con un error de
     * validación sobre el campo RUT (que es el dato repetido) en vez de una
     * segunda fila silenciosa.
     */
    private function exigirPerfilInexistente(bool $yaExiste, string $mensaje): void
    {
        if ($yaExiste) {
            throw ValidationException::withMessages(['rut' => $mensaje]);
        }
    }


    private function mapearFila(array $fila, string $tipo): array
    {
        $datos = [
            // Las planillas vienen escritas a mano: con puntos, sin ellos, con la
            // K en minúscula. Se unifica aquí, antes de validar y de insertar.
            'rut'       => Rut::normalizar($fila[0] ?? null),
            'nombre1'   => $fila[1] ?? null,
            'nombre2'   => $fila[2] ?? null,
            'apellido1' => $fila[3] ?? null,
            'apellido2' => $fila[4] ?? null,
            'email'     => $fila[5] ?? null,
            'username'  => $fila[6] ?? null,
            'password'  => $fila[7] ?? null,
        ];

        if ($tipo === 'estudiante') {
            $datos['agno_ingreso'] = $fila[8] ?? null;
            $datos['id_carrera']   = $fila[9] ?? null;
        } elseif ($tipo === 'docente') {
            $datos['grado']  = $fila[8] ?? null;
            $datos['titulo'] = $fila[9] ?? null;
            $datos['cargo']  = $fila[10] ?? null;
        }

        return $datos;
    }

    /**
     * Reglas de una fila del archivo de importación.
     *
     * Vive aparte para que la previsualización y la importación real validen
     * exactamente lo mismo: si divergen, el administrador aprueba una
     * previsualización limpia y la importación falla igualmente.
     *
     * @return array<string, mixed>
     */
    private function reglasDeFila(string $tipo): array
    {
        $reglas = [
            'rut'       => ['required', 'string', 'max:20', new RutValido],
            'nombre1'   => 'required|string|max:100',
            'apellido1' => 'required|string|max:100',
            'username'  => ['required', 'string', 'max:30', Rule::unique(Usuario::class, 'username')],
            'password'  => ['required', 'string', Password::defaults()],
            'email'     => 'nullable|email|max:255',
        ];

        if ($tipo === 'estudiante') {
            $reglas['username'] = ['required', 'string', 'max:10', Rule::unique(Usuario::class, 'username')];
            $reglas['agno_ingreso'] = 'nullable|integer|min:1900|max:2100';
            $reglas['id_carrera'] = ['nullable', Rule::exists(Carrera::class, 'id_carrera')];
        } elseif ($tipo === 'docente') {
            $reglas['username'] = ['required', 'string', 'max:10', Rule::unique(Usuario::class, 'username')];
            $reglas['grado'] = 'nullable|string|max:100';
            $reglas['titulo'] = 'nullable|string|max:255';
        }

        return $reglas;
    }

    private function validarFila(array $datos, string $tipo, int $numeroFila)
    {
        $mensajes = [
            'required' => "Fila {$numeroFila}: El campo :attribute es obligatorio.",
            'unique'   => "Fila {$numeroFila}: El :attribute ya existe en el sistema.",
            'exists'   => "Fila {$numeroFila}: El ID de carrera no existe.",
        ];

        Validator::make($datos, $this->reglasDeFila($tipo), $mensajes)->validate();
    }

    // FIN BLOQUE DE INSERCIONES EN DB

    /**
     * Obtiene detalles de un usuario específico según su tipo.
     * 
     * Resuelve el usuario con relaciones asociadas (carrera para estudiantes, etc.).
     * Retorna JSON.
     *
     * Nota: $id es polimórfico según 'tipo' (id_estudiante / id_docente / id_usuario),
     * por eso no se usa route-model binding aquí.
     *
     * @param  Request  $request  Parámetro: tipo (estudiante|docente|administrador)
     * @param  int      $id       ID del estudiante/docente/usuario
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Determinar tipo de usuario
        $tipo = $request->input('tipo', 'estudiante');

        $this->authorize('view', $this->resolveUsuarioPorTipo($tipo, $id));

        // Recuperar usuario con sus relaciones según tipo
        if ($tipo === 'estudiante') {
            $usuario = Estudiante::with(['carrera', 'usuario'])->findOrFail($id);
        } elseif ($tipo === 'docente') {
            $usuario = Docente::with('usuario')->findOrFail($id);
        } else {
            $usuario = Usuario::findOrFail($id);
        }

        // Retornar JSON
        return response()->json($usuario);
    }

    /**
     * Dispatcher para actualizar usuario según tipo especificado.
     * 
     * Valida 'tipo' y delega a método específico:
     * - 'estudiante' → updateEstudiante()
     * - 'docente' → updateDocente()
     * - otro → updateAdministrador()
     * 
     * Nota: $id es polimórfico según 'tipo' (id_estudiante / id_docente / id_usuario),
     * por eso no se usa route-model binding aquí.
     *
     * @param  Request  $request  Parámetro: tipo, y datos a actualizar
     * @param  int      $id       ID del usuario a actualizar
     */
    public function update(Request $request, $id): RedirectResponse
    {
        // Determinar tipo de usuario y delegar al método correspondiente
        $tipo = $request->input('tipo');

        $this->authorize('update', $this->resolveUsuarioPorTipo((string) $tipo, $id));

        $this->normalizarRutDelRequest($request);

        if ($tipo === 'estudiante') {
            return $this->updateEstudiante($request, $id);
        } elseif ($tipo === 'docente') {
            return $this->updateDocente($request, $id);
        } else {
            return $this->updateAdministrador($request, $id);
        }
    }

    /**
     * Actualiza datos de usuario Estudiante de forma transaccional.
     * 
     * Modifica registros Usuario y Estudiante conjuntamente.
     * Permite actualizar información personal, carrera y año de ingreso.
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos actualizados: rut, nombres, carrera, agno_ingreso, email
     * @param  int      $id       ID del estudiante a actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    private function updateEstudiante(Request $request, $id)
    {
        // Recuperar estudiante y su relación de usuario
        $estudiante = Estudiante::findOrFail($id);
        $usuario = $estudiante->usuario;

        // Validar datos actualizados: datos personales y carrera
        $validated = $request->validate([
            'rut' => $this->reglasRutUnico($usuario),
            'nombre1' => 'required|string|max:100',
            'nombre2' => 'nullable|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'agno_ingreso' => 'nullable|integer|min:1900|max:2100',
            'id_carrera' => ['nullable', Rule::exists(Carrera::class, 'id_carrera')],
        ]);

        DB::beginTransaction();
        try {
            // Actualizar datos de usuario
            $usuario->update([
                'rut' => $validated['rut'],
                'nombre1' => $validated['nombre1'],
                'nombre2' => $validated['nombre2'],
                'apellido1' => $validated['apellido1'],
                'apellido2' => $validated['apellido2'],
                'email' => $validated['email'],
            ]);

            // Actualizar datos específicos del estudiante
            $estudiante->update([
                'agno_ingreso' => $validated['agno_ingreso'],
                'id_carrera' => $validated['id_carrera'],
            ]);

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'estudiante'])
                ->with('success', 'Estudiante actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar estudiante: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza datos de usuario Docente de forma transaccional.
     * 
     * Modifica registros Usuario y Docente conjuntamente.
     * Permite actualizar información personal, grado académico, cargo y título.
     * Rollback completo si alguna operación falla.
     * 
     * @param  Request  $request  Datos actualizados: rut, nombres, email, grado, cargo, titulo
     * @param  int      $id       ID del docente a actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    private function updateDocente(Request $request, $id)
    {
        // Recuperar docente y su relación de usuario
        $docente = Docente::findOrFail($id);
        $usuario = $docente->usuario;

        // Validar datos actualizados: datos personales y grado académico
        $validated = $request->validate([
            'rut' => $this->reglasRutUnico($usuario),
            'nombre1' => 'required|string|max:100',
            'nombre2' => 'nullable|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'grado' => 'nullable|string|max:100',
            'cargo' => 'nullable|string|max:100',
            'titulo' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Actualizar datos de usuario
            $usuario->update([
                'rut' => $validated['rut'],
                'nombre1' => $validated['nombre1'],
                'nombre2' => $validated['nombre2'],
                'apellido1' => $validated['apellido1'],
                'apellido2' => $validated['apellido2'],
                'email' => $validated['email'],
            ]);

            // Actualizar datos específicos del docente
            $docente->update([
                'grado' => $validated['grado'],
                'cargo' => $validated['cargo'],
                'titulo' => $validated['titulo'],
            ]);

            DB::commit();

            return redirect()->route('admin.usuarios.index', ['tipo' => 'docente'])
                ->with('success', 'Docente actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar docente: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza datos de usuario Administrador.
     * 
     * Modifica registro Usuario base con información personal y contacto.
     * 
     * @param  Request  $request  Datos actualizados: rut, nombres, email
     * @param  int      $id       ID del administrador a actualizar
     * @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado
     */
    private function updateAdministrador(Request $request, $id)
    {
        // Recuperar usuario administrador
        $usuario = Usuario::findOrFail($id);

        // Validar datos actualizados: datos personales
        $validated = $request->validate([
            'rut' => $this->reglasRutUnico($usuario),
            'nombre1' => 'required|string|max:255',
            'nombre2' => 'nullable|string|max:255',
            'apellido1' => 'required|string|max:255',
            'apellido2' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        // Actualizar datos del usuario
        $usuario->update($validated);

        return redirect()->route('admin.usuarios.index', ['tipo' => 'administrador'])
            ->with('success', 'Administrador actualizado exitosamente.');
    }

    /**
     * Elimina un usuario y su perfil asociado de forma transaccional.
     * 
     * Elimina perfil específico (Estudiante/Docente) primero, luego registro Usuario base.
     * Rollback completo si hay registros asociados que lo impidan (foreign keys).
     * 
     * Nota: $id es polimórfico según 'tipo' (id_estudiante / id_docente / id_usuario),
     * por eso no se usa route-model binding aquí.
     *
     * @param  Request  $request  Parámetro: tipo (estudiante|docente|administrador)
     * @param  int      $id       ID del estudiante/docente/usuario a eliminar
     */
    public function destroy(Request $request, $id): RedirectResponse
    {
        // Determinar tipo de usuario a eliminar
        $tipo = $request->input('tipo', 'estudiante');

        $this->authorize('delete', $this->resolveUsuarioPorTipo($tipo, $id));

        DB::beginTransaction();
        try {
            // Buscar registro específico según tipo y eliminar el perfil primero (si aplica)
            if ($tipo === 'estudiante') {
                $record = Estudiante::findOrFail($id);
                $usuarioId = $record->id_usuario;
                $record->delete();
            } elseif ($tipo === 'docente') {
                $record = Docente::findOrFail($id);
                $usuarioId = $record->id_usuario;
                $record->delete();
            } else {
                $record = Usuario::findOrFail($id);
                $usuarioId = $record->id_usuario;
            }

            // Eliminar registro base de usuario
            Usuario::find($usuarioId)?->delete();

            DB::commit();

            // Retornar con mensaje de éxito
            return redirect()->route('admin.usuarios.index', ['tipo' => $tipo])
                ->with('success', ucfirst($tipo) . ' eliminado exitosamente.');
        } catch (\Exception $e) {
            // Manejar error (registros asociados impiden eliminación)
            DB::rollBack();
            return redirect()->route('admin.usuarios.index', ['tipo' => $tipo])
                ->with('error', 'No se puede eliminar el ' . $tipo . ' porque tiene registros asociados.');
        }
    }

    /**
     * Actualiza la contraseña de un usuario.
     *
     * Cambiar la contraseña de una cuenta ajena es una toma de control: exige
     * permiso sobre el usuario, que el administrador confirme su **propia** clave,
     * y cierra las sesiones activas del afectado para que un atacante que ya
     * estuviera dentro no sobreviva al cambio.
     *
     * @param  Request  $request  Datos: current_password, password (confirmed)
     * @param  Usuario  $usuario  Usuario cuya contraseña actualizar (route-model binding)
     */
    public function changePassword(Request $request, Usuario $usuario): RedirectResponse
    {
        /** @var Usuario|null $actor */
        $actor = Auth::user();

        if (!$actor) {
            abort(401, 'Debes iniciar sesión.');
        }

        $this->authorize('update', $usuario);

        // Sin esto, cualquiera que superara IsAdmin se apropiaba de un SuperAdmin.
        if (!$actor->isSuperAdmin() && $usuario->isSuperAdmin()) {
            $this->logIntentoEscalada($actor, $usuario, 'CAMBIO_PASSWORD_SUPERADMIN');
            abort(403, 'Solo un SuperAdmin puede cambiar la contraseña de otro SuperAdmin.');
        }

        $validated = $request->validate([
            // 'current_password' valida contra la clave del usuario autenticado,
            // no contra la del objetivo: es la reautenticación del administrador.
            'current_password' => ['required', 'string', 'current_password'],
            'password'         => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => 'La contraseña de tu propia cuenta no es correcta.',
        ]);

        DB::transaction(function () use ($usuario, $validated) {
            $usuario->update([
                'passhash' => Hash::make($validated['password']),
                // Invalida las cookies "recuérdame" emitidas antes del cambio.
                'token_recuerdame_sesion' => null,
            ]);

            // Cierra las sesiones abiertas del afectado. Sólo aplicable con el
            // driver de sesión en base de datos, que es el configurado.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $usuario->id_usuario)
                    ->delete();
            }
        });

        Log::channel('seguridad')->info('Contraseña cambiada por un administrador', [
            'evento'            => 'CAMBIO_PASSWORD_ADMINISTRATIVO',
            'id_usuario_actor'  => $actor->id_usuario,
            'id_usuario_target' => $usuario->id_usuario,
            'ip'                => $request->ip(),
        ]);

        return back()->with('success', 'Contraseña actualizada. Se cerraron las sesiones activas del usuario.');
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     * 
     * Cambia 'esta_activo' entre true y false. Usuario inactivo no puede autenticarse.
     *
     * @param  Usuario  $usuario  Usuario a activar/desactivar (route-model binding)
     */
    public function toggleActive(Usuario $usuario): RedirectResponse
    {
        $this->authorize('update', $usuario);

        // Alternar su estado activo/inactivo
        $usuario->esta_activo = !(bool) $usuario->esta_activo;
        $usuario->save();

        // Retornar mensaje con el nuevo estado
        $status = $usuario->esta_activo ? 'activado' : 'desactivado';
        return back()->with('success', "Usuario {$status} exitosamente.");
    }

    /**
     * BLOQUE DE FUNCIONES AUXILIARES
     * 
     */
    public function buscarPorRut(Request $request)
    {
        $this->authorize('viewAny', Usuario::class);

        // El buscador recibe lo que el administrador teclea; la columna guarda el
        // formato canónico, así que se compara normalizado.
        $rut = Rut::normalizar($request->query('rut'));
        $usuario = Usuario::where('rut', $rut)->first();

        if (!$usuario) {
            return response()->json(null);
        }

        return response()->json($usuario);
    }

    /**
     * Obtiene las asignaciones actuales de roles y permisos especiales de un usuario.
     * 
     * Retorna SOLO las asignaciones activas del usuario en todos los contextos.
     * Los roles y permisos disponibles para asignar se obtienen desde otros endpoints.
     * 
     * @param  Usuario  $usuario  El usuario cuyas asignaciones obtener
     * @return \Illuminate\Http\JsonResponse  JSON con roles y permisos asignados actualmente
     */
    public function getUserPermissions(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        // Obtener TODAS las asignaciones de rol activas (todos los contextos)
        $roles = UsuarioRolAsignacion::with(['rol', 'contexto.curso', 'asignador'])
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereNull('fecha_fin_real')
            ->where('fecha_fin_planificada', '>=', now())
            ->get()
            // @formatter:off
            ->map(fn($ura) => [
                'id_ura'                   => $ura->id_ura,
                'id_rol'                   => $ura->id_rol,
                'nombre'                   => $ura->rol?->nombre,
                'id_contexto'              => $ura->id_contexto,
                'contexto_display'         => $ura->contexto?->contexto_display,
                'curso_nombre'             => $ura->contexto?->curso?->nombre,
                'fecha_inicio_planificada' => $ura->fecha_inicio_planificada,
                'fecha_fin_planificada'    => $ura->fecha_fin_planificada,
                'creado_por_nombre'        => trim(($ura->asignador?->nombre1 ?? '') . ' ' . ($ura->asignador?->apellido1 ?? '')),
            ])
            ->values()
            ->toArray();

        // Obtener todos los permisos especiales activos del usuario (todos los contextos)
        $specialPermissions = UsuarioPermisoEspecial::with(['permiso', 'contexto.curso'])
            ->where('id_usuario', $usuario->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->whereNull('fecha_fin_real')
            ->where('fecha_fin_planificada', '>=', now())
            ->get()
            // @formatter:off
            ->map(fn($upe) => [
                'id_upe'           => $upe->id_upe,
                'id_permiso'       => $upe->id_permiso,
                'slug'             => $upe->permiso?->slug,
                'nombre'           => $upe->permiso?->nombre,
                'id_contexto'      => $upe->id_contexto,
                'contexto_display' => $upe->contexto?->contexto_display,
                'curso_nombre'     => $upe->contexto?->curso?->nombre,
                'esta_permitido'   => (bool) $upe->esta_permitido,
                'puede_delegar'    => (bool) $upe->puede_delegar,
            ])
            ->values()
            ->toArray();

        // Retornar JSON con datos actuales y disponibles
        return response()->json([
            'roles' => $roles,
            'special_permissions' => $specialPermissions,
        ]);
    }

    /**
     * Sincroniza (actualiza) todos los roles y permisos especiales de un usuario.
     * 
     * Reemplaza asignaciones actuales con las nuevas. Implementa RBAC validado.
     * Registra cambios para auditoría. Transaccional.
     * 
     * @param  Request  $request  Datos: roles (array de ids), special_permissions (array de id_permiso => bool)
     * @param  Usuario  $usuario  El usuario cuyo permisos sincronizar
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse  JSON o redirección
     */
    public function syncPermissions(Request $request, Usuario $usuario, DelegationAuthorizer $delegationAuthorizer)
    {
        // Validar roles e permisos especiales desde el request
        $validated = $request->validate([
            'roles' => 'array',
            'special_permissions' => 'array' // { id_permiso: true/false/null }
        ]);

        // Ambas claves son opcionales: normalizarlas aquí evita el 500 que provocaba
        // recorrerlas cuando el cliente las omitía (D-9).
        $validated['roles'] ??= [];
        $validated['special_permissions'] ??= [];

        // Obtener contexto global
        // TODO: Ampliar para soportar sincronización multi-contexto
        $idContexto = app(GlobalContextService::class)->getContextId();

        $this->assertPuedeSincronizarPermisos($usuario, $validated['roles'], $idContexto, $delegationAuthorizer);

        // Obtener admin que realiza la acción (para auditoría)
        $adminId = Auth::id() ?? 1; // Fallback only for dev/seeder

        DB::beginTransaction();
        try {
            // ===============================================
            // 1. SINCRONIZAR ROLES: Reemplazar con los nuevos
            // ===============================================
            // Desactivar asignaciones actuales
            UsuarioRolAsignacion::where('id_usuario', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->update([
                    'esta_activo' => false,
                    'fue_eliminado' => true,
                    'fecha_fin_real' => now()
                ]);

            // Crear nuevas asignaciones de roles
            if (!empty($validated['roles'])) {
                foreach ($validated['roles'] as $rolId) {
                    UsuarioRolAsignacion::updateOrCreate(
                        [
                            'id_usuario' => $usuario->id_usuario,
                            'id_contexto' => $idContexto,
                            'id_rol' => $rolId,
                        ],
                        [
                            'asignado_por' => (int) $adminId,
                            'creado_por' => (int) $adminId,
                            'fecha_inicio_planificada' => now(),
                            'fecha_fin_planificada' => now()->addDays(365),
                            'esta_activo' => true,
                            'fue_eliminado' => false,
                            'fecha_fin_real' => null,
                            'fecha_creacion' => now(),
                        ]
                    );
                }
            }

            // ==========================================================
            // 2. SINCRONIZAR PERMISOS ESPECIALES: Reemplazar con nuevos
            // ==========================================================
            // Desactivar permisos especiales actuales
            UsuarioPermisoEspecial::where('id_usuario', $usuario->id_usuario)
                ->where('id_contexto', $idContexto)
                ->where('esta_activo', true)
                ->where('fue_borrado', false)
                ->update([
                    'esta_activo' => false,
                    'fue_borrado' => true,
                    'fecha_fin_real' => now()
                ]);

            // Crear nuevas asignaciones de permisos especiales
            foreach ($validated['special_permissions'] as $permId => $status) {
                $isObject = is_array($status);
                $allowed = $isObject ? ($status['allowed'] ?? null) : $status;
                $canDelegate = $isObject ? ($status['can_delegate'] ?? false) : false;
                $duracionDias = $isObject ? ($status['duration_days'] ?? 365) : 365;

                Log::debug('Processing permiso:', [
                    'perm_id' => $permId,
                    'is_object' => $isObject,
                    'allowed' => $allowed,
                    'can_delegate' => $canDelegate,
                    'duration_days' => $duracionDias,
                    'will_save' => ($allowed !== null || $canDelegate)
                ]);

                if ($allowed !== null || $canDelegate) {
                    // Guardar o actualizar permiso especial con su configuración
                    $upe = UsuarioPermisoEspecial::updateOrCreate(
                        [
                            'id_usuario' => $usuario->id_usuario,
                            'id_contexto' => $idContexto,
                            'id_permiso' => $permId,
                        ],
                        [
                            'creado_por' => $adminId,
                            'esta_permitido' => ($allowed === null) ? null : (bool) $allowed,
                            'puede_delegar' => (bool) $canDelegate,
                            'esta_activo' => true,
                            'fue_borrado' => false,
                            'fecha_fin_real' => null,
                            'fecha_fin_planificada' => now()->addDays($duracionDias),
                            'fecha_creacion' => now()
                        ]
                    );

                    Log::debug('✅ Permiso guardado:', [
                        'id_upe' => $upe->id_upe,
                        'perm_id' => $permId,
                        'puede_delegar_saved' => $upe->puede_delegar,
                        'duration_days' => $duracionDias
                    ]);
                }
            }

            DB::commit();

            // Las desactivaciones de arriba son `where(...)->update(...)`: no
            // disparan eventos de modelo, así que la caché de permisos hay que
            // invalidarla explícitamente.
            app(PermissionCache::class)->olvidarUsuario((int) $usuario->id_usuario);

            // Retornar respuesta según formato solicitado
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Permisos actualizados correctamente.'
                ]);
            }

            return back()->with('success', 'Permisos actualizados correctamente.');

        } catch (\Exception $e) {
            // Deshacer cambios si hay error
            DB::rollBack();
            Log::error("SyncPermissions Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $validated
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar permisos: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Error al actualizar permisos: ' . $e->getMessage());
        }
    }

    /**
     * Guard de la sincronización de roles y permisos especiales.
     *
     * Este endpoint escribe directo sobre usuario_rol_asignacion y
     * usuario_permiso_especial, esquivando RoleAssignmentBuilder y su
     * validateActorAuthorization(). Sin este guard era la vía abierta para
     * concederse a uno mismo el rol SuperAdmin.
     *
     * @param  Usuario               $objetivo             Usuario cuyos permisos se sincronizan
     * @param  array                 $rolesSolicitados     IDs de rol que el request pretende asignar
     * @param  int                   $idContexto           Contexto de destino de la sincronización
     * @param  DelegationAuthorizer  $delegationAuthorizer Regla compartida "¿puede delegar esto, aquí?"
     */
    private function assertPuedeSincronizarPermisos(
        Usuario $objetivo,
        array $rolesSolicitados,
        int $idContexto,
        DelegationAuthorizer $delegationAuthorizer
    ): void {
        /** @var Usuario|null $actor */
        $actor = Auth::user();

        if (!$actor) {
            abort(401, 'Debes iniciar sesión.');
        }

        // 1. Nadie edita sus propios roles ni permisos: cierra la auto-escalada.
        if ($actor->id_usuario === $objetivo->id_usuario) {
            $this->logIntentoEscalada($actor, $objetivo, 'AUTO_ASIGNACION_PERMISOS');
            abort(403, 'No puedes modificar tus propios roles ni permisos.');
        }

        // 2. Permiso explícito para crear asignaciones de rol y permisos especiales.
        $this->authorize('create', UsuarioRolAsignacion::class);
        $this->authorize('create', UsuarioPermisoEspecial::class);

        $actorEsSuperAdmin = $actor->isSuperAdmin();

        // 3. Sólo un SuperAdmin puede tocar los permisos de otro SuperAdmin.
        if (!$actorEsSuperAdmin && $objetivo->isSuperAdmin()) {
            $this->logIntentoEscalada($actor, $objetivo, 'MODIFICACION_SUPERADMIN');
            abort(403, 'Solo un SuperAdmin puede modificar los permisos de otro SuperAdmin.');
        }

        // 4. Sólo puede conceder roles cuyos permisos reales pueda delegar en el contexto
        //    de destino — misma regla que usa RoleAssignmentBuilder, no una lista de alias
        //    por nombre (ver H-2: un rol nuevo y potente que no esté en la lista de alias
        //    administrativos podía concederse sin ninguna validación de contenido).
        if (!$actorEsSuperAdmin && !empty($rolesSolicitados)) {
            $permisosDeLosRoles = $this->permisosOtorgadosPorRoles($rolesSolicitados);

            if (!$delegationAuthorizer->actorPuedeDelegar($actor, $permisosDeLosRoles, [$idContexto])) {
                $this->logIntentoEscalada($actor, $objetivo, 'CONCESION_ROL_NO_DELEGABLE', [
                    'roles_solicitados' => $rolesSolicitados,
                ]);
                abort(403, 'No tienes permiso para delegar uno o más de los permisos que otorgan los roles solicitados.');
            }
        }
    }

    /**
     * Resuelve los permisos reales (como enums) que otorgan un conjunto de roles.
     *
     * @param  int[]  $roleIds
     * @return Permissions[]
     */
    private function permisosOtorgadosPorRoles(array $roleIds): array
    {
        return DB::table('usuario.asignacion_rol_permiso')
            ->join('usuario.permiso', 'usuario.asignacion_rol_permiso.id_permiso', '=', 'usuario.permiso.id_permiso')
            ->whereIn('usuario.asignacion_rol_permiso.id_rol', $roleIds)
            ->pluck('usuario.permiso.slug')
            ->unique()
            ->map(Permissions::from(...))
            ->all();
    }

    /**
     * Deja rastro en el canal `seguridad` de un intento de escalada de privilegios.
     */
    private function logIntentoEscalada(Usuario $actor, Usuario $objetivo, string $evento, array $extra = []): void
    {
        Log::channel('seguridad')->warning('Intento de escalada de privilegios en syncPermissions', [
            'evento'            => $evento,
            'id_usuario_actor'  => $actor->id_usuario,
            'id_usuario_target' => $objetivo->id_usuario,
            'ip'                => request()->ip(),
            ...$extra,
        ]);
    }

    /**
     * Resuelve el `Usuario` base a partir del `$id` de la ruta y el `tipo`.
     *
     * `show`/`update`/`destroy` reciben el ID del **perfil** (estudiante o
     * docente), no el del usuario, así que para autorizar contra `UsuarioPolicy`
     * hay que traducirlo primero.
     *
     * `id_estudiante`, `id_docente` e `id_usuario` son secuencias independientes
     * de tablas distintas: un `tipo` no reconocido (vacío, typo, manipulado) NO
     * puede caer en "administrador" por defecto, porque entonces `$id` se
     * reinterpreta silenciosamente como `id_usuario` de una persona sin ninguna
     * relación con el estudiante/docente que la UI mostraba.
     */
    private function resolveUsuarioPorTipo(string $tipo, $id): Usuario
    {
        return match ($tipo) {
            'estudiante'    => Estudiante::findOrFail($id)->usuario,
            'docente'       => Docente::findOrFail($id)->usuario,
            'administrador' => Usuario::findOrFail($id),
            default         => abort(422, "Tipo de usuario inválido: {$tipo}"),
        };
    }

    /**
     * Asignar rol a un usuario en contexto Global.
     */
    private function assignRole(Usuario $usuario, string $roleName)
    {
        // Buscar rol por nombre
        $rol = Rol::where('nombre', $roleName)->first();
        if (!$rol) {
            Log::warning("Role '$roleName' not found for automatic assignment.");
            return;
        }

        // Obtener contexto global y asignar rol por 365 días
        $globalContextId = app(GlobalContextService::class)->getContextId();

        $usuario->giveRole($rol)
            ->inContext($globalContextId)
            ->for(365)
            ->save();
    }
}
