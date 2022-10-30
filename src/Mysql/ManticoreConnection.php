<?php

namespace RomanStruk\ManticoreScoutEngine\Mysql;

use Closure;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use PDO;

class ManticoreConnection
{
    protected PDO $pdo;

    protected int $fetchMode = PDO::FETCH_ASSOC;

    protected ManticoreGrammar $grammar;

    public function __construct(ManticoreGrammar $grammar, array $config)
    {
        $this->pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port']);
        $this->grammar = $grammar;
    }

    public function select($sql, $bindings, int $countRowSet, bool $withMeta)
    {
        return $this->runQueryCallback($sql, $bindings, function ($query, $bindings) use ($withMeta, $countRowSet) {
            $statement = $this->prepared(
                $this->getPdo()->prepare($query)
            );

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $execute = $statement->execute();
            if($execute === false){
                throw new \Exception(implode('|', $statement->errorInfo()));
            }
            $result = [];
            do {
                $result[] = $statement->fetchAll(PDO::FETCH_ASSOC);
            } while ($statement->nextRowset());

            if (count($result) !== $countRowSet){
                throw new \Exception('Count row set invalid');
            }

            $grammar = $this->getQueryGrammar();
            return [
                'hits' => $result[0],
                'facets' => $grammar->formatFacets(array_slice($result, 1, $countRowSet-2)),
                'meta' => $withMeta ? $grammar->formatMeta($result[$countRowSet-1]): [],
            ];
        });
    }

    public function bindValues(\PDOStatement $statement, array $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) || is_float($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            return $callback($query, $bindings);
        }
        catch (\Exception $e) {
            throw new QueryException(
                $query, $this->prepareBindings($bindings), $e
            );
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function prepared(\PDOStatement $prepare): \PDOStatement
    {
        $prepare->setFetchMode($this->fetchMode);

        return $prepare;
    }

    public function prepareBindings(array $bindings): array
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            } elseif (is_string($value)) {
                $bindings[$key] = $grammar->escape($value);
            }
        }

        return $bindings;
    }

    public function createIndex($sql)
    {
        return $this->runQueryCallback($sql, [], function ($query){

            $execute = $this->getPdo()->exec($query);

            if($execute === false){
                throw new \Exception(implode('|', $this->getPdo()->errorInfo()));
            }

            return $execute;
        });
    }

    /**
     * Run an replace statement against the database.
     */
    public function replace(string $query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->runQueryCallback($query, $bindings, function ($query, $bindings) {

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->rowCount();
        });
    }

    protected function getQueryGrammar(): ManticoreGrammar
    {
        return $this->grammar;
    }
}