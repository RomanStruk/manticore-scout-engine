<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class PercolateProduct extends Model
{
    use HasFactory, Searchable;

    protected $guarded = [];

    protected static function newFactory()
    {
        return PercolateProductFactory::new();
    }

    public function scoutIndexMigration(): array
    {
        return [
            'fields' => [
                'title' => ['type' => 'text'],
                'color' => ['type' => 'string'],
            ],
            'settings' => [
                'type' => 'pq'
            ],
        ];
    }

    public function toSearchableArray(): array
    {
        return array_filter([
            'id' => $this->name,
            'query' => "@title {$this->title}",
            'filters' => $this->color ? "color='{$this->color}'" : null,
        ]);
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