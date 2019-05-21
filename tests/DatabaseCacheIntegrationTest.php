<?php

namespace Kodus\Cache\Test\Integration;

use Kodus\Cache\DatabaseCache;
use Kodus\Cache\Tests\SimpleCacheTest;
use PDO;
use PDOException;

abstract class DatabaseCacheIntegrationTest extends SimpleCacheTest
{
    const TABLE_NAME  = "test_cache";
    const DEFAULT_TTL = 86400;

    /**
     * @var PDO
     */
    private static $pdo;

    public static function setUpBeforeClass(): void
    {
        $name = static::getConnectionName();

        self::$pdo = new PDO(
            constant("DB_CACHE_TEST_{$name}_DSN"),
            constant("DB_CACHE_TEST_{$name}_USERNAME"),
            constant("DB_CACHE_TEST_{$name}_PASSWORD")
        );

        try {
            self::$pdo->exec("DROP TABLE " . self::TABLE_NAME);
        } catch (PDOException $error) {
            // ignored.
        }
    }

    abstract protected static function getConnectionName(): string;

    /**
     * @skip
     */
    public function createSimpleCache()
    {
        return new DatabaseCache(
            self::$pdo,
            self::TABLE_NAME,
            self::DEFAULT_TTL
        );
    }
}
