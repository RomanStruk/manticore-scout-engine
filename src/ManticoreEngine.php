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
    protected ?int $maxMatches;

    public function __construct(array $config)
    {
        $this->manticore = new Client($config['connection']);
        $this->maxMatches = $config['max_matches'];
    }

    /**
     * Update the given model in the index.
     *
     * @param Collection $models
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
        return $this->performSearch($builder, array_filter([
            'filter' => $this->filters($builder),
            'limit' => $builder->limit,
            'sort' => $this->buildSortFromOrderByClauses($builder),
            'maxMatches' => $this->getMaxMatches($builder->limit)
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param array $searchParams
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $searchParams = [])
    {
        $manticore = $this->manticore->index($builder->index ?: $builder->model->searchableAs());

        if ($builder->callback) {
            $result = call_user_func(
                $builder->callback,
                $manticore,
                $builder->query,
                $searchParams
            );

            return ($result instanceof Search ? $result->get() : $result)->getResponse()->getResponse();
        }
        $manticore = $manticore->search($builder->query);

        foreach ($searchParams as $name => $option) {
            if (empty($option)){
                continue;
            }
            if (is_array($option)) {
                foreach ($option as $params) {
                    $manticore->{$name}(...$params);
                }
            } else {
                $manticore->{$name}($option);
            }
        }

        return $manticore->get()->getResponse()->getResponse();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param int $perPage
     * @param int $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $offset = ($page - 1) * $perPage;
        return $this->performSearch($builder, array_filter([
            'filters' => $this->filters($builder),
            'limit' => (int)$perPage,
            'offset' => $offset,
            'sort' => $this->buildSortFromOrderByClauses($builder),
            'maxMatches' => $this->getMaxMatches($offset + $perPage)
        ]));
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param array $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        if ($results['hits']['total'] === 0) {
            return collect();
        }

        return collect($results['hits']['hits'])->pluck('_id');
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder $builder
     * @param array $results
     * @param Model $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if ($results['hits']['total'] === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results['hits']['hits'])->pluck('_id')->all();

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
     * @param array $results
     * @param Model $model
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if ($results['hits']['total'] === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['hits']['hits'])->pluck('_id')->all();

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
     * @param array $results
     * @return int
     */
    public function getTotalCount($results): int
    {
        return $results['hits']['total'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
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
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        $index = $this->manticore->index($name);

        return $index->create($options);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
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
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->manticore->$method(...$parameters);
    }

    /**
     * Get the filter array for the query.
     *
     * @param Builder $builder
     * @return array
     */
    protected function filters(Builder $builder): array
    {
        $filters = [];
        foreach ($builder->whereIns as $key => $values) {
            $filters['filter'] = [$key, 'in', $values,];
        }
        foreach ($builder->wheres as $key => $values) {
            if (!array_key_exists($key, $builder->model->scoutMetadata())) {
                continue;
            }
            $filters['filter'] = [$key, '=', $values,];
        }

        return $filters;
    }

    /**
     * Get the sort array for the query.
     *
     * @param Builder $builder
     * @return array
     */
    protected function buildSortFromOrderByClauses(Builder $builder): array
    {
        return collect($builder->orders)->map(function (array $order) {
            return [$order['column'], $order['direction']];
        })->toArray();
    }

    /**
     * Get the max_matches option.
     *
     * @param ?int $max
     * @return int|null
     */
    protected function getMaxMatches(?int $max): ?int
    {
        if (!is_null($this->maxMatches)){
            return $this->maxMatches;
        }

        return $max;
    }
}
