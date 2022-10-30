<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\Product;

class ManticoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);
        Artisan::call('manticore:index', ['model' => Product::class]);
    }

    /** @test */
    public function it_search_by_word()
    {
        $expected = Product::factory()->create(['name' => 'secret word']);

        $searchable = Product::search('secret')->first();

        $this->assertSame($searchable->id, $expected->id);
    }

    /** @test */
    public function it_select_facets()
    {
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 3]);
        Product::factory()->create(['category_id' => 2]);
        Product::factory()->create(['category_id' => 21, 'brand_name' => 'audi']);
        Product::factory()->create(['name' => 'test', 'category_id' => 22]);
        Product::factory()->create(['name' => 'test', 'category_id' => 22, 'brand_name' => 'audi']);
        Product::factory()->create(['category_id' => 22, 'brand_name' => 'bmw']);
        Product::factory()->create(['name' => 'testosterone', 'category_id' => 22, 'brand_name' => 'audi']);
        Product::factory()->create(['name' => 'tes toste rone', 'category_id' => 22, 'brand_name' => 'audi']);

        $searchable = Product::search('', function (Builder $builder) {
            return $builder
                ->facet('category_id')
                ->facet('brand_name');
        })->get();

        $facet = $searchable->getFacet('category_id');

        foreach ($facet as $item) {
            if ($item['key'] == 21) {
                $this->assertEquals(1, $item['count']);
            }
            if ($item['key'] == 22) {
                $this->assertEquals(5, $item['count']);
            }
        }

        $this->assertCount(9, $searchable);
    }
}