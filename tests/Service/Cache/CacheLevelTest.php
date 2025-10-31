<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Tourze\LarkAppBotBundle\Service\Cache\CacheLevel;

/**
 * @internal
 */
#[CoversClass(CacheLevel::class)]
final class CacheLevelTest extends TestCase
{
    private CacheLevel $cacheLevel;

    protected function setUp(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cacheLevel = new CacheLevel($cache, 'test_cache', ['enabled' => true]);
    }

    public function testGetName(): void
    {
        $name = $this->cacheLevel->getName();
        $this->assertNotEmpty($name);
        $this->assertIsString($name);
    }

    public function testIsEnabled(): void
    {
        $enabled = $this->cacheLevel->isEnabled();
        $this->assertIsBool($enabled);
    }

    public function testGetItem(): void
    {
        $result = $this->cacheLevel->getItem('test_key');
        $this->assertInstanceOf(CacheItemInterface::class, $result);
    }

    public function testGetItems(): void
    {
        $result = $this->cacheLevel->getItems(['key1']);
        $this->assertIsIterable($result);
    }

    public function testSave(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $result = $this->cacheLevel->save($item);
        $this->assertIsBool($result);
    }

    public function testClear(): void
    {
        $result = $this->cacheLevel->clear();
        $this->assertIsBool($result);
    }

    public function testDeleteItem(): void
    {
        $result = $this->cacheLevel->deleteItem('test_key');
        $this->assertIsBool($result);
    }

    public function testDeleteItems(): void
    {
        $result = $this->cacheLevel->deleteItems(['key1', 'key2']);
        $this->assertIsBool($result);
    }
}
