<?php

namespace RomanStruk\ManticoreScoutEngine\Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use RomanStruk\ManticoreScoutEngine\ManticoreServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    public function getEnvironmentSetUp($app)
    {
        include_once __DIR__ . '/stubs/create_products_table.php.stub';
        include_once __DIR__ . '/stubs/create_percolate_products_table.php.stub';

        // run the up() method (perform the migration)
        (new \CreateProductsTable)->up();
        (new \CreatePercolateProductsTable)->up();
    }

    protected function getPackageProviders($app)
    {
        return [
            ScoutServiceProvider::class,
            ManticoreServiceProvider::class,
        ];
    }
}
