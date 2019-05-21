<?php

namespace Kodus\Cache\Tests;

use Kodus\Cache\Test\DatabaseCacheIntegrationTest;

class PostgreSQLIntegrationTest extends DatabaseCacheIntegrationTest
{
    protected static function getConnectionName(): string
    {
        return "PGSQL";
    }
}
