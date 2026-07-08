<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Cache;

use HarunGecit\EmailValidator\Cache\NullCacheAdapter;
use PHPUnit\Framework\TestCase;

class NullCacheAdapterTest extends TestCase
{
    private NullCacheAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new NullCacheAdapter();
    }

    public function testGetAlwaysReturnsDefault(): void
    {
        $this->cache->set('key', 'value');

        $this->assertNull($this->cache->get('key'));
        $this->assertEquals('default', $this->cache->get('key', 'default'));
    }

    public function testHasAlwaysReturnsFalse(): void
    {
        $this->cache->set('key', 'value');

        $this->assertFalse($this->cache->has('key'));
    }

    public function testSetReturnsTrue(): void
    {
        $result = $this->cache->set('key', 'value');

        $this->assertTrue($result);
    }

    public function testDeleteReturnsTrue(): void
    {
        $result = $this->cache->delete('key');

        $this->assertTrue($result);
    }

    public function testClearReturnsTrue(): void
    {
        $result = $this->cache->clear();

        $this->assertTrue($result);
    }

    public function testGetMultipleReturnsDefaults(): void
    {
        $this->cache->setMultiple(['key1' => 'value1', 'key2' => 'value2']);

        $values = $this->cache->getMultiple(['key1', 'key2'], 'default');
        $valuesArray = is_array($values) ? $values : iterator_to_array($values);

        $this->assertEquals('default', $valuesArray['key1']);
        $this->assertEquals('default', $valuesArray['key2']);
    }

    public function testSetMultipleReturnsTrue(): void
    {
        $result = $this->cache->setMultiple(['key1' => 'value1', 'key2' => 'value2']);

        $this->assertTrue($result);
    }

    public function testDeleteMultipleReturnsTrue(): void
    {
        $result = $this->cache->deleteMultiple(['key1', 'key2']);

        $this->assertTrue($result);
    }
}
