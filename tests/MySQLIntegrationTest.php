<?php

namespace Kodus\Cache\Tests;

/**
 * @skip
 */
class MySQLIntegrationTest extends DatabaseCacheIntegrationTest
{
    protected static function getConnectionName(): string
    {
        return "MYSQL";
    }
}
