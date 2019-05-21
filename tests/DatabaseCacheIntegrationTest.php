<?php

namespace Kodus\Cache\Test;

use Kodus\Cache\Tests\SimpleCacheTest;
use Kodus\Cache\Tests\TestableDatabaseCache;
use PDO;
use PDOException;

abstract class DatabaseCacheIntegrationTest extends SimpleCacheTest
{
    const TABLE_NAME  = "test_cache";
    const DEFAULT_TTL = 86400;

    /**
     * @var TestableDatabaseCache
     */
    protected $cache;

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
        return new TestableDatabaseCache(
            self::$pdo,
            self::TABLE_NAME,
            self::DEFAULT_TTL
        );
    }

    protected function sleep(int $time)
    {
        $this->cache->timeTravel($time);
    }

    public function testCleanExpired()
    {
        $this->cache->set('key0', 'value', 5);
        $this->cache->set('key1', 'value', 10);

        $this->cache->timeTravel(5);
        $this->cache->cleanExpired();

        $this->assertFalse($this->cache->has('key0'), "key0 expires after 5 seconds");
        $this->assertTrue($this->cache->has('key1'), "key1 has not expired");

        $this->cache->timeTravel(5);
        $this->cache->cleanExpired();

        $this->assertFalse($this->cache->has('key1'), "key1 expires after 10 seconds");
    }
}
