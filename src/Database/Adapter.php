<?php

namespace Kodus\Cache\Database;

use Generator;
use function implode;
use PDO;
use PDOException;
use PDOStatement;
use function gettype;

abstract class Adapter
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var string
     */
    protected $table_name;

    public function __construct(PDO $pdo, string $table_name)
    {
        $this->pdo = $pdo;
        $this->table_name = $table_name;
    }

    abstract public function select(string $key): ?CacheEntry;

    abstract public function delete(string $key): void;

    /**
     * @param string[] $keys
     *
     * @return CacheEntry[]
     */
    abstract public function selectMultiple(array $keys): array;

    abstract public function deleteMultiple(array $keys): void;

    abstract public function upsert(array $values, int $expires): void;

    abstract public function truncate(): void;

    abstract public function deleteExpired(int $now): void;

    abstract protected function createTable(): void;

    protected function execute(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->prepare($sql, $params);

        try {
            if ($statement->execute() !== true) {
                throw new PDOException(implode(" ", $statement->errorInfo()));
            }
        } catch (PDOException $error) {
            $this->createTable();

            if ($statement->execute() !== true) {
                throw new PDOException(implode(" ", $statement->errorInfo()));
            }
        }

        return $statement;
    }

    /**
     * @param string $sql
     * @param array  $params
     *
     * @return CacheEntry[]
     */
    protected function fetch(string $sql, array $params = []): array
    {
        $rows = $this->execute($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        $result = [];

        foreach ($rows as $row) {
            $result[] = new CacheEntry($row["key"], stream_get_contents($row["data"]), $row["expires"]);
        }

        return $result;
    }

    protected function prepare(string $sql, array $params = []): PDOStatement
    {
        static $PDO_TYPE = [
            'integer' => PDO::PARAM_INT,
            'boolean' => PDO::PARAM_BOOL,
            'NULL'    => PDO::PARAM_NULL,
        ];

        foreach ($params as $name => $value) {
            if (is_array($value)) {
                $placeholders = [];

                foreach ($value as $key => $array_value) {
                    $index = "{$name}_{$key}";
                    $params[$index] = $array_value;
                    $placeholders[] = ":{$index}";
                }

                $sql = str_replace(":{$name}", implode(", ", $placeholders), $sql);

                unset($params[$name]);
            }
        }

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $statement->bindValue(
                ":{$name}",
                $value,
                $PDO_TYPE[gettype($value)] ?? PDO::PARAM_LOB
            );
        }

        return $statement;
    }
}
