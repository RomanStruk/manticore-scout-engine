<?php

namespace RomanStruk\ManticoreScoutEngine\Mysql;

use Closure;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use LogicException;
use PDO;

class ManticoreConnection
{
    protected ?PDO $pdo;
    protected ManticoreGrammar $grammar;
    protected int $fetchMode = PDO::FETCH_ASSOC;
    protected bool $loggingQueries = false;
    protected array $queryLog = [];
    protected \Closure $reconnector;

    public function __construct(ManticoreGrammar $grammar, array $config)
    {
        $this->pdo = $this->setPDO($config);
        $this->grammar = $grammar;
        $this->reconnector = function ($connection) use ($config) {
            $this->pdo = $this->setPDO($config);
        };
    }

    protected function setPDO(array $config): ?PDO
    {
        return new PDO('mysql:host=' . $config['host'] . ';port=' . $config['port'], '', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function select($sql, $bindings, int $countRowSet, bool $withMeta)
    {
        return $this->runQueryCallback($sql, $bindings, function ($query, $bindings) use ($withMeta, $countRowSet) {
            $statement = $this->prepared(
                $this->getPdo()->prepare($query)
            );

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $execute = $statement->execute();
            if ($execute === false) {
                throw new \Exception(implode('|', $statement->errorInfo()));
            }
            $result = [];
            do {
                $result[] = $statement->fetchAll(PDO::FETCH_ASSOC);
            } while ($statement->nextRowset());

            if (count($result) !== $countRowSet) {
                throw new \Exception('Count row set invalid');
            }

            $grammar = $this->getQueryGrammar();
            return [
                'hits' => $result[0],
                'facets' => $grammar->formatFacets(array_slice($result, 1, $countRowSet - 2)),
                'meta' => $withMeta ? $grammar->formatMeta($result[$countRowSet - 1]) : [],
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

    protected function runQueryCallback($query, $bindings, Closure $callback, $recursion = false)
    {
        $start = microtime(true);

        try {
            $result = $callback($query, $bindings);
        } catch (\PDOException $e) {
            if (! $recursion && static::hasGoneAway($e)) {
                $this->reconnect();
                return $this->runQueryCallback($query, $bindings, $callback, true);
            }

            throw $e;
        } catch (\Exception $e) {
            throw new QueryException(
                $query, $this->prepareBindings($bindings), $e
            );
        }

        $this->logQuery(
            $query, $bindings, $this->getElapsedTime($start)
        );

        return $result;
    }

    /**
     * Check if a PDOException is a server disconnection or not
     *
     * @param \PDOException $e
     * @return bool Returns TRUE if the PDOException is for "server has gone away" or FALSE for another error
     */
    public static function hasGoneAway(\PDOException $e): bool
    {
        return $e->getCode() == 'HY000' && stristr($e->getMessage(), 'server has gone away');
    }

    /**
     * Reconnect to the database.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function reconnect(): void
    {
        if (is_callable($this->reconnector)) {
            $this->pdo = null;

            call_user_func($this->reconnector, $this);

            return;
        }

        throw new LogicException('Lost connection and no reconnector available.');
    }

    /**
     * Get the elapsed time since a given starting point.
     */
    protected function getElapsedTime(int $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
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
                $bindings[$key] = (int)$value;
            }
        }

        return $bindings;
    }

    public function createIndex($sql)
    {
        return $this->runQueryCallback($sql, [], function ($query) {

            $execute = $this->getPdo()->exec($query);

            if ($execute === false) {
                throw new \Exception(implode('|', $this->getPdo()->errorInfo()));
            }

            return $execute;
        });
    }

    /**
     * Run an replace statement against the database.
     */
    public function replace(string $query, $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     */
    public function delete($query, $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a drop statement against the database.
     */
    public function drop($query, $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run a truncate statement against the database.
     */
    public function truncate($query, $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     */
    public function affectingStatement($query, $bindings = []): int
    {
        return $this->runQueryCallback($query, $bindings, function ($query, $bindings) {

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->rowCount();
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     */
    public function statement(string $query, array $bindings = []): bool
    {
        return $this->runQueryCallback($query, $bindings, function ($query, $bindings) {
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            return $statement->execute();
        });
    }

    /**
     * Log a query in the connection's query log.
     */
    public function logQuery(string $query, array $bindings, $time = null)
    {
        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * Get the connection query log.
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Get the query grammar used by the connection.
     */
    protected function getQueryGrammar(): ManticoreGrammar
    {
        return $this->grammar;
    }
}