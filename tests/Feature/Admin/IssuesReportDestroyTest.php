<?php

use App\Http\Controllers\IssuesReportController;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Services\Authorization\GlobalContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(DatabaseTransactions::class);

function getAdminParaReportes(): Usuario
{
    $sufijo = random_int(1000000, 9999999);
    $admin = Usuario::create([
        'username' => "admin_rep_{$sufijo}",
        'passhash' => Hash::make('secret123'),
        'email' => "admin_rep_{$sufijo}@example.test",
        'rut' => "{$sufijo}-1",
        'nombre1' => 'Admin',
        'apellido1' => 'Reportes',
        'esta_activo' => true,
        'fecha_verificacion_email' => now(),
        'fecha_cambio_passhash' => now(),
    ]);

    $permisoWildcard = Permiso::firstOrCreate(['slug' => '*'], ['nombre' => 'Wildcard']);
    $contextoGlobal = app(GlobalContextService::class)->getContextId();

    UsuarioPermisoEspecial::create([
        'id_usuario' => $admin->id_usuario,
        'id_permiso' => $permisoWildcard->id_permiso,
        'id_contexto' => $contextoGlobal,
        'esta_permitido' => true,
        'puede_delegar' => true,
        'fecha_fin_planificada' => now()->addYears(10),
        'creado_por' => $admin->id_usuario,
        'esta_activo' => true,
    ]);

    return $admin;
}

test('destruye correctamente pdf, metadatos y evidencias de un reporte existente', function () {
    Storage::fake('local');

    $reportId = '2026-10-15 15:30:00_12345678-9';
    $pdfPath = "reports/{$reportId}_report.pdf";
    $metaPath = "reports/metadata/{$reportId}_metadata.json";
    $evidencePath = "reports/evidences/{$reportId}_evidence.png";

    Storage::disk('local')->put($pdfPath, 'PDF CONTENT');
    Storage::disk('local')->put($metaPath, json_encode(['title' => 'Test bug']));
    Storage::disk('local')->put($evidencePath, 'IMAGE CONTENT');

    expect(Storage::disk('local')->exists($pdfPath))->toBeTrue();
    expect(Storage::disk('local')->exists($metaPath))->toBeTrue();
    expect(Storage::disk('local')->exists($evidencePath))->toBeTrue();

    $controller = new IssuesReportController();
    $response = $controller->destroy($reportId);

    expect(Storage::disk('local')->exists($pdfPath))->toBeFalse();
    expect(Storage::disk('local')->exists($metaPath))->toBeFalse();
    expect(Storage::disk('local')->exists($evidencePath))->toBeFalse();
    expect(session('success'))->toBe('Reporte eliminado con éxito');
});

test('destruye reporte cuando el id incluye el sufijo _report.pdf', function () {
    Storage::fake('local');

    $baseId = '2026-10-15 15:30:00_87654321-0';
    $reportIdWithSuffix = "{$baseId}_report.pdf";
    $pdfPath = "reports/{$baseId}_report.pdf";
    $metaPath = "reports/metadata/{$baseId}_metadata.json";
    $evidencePath = "reports/evidences/{$baseId}_screenshot.jpg";

    Storage::disk('local')->put($pdfPath, 'PDF CONTENT');
    Storage::disk('local')->put($metaPath, json_encode(['title' => 'Test bug with suffix']));
    Storage::disk('local')->put($evidencePath, 'IMAGE CONTENT');

    $controller = new IssuesReportController();
    $controller->destroy($reportIdWithSuffix);

    expect(Storage::disk('local')->exists($pdfPath))->toBeFalse();
    expect(Storage::disk('local')->exists($metaPath))->toBeFalse();
    expect(Storage::disk('local')->exists($evidencePath))->toBeFalse();
});

test('aborta con 404 si el reporte no existe', function () {
    Storage::fake('local');

    $controller = new IssuesReportController();
    $controller->destroy('id_que_no_existe');
})->throws(NotFoundHttpException::class);

test('previene directory traversal', function (string $maliciousId) {
    Storage::fake('local');

    $controller = new IssuesReportController();
    $controller->destroy($maliciousId);
})->with([
    '../',
    '../../etc/passwd',
    '.',
    '..',
    '/',
    '',
])->throws(NotFoundHttpException::class);

test('ruta DELETE admin/reportes/{id} funciona con usuario admin autenticado', function () {
    Storage::fake('local');

    $admin = getAdminParaReportes();

    $reportId = '2026-10-15 16:00:00_99999999-9';
    $pdfPath = "reports/{$reportId}_report.pdf";
    $metaPath = "reports/metadata/{$reportId}_metadata.json";
    $evidencePath = "reports/evidences/{$reportId}_shot.png";

    Storage::disk('local')->put($pdfPath, 'PDF DUMMY');
    Storage::disk('local')->put($metaPath, json_encode(['title' => 'HTTP Delete Test']));
    Storage::disk('local')->put($evidencePath, 'EVIDENCE DUMMY');

    $response = $this->actingAs($admin)
        ->from(route('admin.reports.index'))
        ->delete(route('admin.reports.destroy', ['id' => $reportId]));

    $response->assertRedirect(route('admin.reports.index'));
    $response->assertSessionHas('success', 'Reporte eliminado con éxito');

    expect(Storage::disk('local')->exists($pdfPath))->toBeFalse();
    expect(Storage::disk('local')->exists($metaPath))->toBeFalse();
    expect(Storage::disk('local')->exists($evidencePath))->toBeFalse();
});
