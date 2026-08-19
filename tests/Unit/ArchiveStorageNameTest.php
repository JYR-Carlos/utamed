<?php

use App\Services\Archive\AbstractArchiveService;
use Illuminate\Http\UploadedFile;

/**
 * Servicio mínimo para ejercitar los helpers de nombre/extensión de
 * AbstractArchiveService sin tocar disco ni base de datos.
 */
function servicioDeArchivos(): object
{
    return new class extends AbstractArchiveService {
        protected function preValidate(UploadedFile $file, string $archiveId): void {}

        protected function compressFile(UploadedFile $file, string $archiveId): UploadedFile
        {
            return $file;
        }

        public function nombreEnDisco(UploadedFile $file, ?string $propuesto): string
        {
            return $this->buildSafeStorageName($file, $propuesto);
        }

        public function extension(UploadedFile $file): string
        {
            return $this->resolveSafeExtension($file);
        }
    };
}

test('la extensión sale del contenido, no del nombre que manda el cliente', function () {
    // Un PDF renombrado a .php: antes se guardaba tal cual como shell.php.
    $file = UploadedFile::fake()->create('shell.php', 10, 'application/pdf');

    expect(servicioDeArchivos()->nombreEnDisco($file, 'shell.php'))->toBe('shell.pdf');
});

test('el nombre propuesto sólo aporta la parte base', function () {
    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');

    expect(servicioDeArchivos()->nombreEnDisco($file, 'informe_final.docx'))->toBe('informe_final.pdf');
});

test('se descarta cualquier componente de ruta del nombre propuesto', function () {
    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');
    $servicio = servicioDeArchivos();

    expect($servicio->nombreEnDisco($file, '../../etc/passwd'))->toBe('passwd.pdf');
    expect($servicio->nombreEnDisco($file, 'sub/dir/informe'))->toBe('informe.pdf');
});

test('un nombre que se queda vacío tras el saneado cae en un aleatorio, no en punto-extensión', function () {
    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');

    $nombre = servicioDeArchivos()->nombreEnDisco($file, '..');

    expect($nombre)->toEndWith('.pdf');
    expect($nombre)->not->toStartWith('.');
    expect(strlen($nombre))->toBeGreaterThan(4);
});

test('los caracteres fuera del allowlist se reemplazan', function () {
    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');

    expect(servicioDeArchivos()->nombreEnDisco($file, 'informe;rm -rf'))->toBe('informe_rm_-rf.pdf');
});

test('la parte base se recorta a 100 caracteres', function () {
    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');

    $nombre = servicioDeArchivos()->nombreEnDisco($file, str_repeat('a', 300));

    expect($nombre)->toBe(str_repeat('a', 100) . '.pdf');
});

test('sin nombre propuesto se usa un aleatorio con la extensión derivada', function () {
    $file = UploadedFile::fake()->create('entrega.png', 10, 'image/png');

    expect(servicioDeArchivos()->nombreEnDisco($file, null))->toEndWith('.png');
});

test('un MIME que no mapea a ninguna extensión cae en bin', function () {
    $file = UploadedFile::fake()->create('cosa.raro', 10, 'application/x-inventado');

    expect(servicioDeArchivos()->extension($file))->toBe('bin');
});
