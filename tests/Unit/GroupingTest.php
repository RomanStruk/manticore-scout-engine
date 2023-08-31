<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\Product;

class GroupingTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);

        Artisan::call('manticore:index', ['model' => Product::class]);
    }

    /** @test */
    public function it_group_by_field()
    {
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 2]);
        Product::factory()->create(['category_id' => 3]);
        Product::factory()->create(['category_id' => 4]);
        Product::factory()->create(['category_id' => 4]);
        Product::factory()->create(['category_id' => 4]);

        $searchable = Product::search('',
            fn(Builder $builder) => $builder->groupBy('category_id')
        )->get();

        $this->assertCount(4, $searchable);
    }

    /** @test */
    public function it_group_by_multiple_fields()
    {
        Product::factory()->create(['category_id' => 1, 'brand_name' => 'brand1']);
        Product::factory()->create(['category_id' => 1, 'brand_name' => 'brand2']);
        Product::factory()->create(['category_id' => 2, 'brand_name' => 'brand1']);
        Product::factory()->create(['category_id' => 3, 'brand_name' => 'brand1']);
        Product::factory()->create(['category_id' => 4, 'brand_name' => 'brand1']);
        Product::factory()->create(['category_id' => 4, 'brand_name' => 'brand3']);
        Product::factory()->create(['category_id' => 4, 'brand_name' => 'brand3']);

        $searchable = Product::search('',
            fn(Builder $builder) => $builder->groupBy('category_id', 'brand_name')
        )->get();

        $this->assertCount(6, $searchable);
    }

    /** @test */
    public function it_group_n_by()
    {
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 2]);
        Product::factory()->create(['category_id' => 3]);
        Product::factory()->create(['category_id' => 4]);
        Product::factory()->create(['category_id' => 4]);
        Product::factory()->create(['category_id' => 4]);

        $searchable = Product::search('',
            fn(Builder $builder) => $builder->groupN(2)->groupBy('category_id')
        )->get();

        $this->assertCount(6, $searchable);
    }

    /** @test */
    public function it_sorting_group_by()
    {
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 1]);
        Product::factory()->create(['category_id' => 2]);
        Product::factory()->create(['category_id' => 3]);
        Product::factory()->create(['category_id' => 4]);
        Product::factory()->create(['category_id' => 4]);
        Product::factory()->create(['category_id' => 4]);

        $searchable = Product::search('',
            fn(Builder $builder) => $builder
                ->select()
                ->selectRaw('AVG(id) avg')
                ->groupBy('category_id')
                ->orderBy('avg', 'desc')
        )->get();

        $this->assertCount(4, $searchable);
        $this->assertSame(6, $searchable[0]->id);
        $this->assertSame(1, $searchable[3]->id);
    }

    /** @test */
    public function it_group_by_within_order_by_desc()
    {
        Product::factory()->create(['category_id' => 1, 'brand_name' => '1']);
        Product::factory()->create(['category_id' => 1, 'brand_name' => '2']);

        Product::factory()->create(['category_id' => 2, 'brand_name' => '1']);

        Product::factory()->create(['category_id' => 3, 'brand_name' => '1']);

        Product::factory()->create(['category_id' => 4, 'brand_name' => '1']);
        Product::factory()->create(['category_id' => 4, 'brand_name' => '3']);
        Product::factory()->create(['category_id' => 4, 'brand_name' => '3']);

        $searchable = Product::search('',
            fn(Builder $builder) => $builder
                ->groupBy('category_id')
                ->groupOrderBy('brand_name', 'desc')
        )->raw()['hits'];

        $this->assertSame($searchable[0]['category_id'], 4);
        $this->assertSame($searchable[0]['brand_name'], '3');

        $this->assertSame($searchable[1]['category_id'], 3);
        $this->assertSame($searchable[1]['brand_name'], '1');

        $this->assertSame($searchable[2]['category_id'], 2);
        $this->assertSame($searchable[2]['brand_name'], '1');

        $this->assertSame($searchable[3]['category_id'], 1);
        $this->assertSame($searchable[3]['brand_name'], '2');
    }

    /** @test */
    public function it_group_by_within_order_by_asc()
    {
        Product::factory()->create(['category_id' => 1, 'brand_name' => '1']);
        Product::factory()->create(['category_id' => 1, 'brand_name' => '2']);

        Product::factory()->create(['category_id' => 2, 'brand_name' => '1']);

        Product::factory()->create(['category_id' => 3, 'brand_name' => '1']);

        Product::factory()->create(['category_id' => 4, 'brand_name' => '1']);
        Product::factory()->create(['category_id' => 4, 'brand_name' => '3']);
        Product::factory()->create(['category_id' => 4, 'brand_name' => '3']);

        $searchable = Product::search('',
            fn(Builder $builder) => $builder
                ->groupBy('category_id')
                ->groupOrderBy('brand_name')
        )->raw()['hits'];

        $this->assertSame($searchable[0]['category_id'], 4);
        $this->assertSame($searchable[0]['brand_name'], '1');

        $this->assertSame($searchable[1]['category_id'], 3);
        $this->assertSame($searchable[1]['brand_name'], '1');

        $this->assertSame($searchable[2]['category_id'], 2);
        $this->assertSame($searchable[2]['brand_name'], '1');

        $this->assertSame($searchable[3]['category_id'], 1);
        $this->assertSame($searchable[3]['brand_name'], '1');
    }
}