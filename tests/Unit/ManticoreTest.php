<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Mysql\ManticoreConnection;
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

    /** @test */
    public function it_order_by_random()
    {
        Product::factory()->create();
        Product::factory()->create();

        $searchable1 = Product::search('', function (Builder $builder) {
            return $builder->inRandomOrder(1);
        })->first();

        $searchable2 = Product::search('', function (Builder $builder) {
            return $builder->inRandomOrder(2);
        })->first();

        $this->assertTrue($searchable1->id != $searchable2->id);
    }

    /** @test */
    public function it_group_by_field()
    {
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 2]);

        $searchable1 = Product::search('', function (Builder $builder) {
            return $builder->groupBy('category_id')->facet('category_id');
        })->get();

        $searchable2 = Product::search('')->get();

        $this->assertTrue($searchable1->count() != $searchable2->count());
        $this->assertTrue($searchable1->count() == 2);
    }

    /** @test */
    public function it_count_distinct_facet()
    {
        Product::factory()->create(['brand_name' => 'Brand Nine', 'property' => 'Four']);
        Product::factory()->create(['brand_name' => 'Brand Ten', 'property' => 'Four']);
        Product::factory()->create(['brand_name' => 'Brand One', 'property' => 'Five']);
        Product::factory()->create(['brand_name' => 'Brand Seven', 'property' => 'Nine']);
        Product::factory()->create(['brand_name' => 'Brand Seven', 'property' => 'Seven']);
        Product::factory()->create(['brand_name' => 'Brand Three', 'property' => 'Seven']);
        Product::factory()->create(['brand_name' => 'Brand Nine', 'property' => 'Five']);
        Product::factory()->create(['brand_name' => 'Brand Three', 'property' => 'Eight']);
        Product::factory()->create(['brand_name' => 'Brand Two', 'property' => 'Eight']);
        Product::factory()->create(['brand_name' => 'Brand Six', 'property' => 'Eight']);
        Product::factory()->create(['brand_name' => 'Brand Ten', 'property' => 'Four']);
        Product::factory()->create(['brand_name' => 'Brand Ten', 'property' => 'Two']);
        Product::factory()->create(['brand_name' => 'Brand Four', 'property' => 'Ten']);
        Product::factory()->create(['brand_name' => 'Brand One', 'property' => 'Nine']);
        Product::factory()->create(['brand_name' => 'Brand Four', 'property' => 'Eight']);
        Product::factory()->create(['brand_name' => 'Brand Nine', 'property' => 'Seven']);
        Product::factory()->create(['brand_name' => 'Brand Four', 'property' => 'Five']);
        Product::factory()->create(['brand_name' => 'Brand Three', 'property' => 'Four']);
        Product::factory()->create(['brand_name' => 'Brand Four', 'property' => 'Two']);
        Product::factory()->create(['brand_name' => 'Brand Four', 'property' => 'Eight']);

        $searchable = Product::search('', function (Builder $builder) {
            return $builder->facet('brand_name');
        })->get();

        $facets = collect($searchable->getFacet('brand_name'));
        $this->assertSame('5', $facets->firstWhere('key', 'Brand Four')['count']);
        $this->assertSame('3', $facets->firstWhere('key', 'Brand Nine')['count']);
        $this->assertSame('3', $facets->firstWhere('key', 'Brand Ten')['count']);

        $distinctSearchable = Product::search('', function (Builder $builder) {
            return $builder
                ->distinctFacet('brand_name', 'property');
        })->get();

        $facets = collect($distinctSearchable->getFacet('brand_name'));
        $this->assertSame('4', $facets->firstWhere('key', 'Brand Four')['distinct']);
        $this->assertSame('3', $facets->firstWhere('key', 'Brand Nine')['distinct']);
        $this->assertSame('2', $facets->firstWhere('key', 'Brand Ten')['distinct']);
    }

    /** @test */
    public function it_count_expressions_facet()
    {
        Product::factory()->create(['brand_name' => 'Brand Nine', 'price' => 200]);
        Product::factory()->create(['brand_name' => 'Brand Ten', 'price' => 200]);
        Product::factory()->create(['brand_name' => 'Brand One', 'price' => 400]);
        Product::factory()->create(['brand_name' => 'Brand One', 'price' => 400]);

        $searchable = Product::search('', function (Builder $builder) {
            return $builder
                ->select(['*'])
                ->selectRaw('INTERVAL(price,200,400) as price_range')
                ->expressionsFacet('price_range', 'fprice_range,brand_name', 5, 'brand_name', 'desc');
        })->get();

        $facets = collect($searchable->getFacet('fprice_range'));
        $this->assertSame('1', $facets->firstWhere('brand_name', 'Brand Nine')['count']);
        $this->assertSame('1', $facets->firstWhere('brand_name', 'Brand Ten')['count']);
        $this->assertSame('2', $facets->firstWhere('brand_name', 'Brand One')['count']);
    }
}