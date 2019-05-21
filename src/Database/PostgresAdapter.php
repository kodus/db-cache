<?php

namespace Kodus\Cache\Database;

class PostgresAdapter extends Adapter
{
    protected function createTable(): void
    {
        $this->prepare(
            "CREATE TABLE {$this->table_name} (\n"
            . "  key CHARACTER VARYING NOT NULL PRIMARY KEY,\n"
            . "  data BYTEA,\n"
            . "  expires BIGINT\n"
            . ")"
        )->execute();

        $this->prepare(
            "CREATE INDEX {$this->table_name}_expires_index ON {$this->table_name} USING BTREE (expires);"
        )->execute();
    }

    public function select(string $key): ?CacheEntry
    {
        $result = $this->fetch(
            "SELECT * FROM {$this->table_name} WHERE key = :key",
            ["key" => $key]
        );

        return $result[0] ?? null;
    }

    public function upsert(array $values, int $expires): void
    {
        $placeholders = [];

        $params = [];

        $index = 0;

        foreach ($values as $key => $value) {
            $placeholders[] = "(:key_{$index}, :data_{$index}, :expires_{$index})";

            $params["key_{$index}"] = $key;
            $params["data_{$index}"] = $value;
            $params["expires_{$index}"] = $expires;

            $index += 1;
        }

        $this->execute(
            (
                "INSERT INTO {$this->table_name} (key, data, expires)"
                . " VALUES " . implode(", ", $placeholders)
                . " ON CONFLICT (key) DO UPDATE SET data=EXCLUDED.data, expires=EXCLUDED.expires"
            ),
            $params
        );
    }

    public function delete(string $key): void
    {
        $this->execute("DELETE FROM {$this->table_name} WHERE key = :key", ["key" => $key]);
    }

    public function deleteExpired(int $now): void
    {
        $this->execute("DELETE FROM {$this->table_name} WHERE :now >= expires", ["now" => $now]);
    }

    /**
     * @param string[] $keys
     *
     * @return CacheEntry[]
     */
    public function selectMultiple(array $keys): array
    {
        return $this->fetch("SELECT * FROM {$this->table_name} WHERE key IN (:keys)", ["keys" => $keys]);
    }

    public function deleteMultiple(array $keys): void
    {
        $this->execute("DELETE FROM {$this->table_name} WHERE key IN (:keys)", ["keys" => $keys]);
    }

    public function truncate(): void
    {
        $this->execute("TRUNCATE TABLE {$this->table_name}");
    }
}
