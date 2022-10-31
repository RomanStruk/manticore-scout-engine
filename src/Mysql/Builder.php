<?php

namespace RomanStruk\ManticoreScoutEngine\Mysql;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class Builder
{
    public string $index;

    public ?array $columns = null;

    public string $search = '';

    public array $wheres = [];

    public array $facets = [];

    public bool $meta = false;

    public int $offset;

    /**
     * The "limit" that should be applied to the search.
     */
    public int $limit;

    /**
     * The "order" that should be applied to the search.
     */
    public array $orders = [];

    public array $options = [
        'max_matches' => 1000,
    ];

    /**
     * When RECONFIGURE option is used new tokenization, morphology, and other text processing settings specified
     * in the config take effect after the index gets cleared. In case the schema declaration in config is different
     * from the index schema the new schema from config got applied after index get cleared.
     * With this option clearing and reconfiguring an index becomes one atomic operation.
     */
    public bool $withReconfigure = false;

    protected ManticoreGrammar $grammar;
    protected ManticoreConnection $connection;

    public array $bindings = [
        'select' => [],
        'search' => [],
        'where' => [],
        'options' => [],
        'order' => [],
    ];

    public function __construct()
    {
        $this->grammar = app(ManticoreGrammar::class);
        $this->connection = app(ManticoreConnection::class);

        $this->meta();
    }

    /**
     * Set the table which the query is targeting.
     */
    public function index(string $indexName): Builder
    {
        $this->index = $indexName;

        return $this;
    }

    /**
     * Set the search which the query is targeting.
     */
    public function search($search = ''): Builder
    {
        $this->search = $search;

        if (!empty($this->search)){
            $this->addBinding($search, 'search');
        }

        return $this;
    }

    /**
     * Add a raw where clause to the query.
     */
    public function whereRaw($sql, array $bindings = [], string $boolean = 'and'): Builder
    {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];

        $this->addBinding($bindings, 'where');

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and'): Builder
    {
        $type = 'Basic';

        if ($column instanceof Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }
        if (is_null($operator)){
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );
        $this->addBinding($this->flattenValue($value), 'where');

        return $this;
    }

    /**
     * Add a nested where statement to the query.
     */
    public function whereNested(Closure $callback, string $boolean = 'and'): Builder
    {
        call_user_func($callback, $builder = $this->forNestedWhere());

        return $this->addNestedWhereQuery($builder, $boolean);
    }

