<?php

namespace Kodus\Cache\Tests;

use Kodus\Cache\Test\Integration\DatabaseCacheIntegrationTest;

class PostgresIntegrationTest extends DatabaseCacheIntegrationTest
{
    protected static function getConnectionName(): string
    {
        return "POSTGRES";
    }
}
