<?php

namespace RomanStruk\ManticoreScoutEngine\Mysql;

use Illuminate\Database\Grammar;
use Illuminate\Support\Arr;

class ManticoreGrammar extends Grammar
{
    /**
     * The components that make up a select clause.
     */
    protected array $selectComponents = [
        'columns',
        'index',
        'search',
        'wheres',
        'groups',
        'orders',
        'limit',
        'offset',
        'options',
        'facets',
        'meta'
    ];

    /**
     * Compile a select query into SQL.
     */
    public function compileSelect(Builder $query): string
    {
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $sql = trim($this->concatenate(
            $this->compileComponents($query))
        );

        $query->columns = $original;

        return $sql;
    }

    /**
     * Compile a call query into SQL.
     */
    public function compileCall(Builder $query): string
    {
        $component = $query->call['type'];

        $method = 'compile' . ucfirst($component);

        return $this->$method($query, $query->call['options']);
    }

    /**
     * Compile the components necessary for a select clause.
     */
    protected function compileComponents(Builder $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compile the "options" portions of the call query.
     */
    protected function compileCallOptions(array $options): string
    {
        return trim(', ' . collect($options)
                ->map(fn($v, $k) => (is_int($v) ? $v : "'{$v}'") . ' as ' . $k)
                ->implode(', '));
    }

    /**
     * Compile the "select *" portion of the query.
     */
    protected function compileColumns(Builder $query, $columns): string
    {
        return 'select ' . $this->columnize($columns);
    }

    /**
     * Compile the "index" portion of the query.
     */
    protected function compileIndex($query, $table): string
    {
        return 'from ' . $this->wrapTable($table);
    }

    /**
     * Compile the "search" portions of the query.
     */
    protected function compileSearch(Builder $query): string
    {
        if (empty($query->search)) {
            return '';
        }

        return 'where match(?)';
    }

    /**
     * Compile the "autocomplete" portions of the query.
     */
    protected function compileKeywords(Builder $query, array $options): string
    {
        if (empty($query->search)) {
            throw new \Exception('Empty Search query!');
        }

        $options = $this->compileCallOptions($options);

        return "CALL KEYWORDS(?, '{$query->index}'{$options});";
    }

    /**
     * Compile the "SUGGEST" portions of the query.
     */
    protected function compileSuggest(Builder $query, array $options): string
    {
        if (empty($query->search)) {
            throw new \Exception('Empty SUGGEST query!');
        }

        $options = $this->compileCallOptions($options);

        return "CALL SUGGEST(?, '{$query->index}'{$options});";
    }

    /**
     * Compile the "QSUGGEST" portions of the query.
     */
    protected function compileQSuggest(Builder $query, array $options): string
    {
        if (empty($query->search)) {
            throw new \Exception('Empty search query!');
        }

        $options = $this->compileCallOptions($options);

        return "CALL QSUGGEST(?, '{$query->index}'{$options});";
    }

    /**
     * Compile the "Percolate Query" portions of the query.
     */
    protected function compilePercolateQuery(Builder $query, array $options): string
    {
        if (empty($query->search)) {
            throw new \Exception('Empty PQ query!');
        }

        $options = $this->compileCallOptions($options);

        return "CALL PQ('{$query->index}', ?{$options});";
    }

    /**
     * Compile the "where" portions of the query.
     */
    public function compileWheres(Builder $query): string
    {
        if (empty($query->wheres) && empty($query->search)) {
            return '';
        }

        $sql = collect($query->wheres)->map(function ($where) use ($query) {
            return $where['boolean'] . ' ' . $this->{"where{$where['type']}"}($query, $where);
        })->all();

        if (count($sql) > 0) {
            return (empty($query->search) ? 'where ' : ' and ') . $this->removeLeadingBoolean(implode(' ', $sql));
        }

        return '';
    }

    /**
     * Remove the leading boolean from a statement.
     */
    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    /**
     * Concatenate an array of segments, removing empties.
     */
    protected function concatenate(array $segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string)$value !== '';
        }));
    }

    /**
     * Compile a nested where clause.
     */
    protected function whereNested(Builder $query, $where): string
    {
        return '(' . substr($this->compileWheres($where['query']), 6) . ')';
    }

    /**
     * Compile the "order by" portions of the query.
     */
    public function compileOrders(Builder $query, array $orders): string
    {
        if (!empty($orders)) {
            return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * Compile the query orders to an array.
     */
    protected function compileOrdersToArray(Builder $query, array $orders): array
    {
        return array_map(function ($order) {
            return $order['sql'] ?? $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $orders);
    }

    /**
     * Compile the random statement into SQL.
     */
    public function compileRandom(): string
    {
        return 'rand()';
    }

    /**
     * Compile the random statement into SQL.
     */
    public function compileWeight(string $direction = 'asc'): string
    {
        return 'weight() ' . $direction;
    }

    /**
     * Wrap a single string in keyword identifiers.
     */
    protected function wrapValue($value): string
    {
        return $value === '*' ? $value : '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * Compile a "basic where" clause.
     */
    protected function whereBasic(Builder $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return $this->wrap($where['column']) . ' ' . $operator . ' ' . $value;
    }

    /**
     * Compile a "where in" clause.
     */
    protected function whereIn(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * Compile a raw where clause.
     */
    protected function whereRaw(Builder $query, array $where): string
    {
        return $where['sql'];
    }

    /**
     * Compile a "where not in" clause.
     */
    protected function whereNotIn(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * Compile a "where any" clause.
     */
    protected function whereAny(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' any (' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile a "where not any" clause.
     */
    protected function whereNotAny(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' not any (' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile a "where any mva" clause.
     */
    protected function whereAnyMva(Builder $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return 'any(' . $this->wrap($where['column']) . ') ' . $operator . ' ' . $value;
    }

    /**
     * Compile a "where any mva in" clause.
     */
    protected function whereAnyMvaIn(Builder $query, array $where): string
    {
        return 'any(' . $this->wrap($where['column']) . ') in (' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile a "where any mva not in" clause.
     */
    protected function whereAnyMvaNotIn(Builder $query, array $where): string
    {
        return 'any(' . $this->wrap($where['column']) . ') not in (' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile a "where all mva" clause.
     */
    protected function whereAllMva(Builder $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return 'all(' . $this->wrap($where['column']) . ')' . $operator . $value;
    }

    /**
     * Compile a "where all mva in" clause.
     */
    protected function whereAllMvaIn(Builder $query, array $where): string
    {
        return 'all(' . $this->wrap($where['column']) . ') in (' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile a "where all mva not in" clause.
     */
    protected function whereAllMvaNotIn(Builder $query, array $where): string
    {
        return 'all(' . $this->wrap($where['column']) . ') not in (' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile a "where all" clause.
     */
    protected function whereAll(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' all( ' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile a "where not all" clause.
     */
    protected function whereNotAll(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' not all( ' . $this->parameterize($where['value']) . ')';
    }

    /**
     * Compile the "limit" portions of the query.
     */
    protected function compileLimit(Builder $query, int $limit): string
    {
        return 'limit ' . $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     */
    protected function compileOffset(Builder $query, int $offset): string
    {
        return 'offset ' . $offset;
    }

    /**
     * Compile the "group by" portions of the query.
     */
    protected function compileGroups(Builder $query, array $groups): string
    {
        if (empty($groups)) {
            return '';
        }

        return 'group by ' . $this->columnize($groups);
    }

    /**
     * Compile the "facets" portions of the query.
     */
    public function compileFacets(Builder $query, array $facets): string
    {
        if (empty($facets)) {
            return ';';
        }

        $sql = collect($facets)->map(function ($facet) use ($query) {
            return $this->{"facet{$facet['type']}"}($query, $facet);
        })->all();

        return implode(' ', $sql) . ';';
    }

    /**
     * Compile a "basic facet" clause.
     *
     * FACET {expr_list} [BY {expr_list} ] [DISTINCT {field_name}] [ORDER BY {expr | FACET()} {ASC | DESC}] [LIMIT [offset,] count]
     */
    protected function facetBasic(Builder $query, array $facet): string
    {
        $sql[] = 'facet ' . $this->wrap($facet['field']);
        $sql[] = 'by ' . $this->wrap($facet['by']);
        if ($facet['sortBy']) {
            $sql[] = "order by {$facet['sortBy']} {$facet['direction']}";
        }
        if ($facet['limit']) {
            $sql[] = "limit {$facet['limit']}";
        }

        return implode(' ', $sql);
    }

    /**
     * Compile a "facet distinct" clause.
     */
    protected function facetDistinct(Builder $query, array $facet): string
    {
        $sql[] = 'facet ' . $this->wrap($facet['field']);
        $sql[] = 'by ' . $this->wrap($facet['by']);
        $sql[] = 'distinct ' . $this->wrap($facet['distinct']);

        if ($facet['sortBy']) {
            $sql[] = "order by {$facet['sortBy']} {$facet['direction']}";
        }
        if ($facet['limit']) {
            $sql[] = "limit {$facet['limit']}";
        }

        return implode(' ', $sql);
    }

    /**
     * Compile a "facet over expressions", "facet over multi-level grouping" clause.
     */
    protected function facetExpressions(Builder $query, array $facet): string
    {
        $sql[] = "facet {$facet['expressions']}";
        $sql[] = "as {$facet['as']}";
        if ($facet['sortBy']) {
            $sql[] = "order by {$facet['sortBy']} {$facet['direction']}";
        }
        if ($facet['limit']) {
            $sql[] = "limit {$facet['limit']}";
        }

        return implode(' ', $sql);
    }

    /**
     * Compile the "options" portions of the query.
     */
    public function compileOptions(Builder $builder, array $options): string
    {
        if (empty($options)) {
            return '';
        }
        return trim('option ' . collect($options)->map(fn($v, $k) => $k . '=' . $v)->implode(', '));
    }

    /**
     * Compile the "meta" portions of the query.
     */
    public function compileMeta(Builder $query): string
    {
        if (!$query->meta) {
            return '';
        }

        return 'show meta;';
    }

    /**
     * Formatting meta results
     */
    public function formatMeta(array $meta): array
    {
        return collect($meta)->mapWithKeys(fn($value) => [$value['Variable_name'] => $value['Value']])->all();
    }

    /**
     * Formatting facets results
     */
    public function formatFacets(array $facets): array
    {
        return collect($facets)->mapWithKeys(
            fn($values, $key) => [
                empty($values) ? $key : array_key_first($values[0]) => collect($values)
                    ->map(function ($facet) {
                        $result[array_key_first($facet)] = $val = array_shift($facet);
                        $result['key'] = $val; // support previous version

                        if ($keyFirst = array_key_first($facet)) {
                            $result[$this->getFacetHumanKeyCount($keyFirst)] = array_shift($facet);
                        }
                        if ($keyFirst = array_key_first($facet)) {
                            $result[$this->getFacetHumanKeyCount($keyFirst)] = array_shift($facet);
                        }

                        return $result;
                    })->all()
            ]
        )->all();
    }

    /**
     * Return facet count field
     * count|distinct|field_name
     */
    protected function getFacetHumanKeyCount($facetKey): string
    {
        if ($facetKey === 'count(*)') {
            return 'count';
        }

        return preg_replace('/count\((distinct)\s\w+\)/', '$1', $facetKey);
    }

    /**
     * Escaping characters in query string
     */
    public static function escape(string $binding, ?array $allowEscapingSymbols = []): string
    {
        $binding = str_replace(["\\"], ["\\\\\\\\"], $binding);

        $search = ["'", '!', '"', '$', '(', ')', '-', '/', '<', '@', '^', '|', '~'];
        $replace = ["\'", '\!', '\"', '\$', '\(', '\)', '\-', '\/', '\<', '\@', '\^', '\|', '\~'];

        foreach ($allowEscapingSymbols as $allowEscapingSymbol) {
            unset($search[array_search($allowEscapingSymbol, $search)]);
            unset($replace[array_search('\\' . $allowEscapingSymbol, $replace)]);
        }

        return str_replace($search, $replace, $binding);
    }

    /**
     * When deleting an index via SQL, adding IF EXISTS can be used to delete the index only if it exists. If you try to delete a non-existing index with the IF EXISTS option, nothing happens.
     * When deleting an index via PHP, you can add an optional silent parameter which works the same as IF EXISTS .
     */
    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->index);

        // remove search request for build correct wheres
        $query->search = '';

        $where = $this->compileWheres($query);

        return "delete from {$table} {$where};";
    }


    /**
     * Prepare the bindings for a delete statement.
     */
    public function prepareBindingsForDelete(array $bindings): array
    {
        return Arr::flatten(
            Arr::except($bindings, ['select', 'search'])
        );
    }

    /**
     * Compile the "migrate" portions of the query.
     */
    public function compileCreateIndex(string $index, array $fields, array $settings = []): string
    {
        $table = $this->wrapTable($index);
        $fields = $this->compileFieldsForMigrate($fields);

        return 'CREATE TABLE ' . $table . '(' . implode(', ', $fields) . ') '
            . implode(' ', $this->compileSettingsForMigrate($settings));
    }

    public function compileFieldsForMigrate(array $fields): array
    {
        return collect($fields)->map(function ($types, $field) {
            return $field . ' ' . $types['type'];
        })->all();
    }

    /**
     * Compile settings of migrate portions of the query.
     */
    public function compileSettingsForMigrate(array $settings): array
    {
        return collect($settings)->map(function ($value, $name) {
            return $name . " = '" . $value . "'";
        })->all();
    }

    /**
     * Prepare the bindings for an update statement.
     *
     * Booleans, integers, and doubles are inserted into JSON replace as raw values.
     */
    public function prepareBindingsForReplace(array $bindings, array $values): array
    {
        $cleanBindings = Arr::except($bindings, ['search']);

        return array_values(
            array_merge(Arr::flatten($values), Arr::flatten($cleanBindings))
        );
    }

    /**
     * Compile a replacement statement into SQL.
     */
    public function compileReplace(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->index);

        $columns = $this->compileReplaceColumns($query, $values);
        $values = $this->compileReplaceValues($query, $values);

        return "replace into {$table} ({$columns}) VALUES ({$values});";
    }

    /**
     * Compile the columns for a replacement statement.
     */
    protected function compileReplaceColumns(Builder $query, array $values): string
    {
        return collect($values)->map(function ($value, $key) {
            return $this->wrap($key);
        })->implode(', ');
    }

    /**
     * Compile the columns for a replacement statement.
     */
    protected function compileReplaceValues(Builder $query, array $values): string
    {
        return collect($values)
            ->map(fn($value) => is_array($value) ? $this->compileReplaceMvaValues($value) : '?')
            ->implode(', ');
    }

    /**
     * Compile the mva columns for a replacement statement.
     */
    protected function compileReplaceMvaValues(array $values): string
    {
        return '(' . collect($values)
                ->map(fn($value) => '?')
                ->implode(', ') . ')';
    }

    /**
     * Compile a drop statement into SQL.
     */
    public function compileDrop(Builder $query): string
    {
        $table = $this->wrapTable($query->index);

        return "drop table {$table}";
    }

    /**
     * Compile a truncate statement into SQL.
     */
    public function compileTruncate(Builder $query): string
    {
        $table = $this->wrapTable($query->index);

        $withReconfigure = $query->withReconfigure ? ' with reconfigure;' : ';';

        return "truncate table {$table}{$withReconfigure}";
    }
}