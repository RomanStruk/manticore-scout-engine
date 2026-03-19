<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\Category;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\Product;

class TableJoinsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);
        Artisan::call('scout:delete-index', ['name' => app(Category::class)->searchableAs()]);

        Artisan::call('manticore:index', ['model' => Category::class]);
        Artisan::call('manticore:index', ['model' => Product::class]);
    }

    public function test_it_join_table()
    {
        $category = Category::factory()->create(['name' => 'Animals']);

        Product::factory()->create(['name' => 'My cat loves my dogs.', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Some dogs fly.', 'category_id' => $category->id]);

        $categoryTable = $category->searchableAs();

        $searchable = Product::search('', static fn(Builder $builder)
            => $builder
            ->join($categoryTable, $categoryTable.'.id', '=', $builder->index.'.category_id')
            ->select(['id', 'name', DB::raw($categoryTable.'.name as category_name')]),
        )->raw();

        $this->assertCount(2, $searchable['hits']);
        $this->assertSame($category->name, $searchable['hits'][0]['category_name']);
        $this->assertSame($category->name, $searchable['hits'][1]['category_name']);
    }

    public function test_it_join_table_with_search()
    {
        $category = Category::factory()->create(['name' => 'Animals']);

        Product::factory()->create(['name' => 'My cat loves my dogs.', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Some dogs fly.', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Pet Hair Remover Glove', 'category_id' => $category->id]);

        $categoryTable = $category->searchableAs();

        $searchable = Product::search('dogs', static fn(Builder $builder)
            => $builder
            ->join($categoryTable, $categoryTable.'.id', '=', $builder->index.'.category_id')
            ->select(['id', 'name', DB::raw($categoryTable.'.name as category_name')]),
        )->raw();

        $this->assertCount(2, $searchable['hits']);
        $this->assertSame($category->name, $searchable['hits'][0]['category_name']);
        $this->assertSame($category->name, $searchable['hits'][1]['category_name']);
    }

    public function test_it_left_join_table()
    {
        $category = Category::factory()->create(['name' => 'Animals']);

        Product::factory()->create(['name' => 'My cat loves my dogs.', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Some dogs fly.', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Pet Hair Remover Glove', 'category_id' => 999]);

        $categoryTable = $category->searchableAs();

        $searchable = Product::search('', static fn(Builder $builder)
            => $builder
            ->select(['id', DB::raw($categoryTable.'.name as name')])
            ->leftJoin($categoryTable, $categoryTable.'.id', '=', $builder->index.'.category_id')
            ->orderBy('id'),
        )->raw();

        $this->assertCount(3, $searchable['hits']);
        $this->assertSame($category->name, $searchable['hits'][0]['name']);
        $this->assertSame($category->name, $searchable['hits'][1]['name']);
        $this->assertSame("NULL", $searchable['hits'][2]['name']);
    }
}