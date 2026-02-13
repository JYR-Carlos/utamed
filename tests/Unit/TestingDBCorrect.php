<?php

use Illuminate\Support\Facades\DB;

test("Is testing with the correct database connection?", function () {
    $defaultConnection = config('database.default');
    expect($defaultConnection)->toBe('pgsql');
});

test("Is using the correct testing database port (16666)?", function () {
    $dbPort = config('database.connections.pgsql.port');
    expect((int) $dbPort)->toBe(16666);
});

test("Is using the correct testing database host?", function () {
    $dbHost = config('database.connections.pgsql.host');
    expect($dbHost)->toBe('127.0.0.1');
});

test("Can connect to the testing database?", function () {
    try {
        DB::connection('pgsql')->getPdo();
        expect(true)->toBeTrue();
    } catch (\Exception $e) {
        expect(false)->toBeTrue('No se pudo conectar a la BD: ' . $e->getMessage());
    };
});