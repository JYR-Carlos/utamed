<?php

use App\Services\Archive\FiletypeValidation\FileRequirementBuilder;
use App\Services\Archive\FiletypeValidation\FileRequirementType;
use Illuminate\Http\UploadedFile;

/**
 * Ejecuta el closure de tamaño de buildLaravelRules() y devuelve el mensaje de
 * error, o null si el archivo pasa.
 */
function validarTamano(FileRequirementBuilder $builder, UploadedFile $file): ?string
{
    $closure = collect($builder->buildLaravelRules())
        ->first(fn($rule) => $rule instanceof Closure);

    $error = null;
    $closure('archivo', $file, function (string $mensaje) use (&$error) {
        $error = $mensaje;
    });

    return $error;
}

beforeEach(function () {
    config()->set('filetypes.global.max_file_size', 52428800);

    config()->set('filetypes.pdf', [
        'extensions' => ['pdf'],
        'mimes'      => ['application/pdf'],
        'max_size'   => 1048576, // 1 MB
    ]);

    config()->set('filetypes.media', [
        'extensions' => ['mp4'],
        'mimes'      => ['video/mp4'],
        'max_size'   => 104857600, // 100 MB
    ]);

    $this->builder = FileRequirementBuilder::make()
        ->addConfig(FileRequirementType::PDF)
        ->addConfig(FileRequirementType::MEDIA);
});

test('un archivo dentro del límite de su categoría pasa', function () {
    $file = UploadedFile::fake()->create('apunte.pdf', 500, 'application/pdf');

    expect(validarTamano($this->builder, $file))->toBeNull();
});

test('un archivo que excede el límite de su categoría falla', function () {
    $file = UploadedFile::fake()->create('apunte.pdf', 2048, 'application/pdf'); // 2 MB

    expect(validarTamano($this->builder, $file))->toContain('no pueden superar');
});

test('cruzar extensión de una categoría con MIME de otra aplica el límite más restrictivo', function () {
    // Extensión .mp4 (tope 100 MB) con contenido PDF (tope 1 MB): antes esta
    // combinación no casaba con ninguna categoría y salía del closure sin
    // comprobar el tamaño, quedando sólo bajo upload_max_filesize.
    $file = UploadedFile::fake()->create('payload.mp4', 5120, 'application/pdf'); // 5 MB

    expect(validarTamano($this->builder, $file))->toContain('no pueden superar');
});

test('sin coincidencia de extensión ni de MIME el archivo se rechaza', function () {
    $file = UploadedFile::fake()->create('cosa.xyz', 10, 'application/x-inventado');

    expect(validarTamano($this->builder, $file))->toContain('no corresponde a ninguno de los tipos permitidos');
});

test('los interruptores de validación no se honran fuera de local', function () {
    // config/filetypes.php resuelve los flags contra APP_ENV en tiempo de carga:
    // en cualquier entorno que no sea local quedan fijados a true.
    $config = require config_path('filetypes.php');

    expect($config['global']['enable_mime_validation'])->toBeTrue();
    expect($config['global']['enable_extension_validation'])->toBeTrue();
});
