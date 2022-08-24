<?php

namespace Kodus\Cache\Tests;

class PostgreSQLIntegrationTest extends DatabaseCacheIntegrationTest
{
    protected static function getConnectionName(): string
    {
        return "PGSQL";
    }
}
