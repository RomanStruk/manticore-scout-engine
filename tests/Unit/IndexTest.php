<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Laravel\Scout\EngineManager;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\Product;

class IndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);
    }

    /** @test */
    public function it_create_manticore_index()
    {
        Artisan::call('manticore:index', ['model' => Product::class]);

        try {
            Product::search()->raw();
            $this->assertTrue(true);

            Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);
        }catch (\Exception $exception){
            self::fail($exception->getMessage());
        }
    }

    /** @test */
    public function it_replace_document_by_id()
    {
        Artisan::call('manticore:index', ['model' => Product::class]);

        $product = Product::factory(['name' => 'some name'])->create();

        $count = app(Builder::class,['model' => $product, 'query' => ''])
            ->index($product->searchableAs())
            ->replace(array_merge($product->toSearchableArray(), ['name' => 'replace name']), $product->id);

        $this->assertSame(1, $count);

        $found = Product::search('replace name')->get()->first();

        $this->assertSame($found->id, $product->id);

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);
    }

    /** @test */
    public function it_delete_document()
    {
        Artisan::call('manticore:index', ['model' => Product::class]);

        $product = Product::factory(['name' => 'some name'])->create();
        $found = Product::search('some name')->get()->first();
        $this->assertSame($found->id, $product->id);

        app(EngineManager::class)->driver()->delete(collect([$product]));
        $foundDeleted = Product::search('some name')->get()->first();

        $this->assertNull($foundDeleted);

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);
    }
}