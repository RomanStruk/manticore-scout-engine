<?php

namespace RomanStruk\ManticoreScoutEngine\Mysql;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class Builder
{
    public string $index;

    public ?array $columns = null;

    public string $search = '';

    public array $fullTextOperators = [
        'quorum_matching_operator' => false,
        'proximity_search_operator' => false,
    ];

    public array $wheres = [];

    public array $facets = [];

    public array $call = [];

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

    /**
     * The groupings for the query.
     */
    public array $groups = [];

    /**
     * The group N by fields parameter set
     *
     * @var int
     */
    public int $groupN = 0;

    public array $bindings = [
        'select' => [],
        'search' => [],
        'where' => [],
        'groupBy' => [],
        'options' => [],
        'order' => [],
        'calls' => [],
    ];

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

    protected bool $autoEscaping = true;

    public function __construct(array $config = [])
    {
        $this->autoEscaping = $config['auto_escape_search_phrase'] ?? true;
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
     * Set the columns to be selected.
     */
    public function select(array $columns = ['*']): Builder
    {
        $this->columns = [];
        $this->bindings['select'] = [];

        foreach ($columns as $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    /**
     * Add a new “raw” select expression to the query.
     */
    public function selectRaw(string $raw, array $bindings = []): Builder
    {
        $this->columns[] = new Expression($raw);

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    /**
     * Set the search which the query is targeting.
     */
    public function search($search = ''): Builder
    {
        $this->search = $search;

        if (!empty($this->search)) {
            if ($this->autoEscaping) {
                $search = $this->grammar->escapeQueryString($search);
            }

            $this->addBinding($search, 'search');
        }

        return $this;
    }

    /**
     * Proximity distance is specified in words, adjusted for word count,
     * and applies to all words within quotes
     *
     * @param float|int $operator
     */
    public function setQuorumMatchingOperator($operator): Builder
    {
        if (empty($this->search)) {
            return $this;
        }

        if (!is_float($operator) && !is_int($operator)) {
            throw new InvalidArgumentException('Quorum matching operator must be a float or integer.');
        }

        if ($this->fullTextOperators['proximity_search_operator'] === true) {
            throw new InvalidArgumentException('Quorum matching operator and proximity search operator cannot be used together.');
        }

        $this->fullTextOperators['quorum_matching_operator'] = true;

        $this->bindings['search'] = [];

        if ($this->autoEscaping === true) {
            $escapedSearch = $this->grammar->escapeQueryString($this->search);
        } else {
            $escapedSearch = $this->search;
        }

        $this->addBinding('"' . $escapedSearch . '"/' . $operator, 'search');

        return $this;
    }

    /**
     * Quorum matching operator introduces a kind of fuzzy matching.
     * It will only match those documents that pass a given threshold of given words.
     */
    public function setProximitySearchOperator(int $operator): Builder
    {
        if (empty($this->search)) {
            return $this;
        }

        if ($this->fullTextOperators['quorum_matching_operator'] === true) {
            throw new InvalidArgumentException('Quorum matching operator and proximity search operator cannot be used together.');
        }

        $this->fullTextOperators['proximity_search_operator'] = true;

        $this->bindings['search'] = [];

        if ($this->autoEscaping === true) {
            $escapedSearch = $this->grammar->escapeQueryString($this->search);
        } else {
            $escapedSearch = $this->search;
        }

        $this->addBinding('"' . $escapedSearch . '"~' . $operator, 'search');

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

        if (func_num_args() === 2) {
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
     * Add an "or where" clause to the query.
     */
    public function orWhere($column, $operator = null, $value = null): Builder
    {
        return $this->where($column, $operator, $value, 'or');
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
     * Add a "where not in" clause to the query.
     */
    public function whereNotIn(string $column, $values, string $boolean = 'and'): Builder
    {
        return $this->whereIn($column, $values, $boolean, true);
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
     * Add a "where not any" clause to the query.
     */
    public function whereNotAny($column, $value, string $boolean = 'and'): Builder
    {
        return $this->whereAny($column, $value, $boolean, true);
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
     * Add a "where not all" clause to the query.
     */
    public function whereNotAll($column, $value, string $boolean = 'and'): Builder
    {
        return $this->whereAll($column, $value, $boolean, true);
    }

    /**
     * Add a "where all" clause to the query.
     */
    public function whereAllMva($column, string $operator, $value, string $boolean = 'and'): Builder
    {
        if (strtolower($operator) === 'in') {
            $type = 'AllMvaIn';
        } elseif (strtolower($operator) === 'not in') {
            $type = 'AllMvaNotIn';
        } else {
            $type = 'AllMva';
        }

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );
        $this->addBinding($value, 'where');

        return $this;
    }

    /**
     * Add a "where any mva" clause to the query.
     */
    public function whereAnyMva($column, string $operator, $value, string $boolean = 'and'): Builder
    {
        if (strtolower($operator) === 'in') {
            $type = 'AnyMvaIn';
        } elseif (strtolower($operator) === 'not in') {
            $type = 'AnyMvaNotIn';
        } else {
            $type = 'AnyMva';
        }

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
     * Put the query's results in random order.
     */
    public function inRandomOrder(?int $seed = null)
    {
        if (!is_null($seed)) {
            $this->option('rand_seed', $seed);
        }

        return $this->orderByRaw($this->grammar->compileRandom());
    }

    /**
     * Put the query's results in weight order.
     */
    public function inWeightOrder(string $direction = 'asc')
    {
        return $this->orderByRaw($this->grammar->compileWeight($direction));
    }

    /**
     * Add a raw "order by" clause to the query.
     */
    public function orderByRaw(string $sql, array $bindings = []): Builder
    {
        $type = 'Raw';

        $this->orders[] = compact('type', 'sql');

        $this->addBinding($bindings, 'order');

        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param array|string ...$groups
     */
    public function groupBy(...$groups): Builder
    {
        foreach ($groups as $group) {
            $this->groups = array_merge(
                $this->groups,
                Arr::wrap($group)
            );
        }

        return $this;
    }

    /**
     * enable group N by fields
     *
     * @see https://manual.manticoresearch.com/Searching/Grouping#GROUP-BY-multiple-fields-at-once
     *
     * @param int $n
     * @return static
     */
    public function groupN(int $n)
    {
        $this->groupN = $n;

        return $this;
    }

    /**
     * Add a facet where clause to the query.
     *
     * @param string $field
     * @param string|null $by - Faceting by aggregation over another attribute
     * @param int|null $limit
     * @param string|null $sortBy
     * @param string|null $direction
     *
     * @return Builder
     */
    public function facet(string $field, ?string $by = null, ?int $limit = null, ?string $sortBy = null, ?string $direction = 'asc'): Builder
    {
        $type = 'Basic';
        $by = ! is_null($by) ? $by : $field;

        $this->facets[] = compact('type', 'field', 'by', 'limit', 'sortBy', 'direction');

        return $this;
    }

    /**
     * Add a distinct facet where clause to the query.
     */
    public function distinctFacet(string $field, string $distinct, ?string $by = null, ?int $limit = null, ?string $sortBy = null, ?string $direction = 'asc'): Builder
    {
        $type = 'Distinct';
        $by = !is_null($by) ?: $field;

        $this->facets[] = compact('type', 'field', 'distinct', 'by', 'limit', 'sortBy', 'direction');

        return $this;
    }

    /**
     * Add a distinct facet where clause to the query.
     */
    public function expressionsFacet(string $expressions, ?string $as = null, ?int $limit = null, ?string $sortBy = null, ?string $direction = 'asc'): Builder
    {
        $type = 'Expressions';

        $this->facets[] = compact('type', 'expressions', 'as', 'limit', 'sortBy', 'direction');

        return $this;
    }

    /**
     * Autocomplete (or word completion) is a feature in which an application predicts the rest of a word a user is typing.
     * On websites, it's used in search boxes, where a user starts to type a word,
     * and a dropdown with suggestions pops up so the user can select the ending from the list.
     *
     * @param array $allowOperators - using full-text operators *,^,""
     * @param bool $stats - Show statistics of keywords, default is 0
     * @param bool $foldWildcards - Fold wildcards, default is 0
     * @param bool $foldLemmas - Fold morphological lemmas, default is 0
     * @param bool $foldBlended - Fold blended words, default is 0
     * @param string|null $sort - Sort output results by either 'docs' or 'hits'. Default no sorting
     */
    public function autocomplete(array $allowOperators = ['*'], bool $stats = false, bool $foldWildcards = false, bool $foldLemmas = false, bool $foldBlended = false, ?string $sort = null): static
    {
        $type = 'Keywords';
        $options = [
            'stats' => intval($stats),
            'fold_wildcards' => intval($foldWildcards),
            'fold_lemmas' => intval($foldLemmas),
            'fold_blended' => intval($foldBlended),
        ];

        if (in_array($sort, ['docs', 'hits'])) {
            $options['sort_mode'] = $sort;
        }

        $this->call = compact('type', 'options');

        $this->bindings['search'] = [];

        $search = $this->grammar->escapeQueryString($this->search, $allowOperators);

        $this->addBinding($search, 'search');

        return $this;
    }

    /**
     * These commands provide all suggestions from the dictionary for a given word.
     *
     * @param bool $firstWord - true: will return suggestions only for the first word. false: will return suggestions only for the last word, ignoring the rest.
     * @param bool $sentence - Returns the original sentence along with the last word replaced by the matched one.
     * @param int $limit - Returns N top matches
     * @param int $maxEdits - Keeps only dictionary words with a Levenshtein distance less than or equal to N
     * @param bool $resultStats - Provides Levenshtein distance and document count of the found words
     * @param int $deltaLen - Keeps only dictionary words with a length difference less than N
     * @param int $maxMatches - Number of matches to keep
     * @param int $reject - Rejected words are matches that are not better than those already in the match queue. They are put in a rejected queue that gets reset in case one actually can go in the match queue. This parameter defines the size of the rejected queue (as reject*max(max_matched,limit)). If the rejected queue is filled, the engine stops looking for potential matches
     * @param bool $resultLine - alternate mode to display the data by returning all suggests, distances and docs each per one row
     * @param bool $nonChar - do not skip dictionary words with non alphabet symbols
     */
    public function spellCorrection(
        bool $firstWord = false,
        bool $sentence = false,
        int  $limit = 5,
        int  $maxEdits = 4,
        bool $resultStats = true,
        int  $deltaLen = 3,
        int  $maxMatches = 25,
        int  $reject = 4,
        bool $resultLine = false,
        bool $nonChar = false,
    ): static
    {
        if ($firstWord) {
            $type = 'Suggest';
        } else {
            $type = 'QSuggest';
        }
        $options = [
            'sentence' => intval($sentence),
            'limit' => $limit,
            'max_edits' => $maxEdits,
            'result_stats' => intval($resultStats),
            'delta_len' => $deltaLen,
            'max_matches' => $maxMatches,
            'reject' => $reject,
            'result_line' => intval($resultLine),
            'non_char' => intval($nonChar),
        ];

        $this->call = compact('type', 'options');

        return $this;
    }

    /**
     * Percolate queries are also known as Persistent queries, Prospective search, document routing, search in reverse, and inverse search.
     *
     * @param bool $docs - Return matching document ids
     * @param bool $docsJson - Consider input documents are JSON or plain text
     * @param bool $query - Return all info about matching query
     * @param string|null $idFieldName - Use document's own id to show in the result
     * @param bool $skipBadJson - Skip invalid JSON
     * @param bool $verbose - Extended info in SHOW META
     * @param bool $shift - Define the number which will be added to document ids if no docs_id fields provided (mostly relevant in distributed PQ modes)
     * @param string $mode - Sharded distribution mode
     */
    public function percolateQuery(
        bool    $docs = true,
        bool    $docsJson = false,
        bool    $query = true,
        ?string $idFieldName = null,
        bool    $skipBadJson = false,
        bool    $verbose = false,
        bool    $shift = false,
        string  $mode = 'sharded'
    ): static
    {
        $type = 'PercolateQuery';
        $options = [
            'docs' => intval($docs),
            'docs_json' => intval($docsJson),
            'query' => intval($query),
            'skip_bad_json' => intval($skipBadJson),
            'verbose' => intval($verbose),
            'shift' => intval($shift),
            'mode' => $mode,
        ];

        if (!is_null($idFieldName)) {
            $options['docs_id'] = $idFieldName;
        }

        $this->call = compact('type', 'options');

        $this->bindings['search'] = [];
        $this->addBinding($this->search, 'search');

        $this->meta = false;

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
        if (!empty($this->call)) {
            return $this->grammar->compileCall($this);
        }

        return $this->grammar->compileSelect($this);
    }

    /**
     * Add a binding to the query.
     */
    public function addBinding($value, string $type = 'where'): Builder
    {
        if (!array_key_exists($type, $this->bindings)) {
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
        if (!empty($this->call)) {
            return $this->connection->call(
                $this->toSql(), $this->getBindings()
            );
        }

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
     * @param mixed $id
     */
    public function delete($id = null): int
    {
        if (!is_null($id)) {
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
        return $this->connection->drop(
            $this->grammar->compileDrop($this),
        );
    }

    /**
     * Truncate records from the database.
     */
    public function truncate(?bool $withReconfigure)
    {
        if (is_null($withReconfigure)) {
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