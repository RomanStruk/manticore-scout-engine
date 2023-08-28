<?php

namespace RomanStruk\ManticoreScoutEngine\Mysql;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class ManticoreMysqlEngine extends Engine
{
    protected array $result = [
        'hits' => [],
        'facets' => [],
        'meta' => [],
    ];

    protected ?int $paginate_max_matches = null;

    public function __construct(array $config)
    {
        $this->paginate_max_matches = $config['paginate_max_matches'];
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

        $models->each(function ($model) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return;
            }

            app(\RomanStruk\ManticoreScoutEngine\Mysql\Builder::class)
                ->index($model->searchableAs())
                ->replace($searchableData, $model->getScoutKey());
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
        $models->each(function ($model) {
            app(\RomanStruk\ManticoreScoutEngine\Mysql\Builder::class)
                ->index($model->searchableAs())
                ->delete($model->getScoutKey());
        });
    }

    /**
     * Search
     */
    public function search(Builder $builder)
    {
        $manticoreBuilder = app(\RomanStruk\ManticoreScoutEngine\Mysql\Builder::class)
            ->index($builder->index ?: $builder->model->searchableAs())
            ->search($builder->query);

        foreach ($builder->wheres as $field => $values) {
            $manticoreBuilder->where($field, '=', $values, 'and');
        }

        foreach ($builder->whereIns as $field => $values) {
            $manticoreBuilder->whereIn($field, $values, 'and');
        }

        foreach ($builder->orders as $order) {
            $manticoreBuilder->orderBy($order['column'], $order['direction']);
        }

        if ($builder->limit) {
            $manticoreBuilder->take($builder->limit);
        }

        foreach ($builder->model->scoutMetadata() as $name => $searchOption) {
            $manticoreBuilder->option($name, $searchOption);
        }

        return $this->performSearch($builder, $manticoreBuilder);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param \RomanStruk\ManticoreScoutEngine\Mysql\Builder $manticoreBuilder
     * @return mixed
     */
    protected function performSearch(Builder $builder, \RomanStruk\ManticoreScoutEngine\Mysql\Builder $manticoreBuilder)
    {
        if ($builder->callback) {
            $result = call_user_func(
                $builder->callback,
                $manticoreBuilder,
                $builder->query,
                [],
                null
            );

            if ($result instanceof \RomanStruk\ManticoreScoutEngine\Mysql\Builder) {
                return $this->result = $result->runSelect();
            }

            return $this->result = $result;
        }

        return $this->result = $manticoreBuilder->runSelect();
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

        $manticoreBuilder = app(\RomanStruk\ManticoreScoutEngine\Mysql\Builder::class)
            ->index($builder->index ?: $builder->model->searchableAs())
            ->search($builder->query)
            ->take($perPage)
            ->offset($offset);

        foreach ($builder->wheres as $field => $values) {
            $manticoreBuilder->where($field, '=', $values, 'and');
        }

        foreach ($builder->whereIns as $field => $values) {
            $manticoreBuilder->whereIn($field, $values, 'and');
        }

        foreach ($builder->orders as $order) {
            $manticoreBuilder->orderBy($order['column'], $order['direction']);
        }

        foreach ($builder->model->scoutMetadata() as $name => $searchOption) {
            $manticoreBuilder->option($name, $searchOption);
        }

        if (is_null($this->paginate_max_matches)){
            $manticoreBuilder->option('max_matches', $offset + $perPage);
        }else{
            $manticoreBuilder->option('max_matches', $this->paginate_max_matches);
        }

        return $this->performSearch($builder, $manticoreBuilder);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param array $results
     *
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        if ($results['meta']['total'] == 0) {
            return collect();
        }

        return collect($results['hits'])->pluck('id');
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder $builder
     * @param array $results
     * @param Model $model
     *
     * @return Collection
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if (array_key_exists('meta', $results) && $results['meta']['total'] == 0) {
            return $model->newCollection();
        }

        if (array_key_exists('meta', $results)){
            $objectIds = collect($results['hits'])->pluck('id')->all();
        }else{
            $objectIds = collect($results)->pluck('id')->all();
        }

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
     *
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if (array_key_exists('meta', $results) && $results['meta']['total'] == 0) {
            return LazyCollection::make($model->newCollection());
        }

        if (array_key_exists('meta', $results)){
            $objectIds = collect($results['hits'])->pluck('id')->all();
        }else{
            $objectIds = collect($results)->pluck('id')->all();
        }

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
     *
     * @return int
     */
    public function getTotalCount($results): int
    {
        if (array_key_exists('meta', $results)){
            return $results['meta']['total_found'];
        }

        return !empty($results[0]['id']) ? count($results) : 0;
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function flush($model)
    {
        app(\RomanStruk\ManticoreScoutEngine\Mysql\Builder::class)
            ->index($model->searchableAs())
            ->truncate(true);
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
        if (!array_key_exists('fields', $options)) {
            throw new \InvalidArgumentException('Manticore migration failed! Option key "fields" not found!');
        }

        $fields = $options['fields'];
        $settings = $options['settings'] ?? [];

        return app(ManticoreConnection::class)->createIndex(
            app(ManticoreGrammar::class)->compileCreateIndex($name, $fields, $settings),
        );
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
        return app(\RomanStruk\ManticoreScoutEngine\Mysql\Builder::class)
            ->index($name)
            ->drop(true);
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
        return app(\RomanStruk\ManticoreScoutEngine\Mysql\Builder::class)->$method(...$parameters);
    }

    /**
     * Get Facet
     *
     * @param string $group
     * @return array|mixed
     */
    public function getFacet(string $group)
    {
        return $this->result['facets'][$group] ?? [];
    }

    /**
     * Get Facet
     *
     * @return array|mixed
     */
    public function getFacets()
    {
        return $this->result['facets'] ?? [];
    }
}
