<?php

/**
 * Unit tests for App\Enums\ContextType
 *
 * Prueba los métodos de jerarquía puros del enum:
 *   - parentMap()
 *   - immediateParent()
 *   - ancestorChain()
 *   - isAncestorOf()
 *   - isDescendantOf()
 *   - descendantTypes()
 *
 * Sin acceso a BD — lógica pura de PHP.
 *
 * Jerarquía de referencia:
 *   ACTIVIDAD → CURSO → CARRERA → DEPARTAMENTO → FACULTAD → GLOBAL → null
 */

use App\Enums\ContextType;

// ============================================================================
// parentMap()
// ============================================================================

describe('ContextType::parentMap()', function () {

  test('GLOBAL no tiene padre (null)', function () {
    expect(ContextType::parentMap()['global'])->toBeNull();
  });

  test('FACULTAD apunta a GLOBAL', function () {
    expect(ContextType::parentMap()['facultad'])->toBe(ContextType::GLOBAL);
  });

  test('DEPARTAMENTO apunta a FACULTAD', function () {
    expect(ContextType::parentMap()['departamento'])->toBe(ContextType::FACULTAD);
  });

  test('CARRERA apunta a DEPARTAMENTO', function () {
    expect(ContextType::parentMap()['carrera'])->toBe(ContextType::DEPARTAMENTO);
  });

  test('CURSO apunta a CARRERA', function () {
    expect(ContextType::parentMap()['curso'])->toBe(ContextType::CARRERA);
  });

  test('ACTIVIDAD apunta a CURSO', function () {
    expect(ContextType::parentMap()['actividad'])->toBe(ContextType::CURSO);
  });

  test('contiene exactamente 6 entradas (una por case)', function () {
    expect(ContextType::parentMap())->toHaveCount(6);
  });
});

// ============================================================================
// immediateParent()
// ============================================================================

describe('ContextType::immediateParent()', function () {

  test('GLOBAL->immediateParent() = null', function () {
    expect(ContextType::GLOBAL ->immediateParent())->toBeNull();
  });

  test('FACULTAD->immediateParent() = GLOBAL', function () {
    expect(ContextType::FACULTAD->immediateParent())->toBe(ContextType::GLOBAL);
  });

  test('DEPARTAMENTO->immediateParent() = FACULTAD', function () {
    expect(ContextType::DEPARTAMENTO->immediateParent())->toBe(ContextType::FACULTAD);
  });

  test('CARRERA->immediateParent() = DEPARTAMENTO', function () {
    expect(ContextType::CARRERA->immediateParent())->toBe(ContextType::DEPARTAMENTO);
  });

  test('CURSO->immediateParent() = CARRERA', function () {
    expect(ContextType::CURSO->immediateParent())->toBe(ContextType::CARRERA);
  });

  test('ACTIVIDAD->immediateParent() = CURSO', function () {
    expect(ContextType::ACTIVIDAD->immediateParent())->toBe(ContextType::CURSO);
  });
});

// ============================================================================
// ancestorChain()
// ============================================================================

