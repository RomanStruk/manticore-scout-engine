<?php

namespace RomanStruk\ManticoreScoutEngine;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Manticoresearch\Client;
use Manticoresearch\ResultSet;
use Manticoresearch\Search;

class ManticoreEngine extends Engine
{
    protected Client $manticore;
    protected array $options = [];

    protected array $config;

    public function __construct(array $config)
    {
        $this->manticore = new Client($config['connection']);
        $this->config = $config;
    }

    /**
     * Update the given model in the index.
     *
     * @param Collection $models
     *
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $index = $this->manticore->index($models->first()->searchableAs());

        $models->each(function ($model) use ($index) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return;
            }

            $index->replaceDocument($searchableData, $model->getScoutKey());
        });
    }

    /**
     * Remove the given model from the index.
     *
     * @param Collection $models
     *
     * @return void
     */
    public function delete($models)
    {
        $model = $models->first();
        $index = $this->manticore->index($model->searchableAs());

        $models->each(function ($model) use ($index) {
            $index->deleteDocument($model->getScoutKey());
        });
    }

    public function search(Builder $builder)
    {
        $this->options = array_merge($this->options, $builder->model->scoutMetadata());
        $this->options['max_matches'] = $this->getMaxMatches($builder->limit);

        return $this->performSearch($builder, array_filter([
            'filter' => [$builder->wheres, $builder->whereIns],
            'limit' => $builder->limit,
            'orderBy' => $this->buildSortFromOrderByClauses($builder),
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param array $searchParams
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $searchParams)
    {
        $index = $this->manticore->index($builder->index ?: $builder->model->searchableAs());

        if ($builder->callback) {
            $result = call_user_func(
                $builder->callback,
                $index,
                $builder->query,
                []
            );
            if ($result instanceof Search) {
                return $result->get();
            }

            return $result;
        }

        $search = $index->search($builder->query);

        foreach ($this->options as $key => $option) {
            $search->option($key, $option);
        }

        foreach ($searchParams['filter'] ?? [] as $filters) {
            foreach ($filters as $key => $value) {
                $search->filter($key,'gte', $value);
            }
        }
        if ($searchParams['limit'] ?? false){
            $search->limit($searchParams['limit']);
        }

        foreach ($searchParams['orderBy'] ?? [] as $sort){
            $search->sort(...$sort);
        }

        return $search->get();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param int $perPage
     * @param int $page
     *
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $offset = ($page - 1) * $perPage;

        $this->options = array_merge($this->options, $builder->model->scoutMetadata());
        $this->options['max_matches'] = $this->config['paginate_max_matches'] ?: ($offset + $perPage);

        return $this->performSearch($builder, array_filter([
            'filter' => [$builder->wheres, $builder->whereIns],
            'limit' => $builder->limit,
            'orderBy' => $this->buildSortFromOrderByClauses($builder),
        ]));
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param ResultSet $results
     *
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        if ($results->getTotal() === 0) {
            return collect();
        }

        return collect($results->getResponse()->getResponse()['hits']['hits'] ?? [])->pluck('_id');
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder $builder
     * @param ResultSet $results
     * @param Model $model
     *
     * @return Collection
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if ($results->getTotal() === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results->getResponse()->getResponse()['hits']['hits'] ?? [])->pluck('_id')->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
            $builder, $objectIds
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param Builder $builder
     * @param ResultSet $results
     * @param Model $model
     *
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if ($results->getTotal() === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results->getResponse()->getResponse()['hits']['hits'] ?? [])->pluck('_id')->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds(
            $builder, $objectIds
        )->cursor()->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param ResultSet $results
     *
     * @return int
     */
    public function getTotalCount($results): int
    {
        return $results->getTotal();
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     *
     * @return void
     */
    public function flush($model)
    {
        $index = $this->manticore->index($model->searchableAs());

        $index->truncate();
    }

    /**
     * Create a search index.
     *
     * @param string $name
     * @param array $options
     *
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        $index = $this->manticore->index($name);

        if (!array_key_exists('fields', $options)){
            throw new \InvalidArgumentException('Manticore migration failed! Option key "fields" not found!');
        }

        $fields = $options['fields'];
        $settings = $options['settings'] ?? [];
        $silent = $options['silent'] ?? false;

        return $index->create($fields, $settings, $silent);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function deleteIndex($name)
    {
        $index = $this->manticore->index($name);

        return $index->drop(true);
    }

    /**
     * Dynamically call the Manticore client instance.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->manticore->$method(...$parameters);
    }

    /**
     * Get the sort array for the query.
     *
     * @param Builder $builder
     *
     * @return array
     */
    protected function buildSortFromOrderByClauses(Builder $builder): array
    {
        return collect($builder->orders)->mapWithKeys(function (array $order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }

    /**
     * Get the max_matches option.
     *
     * @param ?int $max
     *
     * @return int
     */
    protected function getMaxMatches(?int $max): int
    {
        if (! is_null($this->options['max_matches'])) {
            return $this->options['max_matches'];
        }

        return $max ?: 1000;
    }
}
