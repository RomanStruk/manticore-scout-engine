<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\PercolateProduct;

class PercolateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(PercolateProduct::class)->searchableAs()]);

        Artisan::call('manticore:index', ['model' => PercolateProduct::class]);
    }

    /** @test */
    public function it_match_my_single_document()
    {
        PercolateProduct::factory()->create(['title' => 'bag']);
        $shoes = PercolateProduct::factory()->create(['title' => 'shoes', 'color' => null]);

        $searchable = PercolateProduct::search('Beautiful shoes',
            fn(Builder $builder) => $builder->percolateQuery()
        )->get();

        $this->assertCount(1, $searchable);
        $this->assertSame($shoes->id, $searchable[0]->id);
    }

    /** @test */
    public function it_match_my_single_json_document()
    {
        PercolateProduct::factory()->create(['title' => 'bag']);
        $shoes = PercolateProduct::factory()->create(['title' => 'shoes', 'color' => null]);

        $searchable = PercolateProduct::search(json_encode(['title' =>'Beautiful shoes']),
            fn(Builder $builder) => $builder->percolateQuery(true, true)
        )->get();

        $this->assertCount(1, $searchable);
        $this->assertSame($shoes->id, $searchable[0]->id);
    }

    /** @test */
    public function it_match_my_single_filtered_json_document()
    {
        PercolateProduct::factory()->create(['title' => 'bag']);
        $whiteBag = PercolateProduct::factory()->create(['title' => 'bag', 'color' => 'white']);

        $searchable = PercolateProduct::search(json_encode(['title' =>'Beautiful bag', 'color' => 'white']),
            fn(Builder $builder) => $builder->percolateQuery(true, true)
        )->get();

        $this->assertCount(1, $searchable);
        $this->assertSame($whiteBag->id, $searchable[0]->id);
    }
}