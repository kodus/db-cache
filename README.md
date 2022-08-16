# `kodus/db-cache`

[PSR-16](https://www.php-fig.org/psr/psr-16/) cache implementation for PostgreSQL (9.5+) and MySQL.

Tuned for fast, precise SQL - all operations (including multiple get/set/delete) are fully transactional
and complete in a single round-trip.

No dependencies beyond the PSR-16 interface.

[![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://packagist.org/packages/kodus/db-cache)
[![Build Status](https://travis-ci.org/kodus/db-cache.svg?branch=master)](https://travis-ci.org/kodus/db-cache)
[![Code Coverage](https://scrutinizer-ci.com/g/kodus/db-cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/kodus/db-cache/?branch=master)
[![Code Quality](https://scrutinizer-ci.com/g/kodus/db-cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kodus/db-cache/?branch=master)

## Installation

Install via [Composer](https://getcomposer.org/):

    composer require kodus/db-cache

## Usage

The constructor will automatically detect PostgreSQL or MySQL and use the appropriate adapter -
all you need to do is open a `PDO` connection and specify a table-name and default TTL:

```php
$pdo = new PDO("pgsql:host=localhost;port=5432;dbname=test", "root", "root");

$cache = new DatabaseCache($pdo, "my_cache_table", 24*60*60);
```

Tables are automatically created with the first database operation.

Please refer to [PSR-16 documentation](https://www.php-fig.org/psr/psr-16/) for general usage.
