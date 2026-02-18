<?php

/*
|--------------------------------------------------------------------------
| Integration Tests - NO RefreshDatabase
|--------------------------------------------------------------------------
|
| Integration tests work with a real database and don't need RefreshDatabase
| since we manage cleanup manually in beforeEach hooks.
|
*/

pest()->extend(Tests\TestCase::class)
    ->in('Integration');
