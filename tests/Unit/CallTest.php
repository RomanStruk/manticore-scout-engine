<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use RomanStruk\ManticoreScoutEngine\Mysql\Builder;
use RomanStruk\ManticoreScoutEngine\Tests\TestCase;
use RomanStruk\ManticoreScoutEngine\Tests\TestModels\Product;

class CallTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('scout:delete-index', ['name' => app(Product::class)->searchableAs()]);

        Artisan::call('manticore:index', ['model' => Product::class]);
    }

    /** @test */
    public function it_autocomplete()
    {
        Product::factory()->create(['name' => 'My cat loves my dog. The cat (Felis catus) is a domestic species of small carnivorous mammal.']);

        $searchable = Product::search('my*',
            fn(Builder $builder) => $builder->autocomplete(['"','^'], true)
        )->raw();

        $this->assertCount(3, $searchable);
        
        $this->assertSame($searchable[0]['normalized'], 'my');
        $this->assertSame($searchable[1]['normalized'], 'my cat');
        $this->assertSame($searchable[2]['normalized'], 'my dog');
    }

    /** @test */
    public function it_spell_correct_first_word()
    {
        Product::factory()->create(['name' => 'Crossbody Bag with Tassel']);
        Product::factory()->create(['name' => 'microfiber sheet set']);
        Product::factory()->create(['name' => 'Pet Hair Remover Glove']);

        $searchable = Product::search('bagg with tasel',
            fn(Builder $builder) => $builder->spellCorrection(true)
        )->raw();

        $this->assertCount(1, $searchable);
        $this->assertSame($searchable[0]['suggest'], 'bag');
    }

    /** @test */
    public function it_spell_correct_last_word()
    {
        Product::factory()->create(['name' => 'Crossbody Bag with Tassel']);
        Product::factory()->create(['name' => 'microfiber sheet set']);
        Product::factory()->create(['name' => 'Pet Hair Remover Glove']);

        $searchable = Product::search('bagg with tasel',
            fn(Builder $builder) => $builder->spellCorrection()
        )->raw();

        $this->assertCount(2, $searchable);

        $this->assertSame($searchable[0]['suggest'], 'tassel');
        $this->assertSame($searchable[1]['suggest'], 'set');
    }

    /** @test */
    public function it_spell_correct_last_word_and_return_sentence()
    {
        Product::factory()->create(['name' => 'Crossbody Bag with Tassel']);
        Product::factory()->create(['name' => 'microfiber sheet set']);
        Product::factory()->create(['name' => 'Pet Hair Remover Glove']);

        $searchable = Product::search('bagg with tasel',
            fn(Builder $builder) => $builder->spellCorrection(false, true)
        )->raw();

        $this->assertCount(2, $searchable);

        $this->assertSame($searchable[0]['suggest'], 'bagg with tassel');
    }
}