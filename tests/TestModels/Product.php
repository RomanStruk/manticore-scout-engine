<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    protected $guarded = [];

    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    public function scoutIndexMigration(): array
    {
        return [
            'fields' => [
                'id' => ['type' => 'bigint'],
                'name' => ['type' => 'text'],
                'description' => ['type' => 'string'],
                'brand_name' => ['type' => 'string'],
                'price' => ['type' => 'integer'],
                'category_id' => ['type' => 'integer'],
                'created_at' => ['type' => 'timestamp'],
                'property' => ['type' => 'string'],
                'category' => ['type' => 'string  stored indexed'],
            ],
            'settings' => [
//                'min_prefix_len' => '3',
                'min_infix_len' => '1',
                'prefix_fields' => 'name',
                'expand_keywords' => '1',
                'bigram_index' => 'all',
//                'engine' => 'columnar',
            ],
            'silent' => false, // ignore_nonexistent_columns - default 0
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'brand_name' => $this->brand_name,
            'category_id' => $this->category_id,
            'property' => $this->property,
            'price' => $this->price,
            'created_at' => $this->created_at->unix(),
            'category' => (string)$this->category_id,
        ];
    }

    /**
     * Get all Scout related metadata.
     */
    public function scoutMetadata(): array
    {
        return [
            'cutoff' => 0,
            'max_matches' => 1000,
        ];
    }
}