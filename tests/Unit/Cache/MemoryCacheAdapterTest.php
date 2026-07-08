<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Cache;

use HarunGecit\EmailValidator\Cache\MemoryCacheAdapter;
use PHPUnit\Framework\TestCase;

class MemoryCacheAdapterTest extends TestCase
{
    private MemoryCacheAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new MemoryCacheAdapter();
    }

    public function testSetAndGet(): void
    {
        $this->cache->set('key1', 'value1');

        $this->assertEquals('value1', $this->cache->get('key1'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->cache->has('key'));

        $this->cache->set('key', 'value');

        $this->assertTrue($this->cache->has('key'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->has('key'));

        $result = $this->cache->delete('key');

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key'));
    }

    public function testDeleteNonexistentKey(): void
    {
        $result = $this->cache->delete('nonexistent');

        $this->assertTrue($result);
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $result = $this->cache->clear();

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $values = $this->cache->getMultiple(['key1', 'key2', 'key3']);

        $this->assertIsIterable($values);
        $valuesArray = is_array($values) ? $values : iterator_to_array($values);
        $this->assertEquals('value1', $valuesArray['key1']);
        $this->assertEquals('value2', $valuesArray['key2']);
        $this->assertNull($valuesArray['key3']);
    }

    public function testGetMultipleWithDefault(): void
    {
        $this->cache->set('key1', 'value1');

        $values = $this->cache->getMultiple(['key1', 'key2'], 'default');
        $valuesArray = is_array($values) ? $values : iterator_to_array($values);

        $this->assertEquals('value1', $valuesArray['key1']);
        $this->assertEquals('default', $valuesArray['key2']);
    }

    public function testSetMultiple(): void
    {
        $result = $this->cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $this->assertTrue($result);
        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $result = $this->cache->deleteMultiple(['key1', 'key2']);

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));
    }

    public function testTtlExpiration(): void
    {
        $this->cache->set('key', 'value', 1);

        $this->assertTrue($this->cache->has('key'));
        $this->assertEquals('value', $this->cache->get('key'));

        sleep(2);

        $this->assertFalse($this->cache->has('key'));
        $this->assertNull($this->cache->get('key'));
    }

    public function testPrefixedCache(): void
    {
        $cache = new MemoryCacheAdapter('prefix_');
        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));
        $this->assertEquals('value', $cache->get('key'));
    }

    public function testStoresDifferentTypes(): void
    {
        $this->cache->set('string', 'text');
        $this->cache->set('int', 123);
        $this->cache->set('float', 3.14);
        $this->cache->set('bool', true);
        $this->cache->set('array', ['a', 'b']);

        $this->assertEquals('text', $this->cache->get('string'));
        $this->assertEquals(123, $this->cache->get('int'));
        $this->assertEquals(3.14, $this->cache->get('float'));
        $this->assertTrue($this->cache->get('bool'));
        $this->assertEquals(['a', 'b'], $this->cache->get('array'));
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->cache->count());

        $this->cache->set('key1', 'value1');
        $this->assertEquals(1, $this->cache->count());

        $this->cache->set('key2', 'value2');
        $this->assertEquals(2, $this->cache->count());

        $this->cache->delete('key1');
        $this->assertEquals(1, $this->cache->count());
    }
}