describe('ContextType::ancestorChain()', function () {

  test('GLOBAL->ancestorChain() está vacío', function () {
    expect(ContextType::GLOBAL ->ancestorChain())->toBe([]);
  });

  test('FACULTAD->ancestorChain() = [GLOBAL]', function () {
    expect(ContextType::FACULTAD->ancestorChain())->toBe([ContextType::GLOBAL]);
  });

  test('DEPARTAMENTO->ancestorChain() = [FACULTAD, GLOBAL]', function () {
    expect(ContextType::DEPARTAMENTO->ancestorChain())->toBe([
      ContextType::FACULTAD,
      ContextType::GLOBAL ,
    ]);
  });

  test('CARRERA->ancestorChain() = [DEPARTAMENTO, FACULTAD, GLOBAL]', function () {
    expect(ContextType::CARRERA->ancestorChain())->toBe([
      ContextType::DEPARTAMENTO,
      ContextType::FACULTAD,
      ContextType::GLOBAL ,
    ]);
  });

  test('CURSO->ancestorChain() = [CARRERA, DEPARTAMENTO, FACULTAD, GLOBAL]', function () {
    expect(ContextType::CURSO->ancestorChain())->toBe([
      ContextType::CARRERA,
      ContextType::DEPARTAMENTO,
      ContextType::FACULTAD,
      ContextType::GLOBAL ,
    ]);
  });

  test('ACTIVIDAD->ancestorChain() = [CURSO, CARRERA, DEPARTAMENTO, FACULTAD, GLOBAL]', function () {
    expect(ContextType::ACTIVIDAD->ancestorChain())->toBe([
      ContextType::CURSO,
      ContextType::CARRERA,
      ContextType::DEPARTAMENTO,
      ContextType::FACULTAD,
      ContextType::GLOBAL ,
    ]);
  });

  test('el primer elemento de la cadena es el padre inmediato', function () {
    foreach (ContextType::cases() as $type) {
      if ($type === ContextType::GLOBAL) {
        continue; // GLOBAL tiene cadena vacía
      }
      $chain = $type->ancestorChain();
      expect($chain[0])->toBe($type->immediateParent());
    }
  });

  test('el último elemento de la cadena siempre es GLOBAL (para tipos concretos)', function () {
    foreach (ContextType::cases() as $type) {
      if ($type === ContextType::GLOBAL) {
        continue;
      }
      $chain = $type->ancestorChain();
      expect(end($chain))->toBe(ContextType::GLOBAL);
    }
  });
});

// ============================================================================
// isAncestorOf()
// ============================================================================

describe('ContextType::isAncestorOf()', function () {

  test('FACULTAD es ancestro directo de DEPARTAMENTO', function () {
    expect(ContextType::FACULTAD->isAncestorOf(ContextType::DEPARTAMENTO))->toBeTrue();
  });

  test('FACULTAD es ancestro transitivo de CARRERA', function () {
    expect(ContextType::FACULTAD->isAncestorOf(ContextType::CARRERA))->toBeTrue();
  });

  test('FACULTAD es ancestro transitivo de ACTIVIDAD (tipo hoja)', function () {
    expect(ContextType::FACULTAD->isAncestorOf(ContextType::ACTIVIDAD))->toBeTrue();
  });

  test('GLOBAL es ancestro de CARRERA', function () {
    expect(ContextType::GLOBAL ->isAncestorOf(ContextType::CARRERA))->toBeTrue();
  });

  test('GLOBAL es ancestro de todos los tipos concretos', function () {
    foreach (ContextType::cases() as $type) {
      if ($type === ContextType::GLOBAL) {
        continue;
      }
      expect(ContextType::GLOBAL ->isAncestorOf($type))->toBeTrue();
    }
  });

  test('CARRERA NO es ancestro de FACULTAD (relación inversa)', function () {
    expect(ContextType::CARRERA->isAncestorOf(ContextType::FACULTAD))->toBeFalse();
  });

  test('CARRERA NO es ancestro de CARRERA (no reflexivo)', function () {
    expect(ContextType::CARRERA->isAncestorOf(ContextType::CARRERA))->toBeFalse();
  });

  test('GLOBAL NO es ancestro de sí mismo', function () {
    expect(ContextType::GLOBAL ->isAncestorOf(ContextType::GLOBAL))->toBeFalse();
  });

  test('ACTIVIDAD no es ancestro de ningún tipo (tipo hoja)', function () {
    foreach (ContextType::cases() as $type) {
      expect(ContextType::ACTIVIDAD->isAncestorOf($type))->toBeFalse();
    }
  });
});

// ============================================================================
// isDescendantOf()
// ============================================================================

