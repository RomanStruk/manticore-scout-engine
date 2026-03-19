<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\Product;

class HighlightTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);

        Artisan::call('manticore:index', ['model' => Product::class]);
    }

    public function test_it_highlight()
    {
        $product1 = Product::factory()->create(['name' => 'My cat loves my dogs.']);
        $product2 = Product::factory()->create(['name' => 'Some dogs fly.']);
        Product::factory()->create(['name' => 'Pet Hair Remover Glove']);

        $searchable = Product::search('dogs',
            fn(Builder $builder) => $builder->highlight()->select(['id', 'name'])
        )->get();

        $this->assertCount(2, $searchable);
        $this->assertSame('My cat loves my <b>dogs</b>.', $searchable->getHighlight()[$product1->id]);
        $this->assertSame('Some <b>dogs</b> fly.', $searchable->getHighlight()[$product2->id]);
    }

    public function test_it_highlight_before_after_match()
    {
        Product::factory()->create(['name' => 'My cat loves my dogs.']);

        $searchable = Product::search('dogs',
            fn(Builder $builder) => $builder->highlight(['before_match' => '[match]', 'after_match' => '[/match]'])
        )->raw();

        $this->assertCount(1, $searchable['hits']);
        $this->assertSame('My cat loves my [match]dogs[/match].', $searchable['hits'][0]['highlight']);
    }

    public function test_it_highlight_different_words()
    {
        $this->app['config']->set('manticore.auto_escape_search_phrase', false);

        Product::factory()->create(['name' => 'My cat loves my dogs.']);

        $searchable = Product::search('cat|dogs',
            fn(Builder $builder) => $builder->highlight()
        )->raw();

        $this->assertCount(1, $searchable['hits']);
        $this->assertSame('My <b>cat</b> loves my <b>dogs</b>.', $searchable['hits'][0]['highlight']);
    }

    public function test_it_highlight_query()
    {
        $this->app['config']->set('manticore.auto_escape_search_phrase', false);

        Product::factory()->create(['name' => 'My cat loves my dogs.']);

        $searchable = Product::search('cat',
            fn(Builder $builder) => $builder->highlight([], ['name'], 'dogs')
        )->raw();

        $this->assertCount(1, $searchable['hits']);
        $this->assertSame('My cat loves my <b>dogs</b>.', $searchable['hits'][0]['highlight']);
    }

    public function test_it_highlight_two_fields()
    {
        $this->app['config']->set('manticore.auto_escape_search_phrase', false);

        Product::factory()->create(['name' => 'My cat loves my dogs.', 'alt' => 'The cat fly)']);

        $searchable = Product::search('cat',
            fn(Builder $builder) => $builder->highlight([], ['name', 'alt'])
        )->raw();

        $this->assertCount(1, $searchable['hits']);
        $this->assertSame('My <b>cat</b> loves my dogs. | The <b>cat</b> fly)', $searchable['hits'][0]['highlight']);
    }
}