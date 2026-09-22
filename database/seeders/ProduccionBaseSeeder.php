<?php

namespace Database\Seeders;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Siembra de arranque para una base REAL de UTAMED: lo mínimo indispensable para
 * que el sistema funcione, sin un solo dato de prueba.
 *
 * Lo que hace: cargar la estructura académica real (facultades, departamentos,
 * carreras, planes, asignaturas y sus asignaciones al plan) y verificar que el
 * superadmin que trae el modelo de datos quedó utilizable.
 *
 * Lo que NO hace, y es importante entender por qué:
 *
 *  - **No crea roles, permisos, contextos ni el superadmin.** Todo eso ya viene
 *    en el baseline del submódulo `database-model` (`init_scripts/03-inserts/`):
 *    el usuario `superadmin` con RUT 00000000-0, los 15 roles del RBAC (Docente
 *    Titular, Docente Componente, Jefe de Carrera, Director de Departamento,
 *    Ayudante, SuperAdmin, Estudiante…), el catálogo completo de permisos, los
 *    contextos y la asignación del rol y del permiso «*» al superadmin.
 *    Por eso NO se llama a RoleAndPermissionSeeder: ese seeder es anterior al
 *    baseline y lo duplica —crearía un segundo contexto «Global», un segundo
 *    usuario administrador (`system_admin`) y roles «Super Admin»/«Estudiante»
 *    paralelos a los que ya existen—.
 *
 *  - **No crea usuarios de prueba ni cursos de demo.** Es decir, no llama a
 *    EquipoDesarrolloSeeder (cuentas con RUT de relleno 22222222-2 … 70770800-K,
 *    el último con es_superadmin), UsuariosDePruebaSeeder, BaseCursosSeeder ni
 *    ActividadesSeeder.
 *
 *  - **No crea cursos.** Los cursos los crea el wizard del admin junto con la
 *    sincronización de Intranet; su contenido lo cargan los seeders por
 *    asignatura (Dm069ContenidoSeeder, Dm095ContenidoSeeder).
 *
 * Es la contraparte de DatabaseSeeder, que sigue existiendo para desarrollo
 * local y sí encadena la data de demo. En una base real se corre ESTE:
 *
 *   php artisan db:seed --class=Database\\Seeders\\ProduccionBaseSeeder
 *
 * Es idempotente: todo lo de adentro es firstOrCreate.
 *
 * Superadmin: usuario `superadmin`, RUT **00000000-0** (se entra con el RUT, no
 * con el correo), clave inicial **superadmin**, definida en el baseline.
 *  - UTAMED_SUPERADMIN_PASSWORD la reemplaza y deja `fecha_cambio_passhash` en
 *    NULL, con lo que el primer login obliga a cambiarla.
 *  - Sólo se aplica mientras la clave siga siendo la inicial: si el superadmin ya
 *    la cambió, este seeder no la toca.
 */
class ProduccionBaseSeeder extends Seeder
{
    /** Los trae el baseline de database-model, no este seeder. */
    private const RUT_SUPERADMIN = '00000000-0';

    private const PASSWORD_INICIAL = 'superadmin';

    /** RUT de relleno de EquipoDesarrolloSeeder: si aparecen, se corrió el seeder equivocado. */
    private const RUT_EQUIPO_DESARROLLO = ['22222222-2', '33333333-3', '44444444-4', '55555555-5', '70770800-K'];

    public function run(): void
    {
        $this->command->info('== ProduccionBaseSeeder: estructura académica real, sin datos de prueba ==');

        // Estructura académica. Cada uno crea o encuentra su facultad,
        // departamento, carrera, plan y catálogo de asignaturas.
        $this->call(CarreraDisenioMultimediaSeeder::class);
        $this->call(CarreraIngenieriaComercialSeeder::class);

        $this->revisarSuperadmin();
        $this->resumen();
        $this->avisarDatosDePrueba();
    }

