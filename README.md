# `kodus/db-cache`

[PSR-16](https://www.php-fig.org/psr/psr-16/) cache implementation for PostgreSQL and MySQL.

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
