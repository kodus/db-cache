<?php

namespace Kodus\Cache\Tests;

use Kodus\Cache\Test\DatabaseCacheIntegrationTest;

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
