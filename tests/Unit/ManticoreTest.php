<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Mysql\ManticoreConnection;
use RomanStruk\ManticoreScoutEngine\Mysql\ManticoreGrammar;
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
    public function it_order_by_raw()
    {
        Product::factory()->create(['name' => 'Officiis quidem sint ex omnis sint. Debitis atque eum modi similique sunt neque laudantium perspiciatis. Modi ipsa aut commodi et sunt non amet']);
        Product::factory()->create(['name' => 'Atque sed aut adipisci odio magnam. Offical in veniam minus et.']);

        $searchable = Product::search('offic', fn(Builder $builder) => $builder->orderByRaw('weight() DESC'))->get();

        $this->assertCount(2, $searchable);
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
        $this->assertSame(5, $facets->firstWhere('key', 'Brand Four')['count']);
        $this->assertSame(3, $facets->firstWhere('key', 'Brand Nine')['count']);
        $this->assertSame(3, $facets->firstWhere('key', 'Brand Ten')['count']);

        $distinctSearchable = Product::search('', function (Builder $builder) {
            return $builder
                ->distinctFacet('brand_name', 'property');
        })->get();

        $facets = collect($distinctSearchable->getFacet('brand_name'));
        $this->assertSame(4, $facets->firstWhere('key', 'Brand Four')['distinct']);
        $this->assertSame(3, $facets->firstWhere('key', 'Brand Nine')['distinct']);
        $this->assertSame(2, $facets->firstWhere('key', 'Brand Ten')['distinct']);
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
        $this->assertSame(1, $facets->firstWhere('brand_name', 'Brand Nine')['count']);
        $this->assertSame(1, $facets->firstWhere('brand_name', 'Brand Ten')['count']);
        $this->assertSame(2, $facets->firstWhere('brand_name', 'Brand One')['count']);
    }

    /** @test */
    public function it_will_only_match_those_documents_that_pass_a_given_threshold_of_given_words()
    {
        Product::factory()->create(['name' => 'Smartphone Apple Iphone X 64GB Freebies']);
        Product::factory()->create(['name' => 'Charging strip apple iphone x microphone a1901']);
        Product::factory()->create(['name' => 'Apple Iphone X 10 2716MAH battery']);
        Product::factory()->create(['name' => 'Outlet Product: Apple iPhone 9 256GB Gray']);
        Product::factory()->create(['name' => 'HP NVIDIA RTX A2000 6GB 4mDP GFX 340L0AA']);
        Product::factory()->create(['name' => 'HP NVIDIA Quadro RTX 5000 16GB (5JH81AA)']);

        $searchable = Product::search('apple nvidia', function (Builder $builder) {
            return $builder->setQuorumMatchingOperator(2);
        })->get();

        $this->assertSame(0, $searchable->count());

        $searchable = Product::search('apple nvidia x', function (Builder $builder) {
            return $builder->setQuorumMatchingOperator(2);
        })->get();

        $this->assertSame(3, $searchable->count());
    }

    /** @test */
    public function it_will_match_proximity_search_operator()
    {
        Product::factory()->create(['name' => 'Smartphone Apple Iphone X 64GB Freebies', 'description' => '']);
        Product::factory()->create(['name' => 'Charging strip apple iphone x microphone a1901', 'description' => '']);
        Product::factory()->create(['name' => 'Apple Iphone X 10 2716MAH battery', 'description' => '']);
        Product::factory()->create(['name' => 'Outlet Product: Apple iPhone 9 256GB Gray', 'description' => '']);

        $searchable = Product::search('Apple Iphone', function (Builder $builder) {
            return $builder->setProximitySearchOperator(4);
        })->get();

        $this->assertSame(4, $searchable->count());

        $searchable = Product::search('Smartphone 64GB 256GB', function (Builder $builder) {
            return $builder->setQuorumMatchingOperator(2);
        })->get();

        $this->assertSame(1, $searchable->count());
    }

    /** @test */
    public function it_can_switch_escaping()
    {
        Product::factory()->create(['name' => 'Smartphone Apple Iphone X 64GB Freebies', 'description' => '']);

        $this->app['config']->set('manticore.auto_escape_search_phrase', false);

        $searchableWithoutEscaping = Product::search('smartphone !Apple')->get();
        $searchableWithCustomEscaping = Product::search(ManticoreGrammar::escapeQueryString('smartphone !Apple'))->get();

        $this->app['config']->set('manticore.auto_escape_search_phrase', true);

        $searchableWithEscaping = Product::search('smartphone !Apple')->get();

        $this->assertSame(0, $searchableWithoutEscaping->count());
        $this->assertSame(1, $searchableWithCustomEscaping->count());
        $this->assertSame(1, $searchableWithEscaping->count());
    }

    /** @test */
    public function it_throw_escaping_exception()
    {
        Product::factory()->create(['name' => 'Smartphone Apple Iphone X 64GB Freebies', 'description' => '']);

        $this->app['config']->set('manticore.auto_escape_search_phrase', false);

        $this->expectExceptionCode(42000);
        Product::search('smartphone "Apple')->get();
    }

    /** @test */
    public function it_avoid_throw_escaping_exception()
    {
        Product::factory()->create(['name' => 'Smartphone Apple Iphone X 64GB Freebies', 'description' => '']);

        $this->app['config']->set('manticore.auto_escape_search_phrase', false);

        $searchable = Product::search(ManticoreGrammar::escapeQueryString('smartphone "Apple'))->get();

        $this->assertSame(1, $searchable->count());
    }

    /** @test */
    public function it_avoid_throw_escaping_exception_with_where()
    {
        Product::factory()->create(['brand_name' => 'apple']);
        Product::factory()->create(['brand_name' => 'hp']);

        $this->app['config']->set('manticore.auto_escape_search_phrase', false);

        $searchable1 = Product::search('')->where('brand_name', 'hp')->get();
        $searchable2 = Product::search('')->where('brand_name', 'hp"\\^')->get();

        $this->assertSame(1, $searchable1->count());
        $this->assertSame(0, $searchable2->count());
    }
}