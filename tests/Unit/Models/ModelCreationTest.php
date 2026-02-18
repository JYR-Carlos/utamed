<?php

use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Departamento;

beforeEach(function () {
});

test("create facultad", function () {
  $facultad = Facultad::create(['nombre' => 'A']);
  
  $facultadfound = Facultad::find($facultad->id_facultad);
  expect($facultadfound->nombre)->toBe('A');

  // Usando factory - evita problema de fillable
  $departamento = Departamento::create([
    'nombre'=> 'B',
    'id_facultad'=> $facultadfound->id_facultad
  ]);

  echo json_encode($departamento->getKey()); 

  // Con llave compuesta, verifica el objeto creado directamente
  expect($departamento->nombre)->toBe('B');

});