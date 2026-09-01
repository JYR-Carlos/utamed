<?php

use App\Traits\QueryScopes\FiltersContextScope;
use Illuminate\Database\Eloquent\Builder;

// Tests unitarios que mockean el query builder sin conectarse a BD

// ============================================================================
// TESTS DE SCOPE whereContext (GLOBAL)
// ============================================================================

test('whereContext con tipo direct agrega whereIn correctamente', function () {
    // Crear una clase mock que use el trait
    $model = new class {
        use FiltersContextScope;
        
        public function getTable() {
            return 'carrera';
        }
        
        public function qualifyColumn($column) {
            return $this->getTable() . '.' . $column;
        }
        
        protected function resolveContextMapping(): array {
            return ['type' => 'direct'];
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    
    // Verificar que llama whereIn con los parámetros correctos
    $builder->shouldReceive('whereIn')
        ->once()
        ->with('carrera.id_contexto', [100, 200])
        ->andReturnSelf();
    
    // Ejecutar el scope
    $result = $model->scopeWhereContext($builder, [100, 200]);
    
    expect($result)->toBe($builder);
});

test('whereContext con array vacío retorna whereRaw 1=0', function () {
    $model = new class {
        use FiltersContextScope;
        
        public function getContextType(): string {
            return 'direct';
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    
    // Verificar que llama whereRaw('1 = 0') cuando el array está vacío
    $builder->shouldReceive('whereRaw')
        ->once()
        ->with('1 = 0')
        ->andReturnSelf();
    
    $result = $model->scopeWhereContext($builder, []);
    
    expect($result)->toBe($builder);
});

// ============================================================================
// TESTS DE SCOPE whereContextHierarchy (PATH SIMPLE)
// ============================================================================

test('whereContextHierarchy con un solo path agrega whereHas correctamente', function () {
    // Crear modelo mock que simula Seccion
    $model = new class {
        public function scopeWhereContextHierarchy($query, array $contextIds)
        {
            if (empty($contextIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('curso', function ($q) use ($contextIds) {
                    $q->whereIn('id_contexto', $contextIds);
                });
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    
    // Verificar que llama whereHas con la relación 'curso'
    $builder->shouldReceive('whereHas')
        ->once()
        ->with('curso', Mockery::type('Closure'))
        ->andReturnSelf();
    
    $result = $model->scopeWhereContextHierarchy($builder, [100]);
    
    expect($result)->toBe($builder);
});

test('whereContextHierarchy con array vacío retorna whereRaw 1=0', function () {
    $model = new class {
        public function scopeWhereContextHierarchy($query, array $contextIds)
        {
            if (empty($contextIds)) {
                return $query->whereRaw('1 = 0');
            }
            return $query;
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    
    // Verificar que retorna query imposible cuando no hay contextos
    $builder->shouldReceive('whereRaw')
        ->once()
        ->with('1 = 0')
        ->andReturnSelf();
    
    $result = $model->scopeWhereContextHierarchy($builder, []);
    
    expect($result)->toBe($builder);
});

// ============================================================================
// TESTS DE SCOPE whereContextHierarchy (PATHS MÚLTIPLES)
// ============================================================================

test('whereContextHierarchy con múltiples paths usa whereHas y orWhereHas', function () {
    // Simular Asistencia con 2 paths
    $model = new class {
        public function scopeWhereContextHierarchy($query, array $contextIds)
        {
            if (empty($contextIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('inscripcionSeccion', function ($q) use ($contextIds) {
                    $q->whereHas('estudiante', function ($q) use ($contextIds) {
                    $q->whereHas('carrera', function ($q) use ($contextIds) {
                    $q->whereIn('id_contexto', $contextIds);
                });
                });
                })
                ->orWhereHas('inscripcionSeccion', function ($q) use ($contextIds) {
                    $q->whereHas('seccion', function ($q) use ($contextIds) {
                    $q->whereHas('curso', function ($q) use ($contextIds) {
                    $q->whereIn('id_contexto', $contextIds);
                });
                });
                });
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    
    // El primer path usa whereHas
    $builder->shouldReceive('whereHas')
        ->once()
        ->with('inscripcionSeccion', Mockery::type('Closure'))
        ->andReturnSelf();
    
    // El segundo path usa orWhereHas
    $builder->shouldReceive('orWhereHas')
        ->once()
        ->with('inscripcionSeccion', Mockery::type('Closure'))
        ->andReturnSelf();
    
    $result = $model->scopeWhereContextHierarchy($builder, [100]);
    
    expect($result)->toBe($builder);
});

test('scopeWhereContextHierarchy verifica closure anidados correctamente', function () {
    $model = new class {
        public function scopeWhereContextHierarchy($query, array $contextIds)
        {
            return $query->whereHas('inscripcionSeccion', function ($q) use ($contextIds) {
                $q->whereHas('estudiante', function ($q) use ($contextIds) {
                    $q->whereHas('carrera', function ($q) use ($contextIds) {
                        $q->whereIn('id_contexto', $contextIds);
                    });
                });
            });
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    $nestedBuilder = Mockery::mock(Builder::class);
    
    // Capturar el closure para verificar su comportamiento
    $capturedClosure = null;
    
    $builder->shouldReceive('whereHas')
        ->once()
        ->with('inscripcionSeccion', Mockery::on(function($closure) use (&$capturedClosure) {
            $capturedClosure = $closure;
            return is_callable($closure);
        }))
        ->andReturnSelf();
    
    $model->scopeWhereContextHierarchy($builder, [100]);
    
    // Verificar que se capturó el closure
    expect($capturedClosure)->toBeCallable();
    
    // Verificar que el closure anidado llama whereHas sobre estudiante
    $nestedBuilder->shouldReceive('whereHas')
        ->with('estudiante', Mockery::type('Closure'))
        ->andReturnSelf();
    
    $capturedClosure($nestedBuilder);
});

// ============================================================================
// TESTS DE INTEGRACIÓN: whereContext detecta tipo automáticamente
// ============================================================================

test('whereContext delega a direct cuando el modelo tiene contexto directo', function () {
    $model = new class {
        use FiltersContextScope;
        
        public function getTable() {
            return 'carrera';
        }
        
        public function qualifyColumn($column) {
            return $this->getTable() . '.' . $column;
        }
        
        protected function resolveContextMapping(): array {
            return ['type' => 'direct'];
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    
    // Carrera tiene tipo 'direct', así que debe llamar whereIn directamente
    $builder->shouldReceive('whereIn')
        ->once()
        ->with('carrera.id_contexto', [100])
        ->andReturnSelf();
    
    $result = $model->scopeWhereContext($builder, [100]);
    
    expect($result)->toBe($builder);
});

test('whereContext delega a hierarchical cuando el modelo tiene jerarquía', function () {
    $model = new class {
        use FiltersContextScope;
        
        protected function resolveContextMapping(): array {
            return ['type' => 'hierarchical'];
        }
        
        public function scopeWhereContextHierarchy($query, array $contextIds)
        {
            return $query->whereHas('curso', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
        }
    };
    
    $builder = Mockery::mock(Builder::class);
    
    // Seccion tiene tipo 'hierarchical', así que debe llamar whereHas
    $builder->shouldReceive('whereHas')
        ->once()
        ->with('curso', Mockery::type('Closure'))
        ->andReturnSelf();
    
    $result = $model->scopeWhereContext($builder, [100]);
    
    expect($result)->toBe($builder);
});

test('whereContextHierarchy con 3 niveles expone los 3 niveles al builder', function () {
    $model = new class {
        public function scopeWhereContextHierarchy($query, array $contextIds)
        {
            return $query->whereHas('nivel1', function ($q) use ($contextIds) {
                $q->whereHas('nivel2', function ($q) use ($contextIds) {
                    $q->whereHas('nivel3', function ($q) use ($contextIds) {
                        $q->whereIn('id_contexto', $contextIds);
                    });
                });
            });
        }
    };

    $builder = Mockery::mock(Builder::class);
    $level1Builder = Mockery::mock(Builder::class);
    $level2Builder = Mockery::mock(Builder::class);
    $level3Builder = Mockery::mock(Builder::class);

    $level1Closure = null;
    $level2Closure = null;
    $level3Closure = null;

    $builder->shouldReceive('whereHas')
        ->once()
        ->with('nivel1', Mockery::on(function ($closure) use (&$level1Closure) {
            $level1Closure = $closure;
            return is_callable($closure);
        }))
        ->andReturnSelf();

    $result = $model->scopeWhereContextHierarchy($builder, [10, 20]);

    expect($result)->toBe($builder);
    expect($level1Closure)->toBeCallable();

    $level1Builder->shouldReceive('whereHas')
        ->once()
        ->with('nivel2', Mockery::on(function ($closure) use (&$level2Closure) {
            $level2Closure = $closure;
            return is_callable($closure);
        }))
        ->andReturnSelf();

    $level1Closure($level1Builder);
    expect($level2Closure)->toBeCallable();

    $level2Builder->shouldReceive('whereHas')
        ->once()
        ->with('nivel3', Mockery::on(function ($closure) use (&$level3Closure) {
            $level3Closure = $closure;
            return is_callable($closure);
        }))
        ->andReturnSelf();

    $level2Closure($level2Builder);
    expect($level3Closure)->toBeCallable();

    $level3Builder->shouldReceive('whereIn')
        ->once()
        ->with('id_contexto', [10, 20])
        ->andReturnSelf();

    $level3Closure($level3Builder);
});