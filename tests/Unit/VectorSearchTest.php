<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\SimilarProduct;

class VectorSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(SimilarProduct::class)->searchableAs()]);

        Artisan::call('manticore:index', ['model' => SimilarProduct::class]);
    }


    public function test_it_create_record_with_correct_vector()
    {
        $vector = [0.653448, 0.192478, 0.017971, 0.339821];
        SimilarProduct::factory()->create(['name' => 'Foo Bar', 'vector' => $vector]);

        $searchable = SimilarProduct::search('foo')->raw()['hits'][0]['vector'];

        $this->assertSame($searchable, implode(',', $vector));
    }


    public function test_it_knn_vector_search()
    {
        $expected1 = SimilarProduct::factory()->create([
            'name' => 'Foo Bar', 'vector' => [0.653448, 0.192478, 0.017971, 0.339821],
        ]);
        $expected2 = SimilarProduct::factory()->create([
            'name' => 'Foo Bar', 'vector' => [-0.148894, 0.748278, 0.091892, -0.095406],
        ]);

        $searchable = SimilarProduct::search('', function (Builder $q) {
            return $q->whereRaw("knn ( vector, 5, (0.286569,-0.031816,0.066684,0.032926), 2000 )");
        })->get();

        $this->assertSame($searchable[0]->id, $expected1->id);
        $this->assertSame($searchable[1]->id, $expected2->id);
    }


    public function test_it_find_similar_docs_by_id()
    {
        SimilarProduct::factory()->create(['name' => 'Foo Bar', 'vector' => [0.653448, 0.192478, 0.017971, 0.339821]]);
        $expected = SimilarProduct::factory()->create([
            'name' => 'Foo Bar', 'vector' => [-0.148894, 0.748278, 0.091892, -0.095406],
        ]);

        $searchable = SimilarProduct::search('', function (Builder $q) {
            return $q->whereRaw("knn ( vector, 5, 1 )")->discardMeta();
        })->get();

        $this->assertSame($searchable[0]->id, $expected->id);
    }
}