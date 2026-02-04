<?php

use App\Traits\HasCompositeKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TestCompositeModel extends Model
{
    use HasCompositeKey;

    protected $primaryKey = ['key_part_1', 'key_part_2'];
    public $incrementing = false;
    protected $guarded = [];
}

test('it sets keys for save query correctly', function () {
    $model = new TestCompositeModel([
        'key_part_1' => 1,
        'key_part_2' => 'A',
        'value' => 'foo'
    ]);

    // Mocking the original attributes as if it was hydrated from DB
    $reflection = new ReflectionClass($model);
    $originalProperty = $reflection->getProperty('original');
    $originalProperty->setAccessible(true);
    $originalProperty->setValue($model, [
        'key_part_1' => 1,
        'key_part_2' => 'A',
        'value' => 'foo'
    ]);

    $model->value = 'bar';

    // Mock the query builder
    $queryMock = Mockery::mock(Builder::class);

    // Expect where clauses for each key
    $queryMock->shouldReceive('where')
        ->with('key_part_1', '=', 1)
        ->once()
        ->andReturnSelf();

    $queryMock->shouldReceive('where')
        ->with('key_part_2', '=', 'A')
        ->once()
        ->andReturnSelf();

    // Access protected setKeysForSaveQuery
    $method = $reflection->getMethod('setKeysForSaveQuery');
    $method->setAccessible(true);

    // Invoke with the mock
    $method->invoke($model, $queryMock);

    // If no exception and expectations met, test passes
    expect(true)->toBeTrue();
});