    private function revisarSuperadmin(): void
    {
        $this->command->info("\n-- Superadmin --");

        $admin = Usuario::where('rut', self::RUT_SUPERADMIN)->first();
        if (!$admin) {
            $this->command->error('   ! No existe el usuario con RUT ' . self::RUT_SUPERADMIN . '.');
            $this->command->error('     Lo crea el baseline de database-model (init_scripts/03-inserts/05-usuarios-base.sql).');
            $this->command->error('     Si falta, la base no se levantó desde el baseline: revisar antes de seguir.');

            return;
        }

        $this->command->line("   usuario «{$admin->username}» (id {$admin->id_usuario}), RUT {$admin->rut}, {$admin->email}.");

        $password = env('UTAMED_SUPERADMIN_PASSWORD');

        if (!Hash::check(self::PASSWORD_INICIAL, $admin->passhash)) {
            $this->command->line('   contraseña: ya no es la inicial; no se toca.');

            return;
        }

        if ($password) {
            $admin->forceFill([
                'passhash' => Hash::make($password),
                // A NULL a propósito: aunque la clave del entorno sea buena, el
                // primer login obliga a cambiarla.
                'fecha_cambio_passhash' => null,
            ])->save();
            $this->command->line('   contraseña: tomada de UTAMED_SUPERADMIN_PASSWORD. El primer login pedirá cambiarla.');

            return;
        }

        $this->command->warn('   ! contraseña: sigue siendo «' . self::PASSWORD_INICIAL . '», la del baseline.');
        $this->command->warn('     Sirve para local. En una base real defina UTAMED_SUPERADMIN_PASSWORD antes de correr esto, o cámbiela de inmediato.');
        $this->command->warn('     Se entra con el RUT ' . self::RUT_SUPERADMIN . ', no con el correo.');
    }

    private function resumen(): void
    {
        $this->command->info("\n-- Resumen de lo que quedó --");

        foreach ([
            'facultades'           => Facultad::count(),
            'departamentos'        => Departamento::count(),
            'carreras'             => Carrera::count(),
            'planes'               => Plan::count(),
            'asignaturas'          => Asignatura::count(),
            'asignaciones al plan' => AsignacionPlan::count(),
            'roles'                => Rol::count(),
            'permisos'             => Permiso::count(),
            'usuarios'             => Usuario::count(),
            'cursos'               => Curso::withTrashed()->count(),
        ] as $que => $cuantos) {
            $this->command->line(sprintf('   %-22s %d', $que, $cuantos));
        }
    }

    /**
     * No borra nada: sólo avisa. Si esta base ya pasó por DatabaseSeeder, aquí
     * salen las cuentas y los cursos de demo que habría que revisar.
     */
    private function avisarDatosDePrueba(): void
    {
        $avisos = [];

        foreach (Usuario::whereIn('rut', self::RUT_EQUIPO_DESARROLLO)->get() as $u) {
            $avisos[] = "cuenta del equipo de desarrollo: {$u->rut} ({$u->username}, id {$u->id_usuario})";
        }

        if ($sysadmin = Usuario::where('username', 'system_admin')->first()) {
            $avisos[] = "usuario «system_admin» (id {$sysadmin->id_usuario}): lo crea RoleAndPermissionSeeder y duplica al superadmin del baseline";
        }

        $plantillas = Curso::withTrashed()->where('es_plantilla', true)->count();
        if ($plantillas > 0) {
            $avisos[] = "{$plantillas} curso(s) plantilla de BaseCursosSeeder";
        }

        $otros = Usuario::where('rut', '!=', self::RUT_SUPERADMIN)->count();
        if ($otros > 0) {
            $avisos[] = "{$otros} usuario(s) además del superadmin (pueden ser legítimos: docentes y alumnos que trajo Intranet)";
        }

        if (!$avisos) {
            $this->command->info("\nLa base no tiene datos de prueba conocidos: sólo el superadmin del baseline.");

            return;
        }

        $this->command->warn("\nOJO, hay cosas que este seeder NO creó y que conviene revisar:");
        foreach ($avisos as $a) {
            $this->command->warn("  - {$a}");
        }
        $this->command->warn('  Este seeder no borra nada.');
    }
}
