<?php

use App\Exceptions\Archive\ArchiveException;
use App\Services\Archive\AbstractArchiveService;
use Illuminate\Http\UploadedFile;

function servicioConEscaneo(): object
{
    return new class extends AbstractArchiveService {
        protected function preValidate(UploadedFile $file, string $archiveId): void {}

        protected function compressFile(UploadedFile $file, string $archiveId): UploadedFile
        {
            return $file;
        }

        public function escanear(UploadedFile $file): void
        {
            $this->scanForViruses($file, 'test-archive-id');
        }
    };
}

test('con el escaneo desactivado el hook no interrumpe la subida', function () {
    config()->set('files.validation.virus_scan_enabled', false);

    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');

    servicioConEscaneo()->escanear($file);
})->throwsNoExceptions();

test('con el escaneo activado y sin escáner implementado la subida se rechaza', function () {
    // El cuerpo estaba vacío: activar el flag aprobaba todos los archivos en
    // silencio. Ahora falla cerrado y el error dice qué hacer.
    config()->set('files.validation.virus_scan_enabled', true);

    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');

    expect(fn() => servicioConEscaneo()->escanear($file))
        ->toThrow(ArchiveException::class);
});

test('el error de escaneo se marca como CONFIGURATION_ERROR y arrastra el archive id', function () {
    config()->set('files.validation.virus_scan_enabled', true);

    $file = UploadedFile::fake()->create('entrega.pdf', 10, 'application/pdf');

    try {
        servicioConEscaneo()->escanear($file);
        $this->fail('Se esperaba una ArchiveException.');
    } catch (ArchiveException $e) {
        expect($e->getType())->toBe('CONFIGURATION_ERROR');
        expect($e->getArchiveId())->toBe('test-archive-id');
    }
});