describe('ContextType::isDescendantOf()', function () {

  test('CARRERA es descendiente de FACULTAD (transitivo)', function () {
    expect(ContextType::CARRERA->isDescendantOf(ContextType::FACULTAD))->toBeTrue();
  });

  test('CARRERA es descendiente de DEPARTAMENTO (directo)', function () {
    expect(ContextType::CARRERA->isDescendantOf(ContextType::DEPARTAMENTO))->toBeTrue();
  });

  test('ACTIVIDAD es descendiente de GLOBAL', function () {
    expect(ContextType::ACTIVIDAD->isDescendantOf(ContextType::GLOBAL))->toBeTrue();
  });

  test('FACULTAD NO es descendiente de CARRERA', function () {
    expect(ContextType::FACULTAD->isDescendantOf(ContextType::CARRERA))->toBeFalse();
  });

  test('CARRERA NO es descendiente de CARRERA (no reflexivo)', function () {
    expect(ContextType::CARRERA->isDescendantOf(ContextType::CARRERA))->toBeFalse();
  });

  test('isDescendantOf es el inverso exacto de isAncestorOf', function () {
    $pairs = [
      [ContextType::CARRERA, ContextType::FACULTAD],
      [ContextType::ACTIVIDAD, ContextType::GLOBAL],
      [ContextType::DEPARTAMENTO, ContextType::GLOBAL],
      [ContextType::CURSO, ContextType::CARRERA],
    ];

    foreach ($pairs as [$descendant, $ancestor]) {
      expect($descendant->isDescendantOf($ancestor))
        ->toBe($ancestor->isAncestorOf($descendant));
    }
  });
});

// ============================================================================
// descendantTypes()
// ============================================================================

describe('ContextType::descendantTypes()', function () {

  test('ACTIVIDAD (hoja) no tiene descendientes', function () {
    expect(ContextType::ACTIVIDAD->descendantTypes())->toBe([]);
  });

  test('CURSO->descendantTypes() = [ACTIVIDAD]', function () {
    expect(ContextType::CURSO->descendantTypes())->toBe([ContextType::ACTIVIDAD]);
  });

  test('CARRERA->descendantTypes() contiene CURSO y ACTIVIDAD', function () {
    $result = ContextType::CARRERA->descendantTypes();

    expect($result)->toContain(ContextType::CURSO);
    expect($result)->toContain(ContextType::ACTIVIDAD);
    expect($result)->toHaveCount(2);
  });

  test('DEPARTAMENTO->descendantTypes() contiene CARRERA, CURSO y ACTIVIDAD', function () {
    $result = ContextType::DEPARTAMENTO->descendantTypes();

    expect($result)->toContain(ContextType::CARRERA);
    expect($result)->toContain(ContextType::CURSO);
    expect($result)->toContain(ContextType::ACTIVIDAD);
    expect($result)->toHaveCount(3);
  });

  test('FACULTAD->descendantTypes() contiene DEPARTAMENTO, CARRERA, CURSO y ACTIVIDAD', function () {
    $result = ContextType::FACULTAD->descendantTypes();

    expect($result)->toContain(ContextType::DEPARTAMENTO);
    expect($result)->toContain(ContextType::CARRERA);
    expect($result)->toContain(ContextType::CURSO);
    expect($result)->toContain(ContextType::ACTIVIDAD);
    expect($result)->toHaveCount(4);
  });

  test('GLOBAL->descendantTypes() contiene los 5 tipos concretos', function () {
    $result = ContextType::GLOBAL ->descendantTypes();

    expect($result)->toContain(ContextType::FACULTAD);
    expect($result)->toContain(ContextType::DEPARTAMENTO);
    expect($result)->toContain(ContextType::CARRERA);
    expect($result)->toContain(ContextType::CURSO);
    expect($result)->toContain(ContextType::ACTIVIDAD);
    expect($result)->toHaveCount(5);
  });

  test('cada resultado de descendantTypes() es descendiente del tipo llamado', function () {
    foreach (ContextType::cases() as $type) {
      foreach ($type->descendantTypes() as $desc) {
        expect($type->isAncestorOf($desc))
          ->toBeTrue("{$desc->value} debería ser descendiente de {$type->value}");
      }
    }
  });

  test('GLOBAL no aparece en descendantTypes() de ningún tipo', function () {
    foreach (ContextType::cases() as $type) {
      expect(ContextType::GLOBAL ->descendantTypes())
        ->not->toContain(ContextType::GLOBAL);
    }
  });
});
