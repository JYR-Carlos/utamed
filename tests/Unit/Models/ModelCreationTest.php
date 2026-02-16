<?php

use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Departamento;

beforeEach(function () {
});

test("create facultad", function () {
  $facultad = Facultad::factory()->create(['nombre' => 'A']);
  
  $facultadfound = Facultad::find($facultad->id_facultad);
  expect($facultadfound->nombre)->toBe('A');

  // Usando factory - evita problema de fillable
  $departamento = Departamento::create([
    'nombre'=> 'B',
    'id_facultad'=> $facultadfound->id_facultad
  ]);
  
  // En lugar de refresh(), intenta esto:
  // $fresh = Departamento::where([
  //   ['id_facultad', '=', $departamento->id_facultad],
  // ])->orderBy('id_departamento', 'desc')->first();

  echo json_encode($departamento->getKey()); 

  // Con llave compuesta, verifica el objeto creado directamente
  expect($departamento->nombre)->toBe('B');

  // $keys = $departamento->getKey();
  // $keynames = $departamento->getKeyName();

  // echo "departamento key: " . json_encode($departamento->getKey()) . "\n";
  // echo "departamento keynames: " . json_encode($keynames) . "\n";

  // echo array_combine($keynames, $keys);
});