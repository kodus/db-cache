<?php

namespace Kodus\Cache\Tests;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

abstract class DatabaseCacheIntegrationTest extends TestCase
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
     * Data provider for invalid cache keys.
     *
     * @return array
     */
    public static function invalidKeys()
    {
        return array_merge(
            self::invalidArrayKeys(),
            [
                [2],
            ]
        );
    }

    /**
     * Data provider for valid keys.
     *
     * @return array
     */
    public static function validKeys()
    {
        return [
            ['AbC19_.'],
            ['1234567890123456789012345678901234567890123456789012345678901234'],
        ];
    }

    /**
     * Data provider for invalid array keys.
     *
     * @return array
     */
    public static function invalidArrayKeys()
    {
        return [
            [''],
            [true],
            [false],
            [null],
            [2.5],
            ['{str'],
            ['rand{'],
            ['rand{str'],
            ['rand}str'],
            ['rand(str'],
            ['rand)str'],
            ['rand/str'],
            ['rand\\str'],
            ['rand@str'],
            ['rand:str'],
            [new \stdClass()],
            [['array']],
        ];
    }

    /**
     * Data provider for valid data to store.
     *
     * @return array
     */
    public static function validData()
    {
        return [
            ['AbC19_.'],
            [4711],
            [47.11],
            [true],
            [null],
            [['key' => 'value']],
            [new \stdClass()],
        ];
    }

    /**
     * @return array
     */
    public static function invalidTtl()
    {
        return [
            [''],
            [true],
            [false],
            ['abc'],
            [2.5],
            [' 1'], // can be casted to a int
            ['12foo'], // can be casted to a int
            ['025'], // can be interpreted as hex
            [new \stdClass()],
            [['array']],
        ];
    }

    public function testSet()
    {
        $result = $this->cache->set('key', 'value');
        $this->assertTrue($result, 'set() must return true if success');
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testSetMultipleNoIterable()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->setMultiple('key');
    }

    public function testObjectAsDefaultValue()
    {
        $obj = new \stdClass();
        $obj->foo = 'value';
        $this->assertEquals($obj, $this->cache->get('key', $obj));
    }

    public function testGet()
    {
        $this->assertNull($this->cache->get('key'));
        $this->assertEquals('foo', $this->cache->get('key', 'foo'));

        $this->cache->set('key', 'value');
        $this->assertEquals('value', $this->cache->get('key', 'foo'));
    }

    /**
     * @dataProvider validData
     */
    public function testSetValidData($data)
    {
        $this->cache->set('key', $data);
        $this->assertEquals($data, $this->cache->get('key'));
    }

    public function testSetTtl()
    {
        $result = $this->cache->set('key1', 'value', 1);
        $this->assertTrue($result, 'set() must return true if success');
        $this->assertEquals('value', $this->cache->get('key1'));
        $this->sleep(2);
        $this->assertNull($this->cache->get('key1'), 'Value must expire after ttl.');

        $this->cache->set('key2', 'value', new \DateInterval('PT1S'));
        $this->assertEquals('value', $this->cache->get('key2'));
        $this->sleep(2);
        $this->assertNull($this->cache->get('key2'), 'Value must expire after ttl.');
    }

    public function testDeleteMultiple()
    {
        $this->assertTrue($this->cache->deleteMultiple([]), 'Deleting a empty array should return true');
        $this->assertTrue($this->cache->deleteMultiple(['key']),
            'Deleting a value that does not exist should return true');

        $this->cache->set('key0', 'value0');
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->deleteMultiple(['key0', 'key1']), 'Delete must return true on success');
        $this->assertNull($this->cache->get('key0'), 'Values must be deleted on deleteMultiple()');
        $this->assertNull($this->cache->get('key1'), 'Values must be deleted on deleteMultiple()');
    }

    /**
     * @dataProvider invalidTtl
     */
    public function testSetMultipleInvalidTtl($ttl)
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->setMultiple(['key' => 'value'], $ttl);
    }

    public function testSetExpiredTtl()
    {
        $this->cache->set('key0', 'value');
        $this->cache->set('key0', 'value', 0);
        $this->assertNull($this->cache->get('key0'));
        $this->assertFalse($this->cache->has('key0'));

        $this->cache->set('key1', 'value', -1);
        $this->assertNull($this->cache->get('key1'));
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testNullOverwrite()
    {
        $this->cache->set('key', 5);
        $this->cache->set('key', null);

        $this->assertNull($this->cache->get('key'), 'Setting null to a key must overwrite previous value');
    }

    public function testDataTypeObject()
    {
        $object = new \stdClass();
        $object->a = 'foo';
        $this->cache->set('key', $object);
        $result = $this->cache->get('key');
        $this->assertTrue(is_object($result), 'Wrong data type. If we store object we must get an object back.');
        $this->assertEquals($object, $result);
    }

    public function testBinaryData()
    {
        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }

        $this->cache->set('key', $data);
        $result = $this->cache->get('key');
        $this->assertTrue($data === $result, 'Binary data must survive a round trip.');
    }

    public function testObjectDoesNotChangeInCache()
    {
        $obj = new \stdClass();
        $obj->foo = 'value';
        $this->cache->set('key', $obj);
        $obj->foo = 'changed';

        $cacheObject = $this->cache->get('key');
        $this->assertEquals('value', $cacheObject->foo, 'Object in cache should not have their values changed.');
    }

    public function testSetMultipleWithIntegerArrayKey()
    {
        $result = $this->cache->setMultiple(['0' => 'value0']);
        $this->assertTrue($result, 'setMultiple() must return true if success');
        $this->assertEquals('value0', $this->cache->get('0'));
    }

    public function testSetMultiple()
    {
        $result = $this->cache->setMultiple(['key0' => 'value0', 'key1' => 'value1']);
        $this->assertTrue($result, 'setMultiple() must return true if success');
        $this->assertEquals('value0', $this->cache->get('key0'));
        $this->assertEquals('value1', $this->cache->get('key1'));
    }

    public function testDataTypeInteger()
    {
        $this->cache->set('key', 5);
        $result = $this->cache->get('key');
        $this->assertTrue(5 === $result, 'Wrong data type. If we store an int we must get an int back.');
        $this->assertTrue(is_int($result), 'Wrong data type. If we store an int we must get an int back.');
    }

    public function testDelete()
    {
        $this->assertTrue($this->cache->delete('key'), 'Deleting a value that does not exist should return true');
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->delete('key'), 'Delete must return true on success');
        $this->assertNull($this->cache->get('key'), 'Values must be deleted on delete()');
    }

    public function testDeleteMultipleGenerator()
    {
        $gen = function () {
            yield 1 => 'key0';
            yield 1 => 'key1';
        };
        $this->cache->set('key0', 'value0');
        $this->assertTrue($this->cache->deleteMultiple($gen()), 'Deleting a generator should return true');

        $this->assertNull($this->cache->get('key0'), 'Values must be deleted on deleteMultiple()');
        $this->assertNull($this->cache->get('key1'), 'Values must be deleted on deleteMultiple()');
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testHasInvalidKeys($key)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->has($key);
    }

    public function testDataTypeArray()
    {
        $array = ['a' => 'foo', 2 => 'bar'];
        $this->cache->set('key', $array);
        $result = $this->cache->get('key');
        $this->assertTrue(is_array($result), 'Wrong data type. If we store array we must get an array back.');
        $this->assertEquals($array, $result);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetInvalidKeys($key)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get($key);
    }

    public function testSetMultipleWithGenerator()
    {
        $gen = function () {
            yield 'key0' => 'value0';
            yield 'key1' => 'value1';
        };

        $this->cache->setMultiple($gen());
        $this->assertEquals('value0', $this->cache->get('key0'));
        $this->assertEquals('value1', $this->cache->get('key1'));
    }

    public function testHas()
    {
        $this->assertFalse($this->cache->has('key0'));
        $this->cache->set('key0', 'value0');
        $this->assertTrue($this->cache->has('key0'));
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteMultipleInvalidKeys($key)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->deleteMultiple(['key1', $key, 'key2']);
    }

    /**
     * @dataProvider invalidArrayKeys
     */
    public function testSetMultipleInvalidKeys($key)
    {
        $this->expectException(InvalidArgumentException::class);

        $values = function () use ($key) {
            yield 'key1' => 'foo';
            yield $key => 'bar';
            yield 'key2' => 'baz';
        };

        $this->cache->setMultiple($values());
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteInvalidKeys($key)
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->delete($key);
    }

    public function testGetMultiple()
    {
        $result = $this->cache->getMultiple(['key0', 'key1']);
        $keys = [];
        foreach ($result as $i => $r) {
            $keys[] = $i;
            $this->assertNull($r);
        }
        sort($keys);
        $this->assertSame(['key0', 'key1'], $keys);

        $this->cache->set('key3', 'value');
        $result = $this->cache->getMultiple(['key2', 'key3', 'key4'], 'foo');
        $keys = [];
        foreach ($result as $key => $r) {
            $keys[] = $key;
            if ($key === 'key3') {
                $this->assertEquals('value', $r);
            } else {
                $this->assertEquals('foo', $r);
            }
        }
        sort($keys);
        $this->assertSame(['key2', 'key3', 'key4'], $keys);
    }

    public function testGetMultipleWithGenerator()
    {
        $gen = function () {
            yield 1 => 'key0';
            yield 1 => 'key1';
        };

        $this->cache->set('key0', 'value0');
        $result = $this->cache->getMultiple($gen());
        $keys = [];
        foreach ($result as $key => $r) {
            $keys[] = $key;
            if ($key === 'key0') {
                $this->assertEquals('value0', $r);
            } elseif ($key === 'key1') {
                $this->assertNull($r);
            } else {
                $this->assertFalse(true, 'This should not happend');
            }
        }
        sort($keys);
        $this->assertSame(['key0', 'key1'], $keys);
        $this->assertEquals('value0', $this->cache->get('key0'));
        $this->assertNull($this->cache->get('key1'));
    }

    public function testBasicUsageWithLongKey()
    {
        $key = str_repeat('a', 300);

        $this->assertFalse($this->cache->has($key));
        $this->assertTrue($this->cache->set($key, 'value'));

        $this->assertTrue($this->cache->has($key));
        $this->assertSame('value', $this->cache->get($key));

        $this->assertTrue($this->cache->delete($key));

        $this->assertFalse($this->cache->has($key));
    }

    public function testDataTypeFloat()
    {
        $float = 1.23456789;
        $this->cache->set('key', $float);
        $result = $this->cache->get('key');
        $this->assertTrue(is_float($result), 'Wrong data type. If we store float we must get an float back.');
        $this->assertEquals($float, $result);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testSetInvalidKeys($key)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set($key, 'foobar');
    }

    public function testDataTypeString()
    {
        $this->cache->set('key', '5');
        $result = $this->cache->get('key');
        $this->assertTrue('5' === $result, 'Wrong data type. If we store a string we must get an string back.');
        $this->assertTrue(is_string($result), 'Wrong data type. If we store a string we must get an string back.');
    }

    public function testSetMultipleTtl()
    {
        $this->cache->setMultiple(['key2' => 'value2', 'key3' => 'value3'], 1);
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertEquals('value3', $this->cache->get('key3'));
        $this->sleep(2);
        $this->assertNull($this->cache->get('key2'), 'Value must expire after ttl.');
        $this->assertNull($this->cache->get('key3'), 'Value must expire after ttl.');

        $this->cache->setMultiple(['key4' => 'value4'], new \DateInterval('PT1S'));
        $this->assertEquals('value4', $this->cache->get('key4'));
        $this->sleep(2);
        $this->assertNull($this->cache->get('key4'), 'Value must expire after ttl.');
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

    public function testGetMultipleNoIterable()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->getMultiple('key');
    }

    /**
     * @dataProvider validKeys
     */
    public function testSetValidKeys($key)
    {
        $this->cache->set($key, 'foobar');
        $this->assertEquals('foobar', $this->cache->get($key));
    }

    public function testClear()
    {
        $this->assertTrue($this->cache->clear(), 'Clearing an empty cache should return true');
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->clear(), 'Delete must return true on success');
        $this->assertNull($this->cache->get('key'), 'Values must be deleted on clear()');
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetMultipleInvalidKeys($key)
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->getMultiple(['key1', $key, 'key2']);
    }

    public function testSetMultipleExpiredTtl()
    {
        $this->cache->setMultiple(['key0' => 'value0', 'key1' => 'value1'], 0);
        $this->assertNull($this->cache->get('key0'));
        $this->assertNull($this->cache->get('key1'));
    }

    /**
     * @dataProvider validKeys
     */
    public function testSetMultipleValidKeys($key)
    {
        $this->cache->setMultiple([$key => 'foobar']);
        $result = $this->cache->getMultiple([$key]);
        $keys = [];
        foreach ($result as $i => $r) {
            $keys[] = $i;
            $this->assertEquals($key, $i);
            $this->assertEquals('foobar', $r);
        }
        $this->assertSame([$key], $keys);
    }

    public function testDeleteMultipleNoIterable()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->deleteMultiple('key');
    }

    public function testDataTypeBoolean()
    {
        $this->cache->set('key', false);
        $result = $this->cache->get('key');
        $this->assertTrue(is_bool($result), 'Wrong data type. If we store boolean we must get an boolean back.');
        $this->assertFalse($result);
        $this->assertTrue($this->cache->has('key'), 'has() should return true when true are stored. ');
    }

    /**
     * @dataProvider invalidTtl
     */
    public function testSetInvalidTtl($ttl)
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->set('key', 'value', $ttl);
    }

    /**
     * @dataProvider validData
     */
    public function testSetMultipleValidData($data)
    {
        $this->cache->setMultiple(['key' => $data]);
        $result = $this->cache->getMultiple(['key']);
        $keys = [];
        foreach ($result as $i => $r) {
            $keys[] = $i;
            $this->assertEquals($data, $r);
        }
        $this->assertSame(['key'], $keys);
    }

    protected function setUp(): void
    {
        $this->cache = new TestableDatabaseCache(
            self::$pdo,
            self::TABLE_NAME,
            self::DEFAULT_TTL
        );
    }

    protected function tearDown(): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
    }
}