    /**
     * Add another query builder as a nested where to the query builder.
     */
    public function addNestedWhereQuery(Builder $query, string $boolean = 'and'): Builder
    {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['where'], 'where');
        }

        return $this;
    }

    /**
     * Create a new query instance for nested where condition.
     */
    public function forNestedWhere(): Builder
    {
        return new self();
    }

    /**
     * Add a "where in" clause to the query.
     */
    public function whereIn(string $column, $values, string $boolean = 'and', bool $not = false): Builder
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        $this->addBinding($values, 'where');

        return $this;
    }

    /**
     * Add a "where any" clause to the query.
     */
    public function whereAny($column, $value, string $boolean = 'and', bool $not = false): Builder
    {
        $type = $not ? 'NotAny' : 'Any';

        $this->wheres[] = compact(
            'type', 'column', 'value', 'boolean'
        );

        $this->addBinding($this->stringValue($value), 'where');

        return $this;
    }

    /**
     * Add a "where all" clause to the query.
     */
    public function whereAll($column, $value, string $boolean = 'and', bool $not = false): Builder
    {
        $type = $not ? 'NotAll' : 'All';

        $this->wheres[] = compact(
            'type', 'column', 'value', 'boolean'
        );

        $this->addBinding($this->stringValue($value), 'where');

        return $this;
    }

    /**
     * Add a "where all" clause to the query.
     */
    public function whereAllMva($column, string $operator,  $value, string $boolean = 'and'): Builder
    {
        $type = 'AllMva';

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );
        $this->addBinding($value, 'where');

        return $this;
    }

    /**
     * Add a "where any mva" clause to the query.
     */
    public function whereAnyMva($column, string $operator,  $value, string $boolean = 'and'): Builder
    {
        $type = 'AnyMva';

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        $this->addBinding($value, 'where');

        return $this;
    }

    /**
     * Set the "limit" for the search query.
     */
    public function take(int $limit): Builder
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $offset): Builder
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Add an "order" for the search query.
     */
    public function orderBy(string $column, string $direction = 'asc'): Builder
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) == 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * Add a facet where clause to the query.
     */
    public function facet(string $field, ?string $group = null, ?int $limit = null, ?string $as = null): Builder
    {
        $this->facets[] = compact('field', 'group', 'limit', 'as');

        return $this;
    }

    /**
     * Set the "option" value of the query.
     */
    public function option(string $key, string $value): Builder
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Init the "meta" information.
     */
    protected function meta(): Builder
    {
        $this->meta = true;

        return $this;
    }

    /**
     * Get the SQL representation of the query.
     */
    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    /**
     * Add a binding to the query.
     */
    public function addBinding($value, string $type = 'where'): Builder
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Get a scalar type value from an unknown type of input.
     */
    protected function flattenValue($value)
    {
        return is_array($value) ? head(Arr::flatten($value)) : $value;
    }

    /**
     * Cast array value to string
     */
    protected function stringValue($value): array
    {
        return collect($value)->map(fn($v) => (string)$v)->all();
    }

    /**
     * Get the current query value bindings in a flattened array.
     */
    public function getBindings(): array
    {
        return Arr::flatten($this->bindings);
    }

    /**
     * Get the raw array of bindings.
     */
    public function getRawBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Count set rows
     */
    public function countSetRows(): int
    {
        return 1 + count($this->facets) + (int)$this->meta;
    }

    /**
     * Run the query as a "select" statement against the connection.
     */
    public function runSelect(): array
    {
        return $this->connection->select(
            $this->toSql(), $this->getBindings(), $this->countSetRows(), $this->meta
        );
    }

    /**
     * Replace records from the database.
     */
    public function replace($values, int $id)
    {
        $values = array_merge($values, ['id' => $id]);

        $sql = $this->grammar->compileReplace($this, $values);

        return $this->connection->replace($sql,
            $this->grammar->prepareBindingsForReplace($this->bindings, $values)
        );
    }

    /**
     * Delete records from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null): int
    {
        if (! is_null($id)) {
            $this->where('id', $id);
        }

        return $this->connection->delete(
            $this->grammar->compileDelete($this),
            $this->grammar->prepareBindingsForDelete($this->bindings)
        );
    }

    /**
     * Delete records from the database.
     */
    public function drop(): int
    {
        return $this->connection->delete(
            $this->grammar->compileDrop($this),
        );
    }

    /**
     * Truncate records from the database.
     */
    public function truncate(?bool $withReconfigure)
    {
        if (is_null($withReconfigure)){
            $this->withReconfigure($withReconfigure);
        }

        return $this->connection->truncate(
            $this->grammar->compileTruncate($this),
        );
    }

    /**
     * Set reconfigure parameter for truncate
     */
    public function withReconfigure($withReconfigure): Builder
    {
        $this->withReconfigure = $withReconfigure;

        return $this;
    }


    /**
     * Apply the callback's query changes if the given "value" is true.
     */
    public function when($value, Closure $callback, ?Closure $default = null): Builder
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }


    /**
     * Dump the current SQL and bindings.
     *
     * @return $this
     */
    public function dump(): Builder
    {
        dump($this->toSql(), $this->getBindings());

        return $this;
    }

    /**
     * Die and dump the current SQL and bindings.
     *
     * @return never
     */
    public function dd()
    {
        dd($this->toSql(), $this->getBindings());
    }
}