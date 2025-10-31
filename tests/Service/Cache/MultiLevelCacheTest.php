<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Cache\CacheItemInterface;
use Tourze\LarkAppBotBundle\Service\Cache\MultiLevelCache;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MultiLevelCache::class)]
#[RunTestsInSeparateProcesses]
final class MultiLevelCacheTest extends AbstractIntegrationTestCase
{
    public function testGetItem(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        // 使用唯一的测试键避免缓存冲突
        $testKey = 'test_key_' . uniqid();
        $result = $cache->getItem($testKey);

        $this->assertInstanceOf(CacheItemInterface::class, $result);
        $this->assertSame($testKey, $result->getKey());
        $this->assertFalse($result->isHit()); // 新获取的项应该是未命中状态
    }

    public function testGetStats(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        // 测试获取统计信息
        $stats = $cache->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('requests', $stats);
        $this->assertArrayHasKey('hits', $stats);
    }

    public function testClear(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        $result = $cache->clear();

        $this->assertTrue($result);
    }

    public function testHasItem(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        // 测试不存在的项
        $result = $cache->hasItem('non_existent_key');
        $this->assertFalse($result);

        // 设置一个项然后测试
        $item = $cache->getItem('test_key');
        $item->set('test_value');
        $cache->save($item);

        $result = $cache->hasItem('test_key');
        $this->assertTrue($result);
    }

    public function testGetItems(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        $keys = ['key1', 'key2'];
        $items = $cache->getItems($keys);

        $this->assertIsIterable($items);

        // 对于集成测试，只验证方法可以正常调用并返回可迭代对象
        // 具体的缓存行为取决于缓存级别配置
        $itemArray = iterator_to_array($items);
        $this->assertIsArray($itemArray);
    }

    public function testSave(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        $item = $cache->getItem('test_save_key');
        $item->set('test_value');

        $result = $cache->save($item);

        $this->assertTrue($result);

        // 验证保存后可以获取
        $savedItem = $cache->getItem('test_save_key');
        $this->assertTrue($savedItem->isHit());
        $this->assertSame('test_value', $savedItem->get());
    }

    public function testSaveDeferred(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        $item = $cache->getItem('test_deferred_key');
        $item->set('deferred_value');

        $result = $cache->saveDeferred($item);

        $this->assertTrue($result);
    }

    public function testCommit(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        // 先保存一个延迟项
        $item = $cache->getItem('commit_test_key');
        $item->set('commit_value');
        $cache->saveDeferred($item);

        $result = $cache->commit();

        $this->assertTrue($result);

        // 验证提交后可以获取
        $savedItem = $cache->getItem('commit_test_key');
        $this->assertTrue($savedItem->isHit());
        $this->assertSame('commit_value', $savedItem->get());
    }

    public function testDeleteItem(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        // 先保存一个项
        $item = $cache->getItem('delete_test_key');
        $item->set('delete_value');
        $cache->save($item);

        // 验证项存在
        $this->assertTrue($cache->hasItem('delete_test_key'));

        // 删除项
        $result = $cache->deleteItem('delete_test_key');

        $this->assertTrue($result);
        $this->assertFalse($cache->hasItem('delete_test_key'));
    }

    public function testDeleteItems(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        $keys = ['delete_key1', 'delete_key2'];

        // 先保存项
        foreach ($keys as $key) {
            $item = $cache->getItem($key);
            $item->set('value_' . $key);
            $cache->save($item);
        }

        // 验证项存在
        foreach ($keys as $key) {
            $this->assertTrue($cache->hasItem($key));
        }

        // 删除项
        $result = $cache->deleteItems($keys);

        $this->assertTrue($result);

        // 验证项已删除
        foreach ($keys as $key) {
            $this->assertFalse($cache->hasItem($key));
        }
    }

    public function testWarmup(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        $data = ['key1' => 'value1', 'key2' => 'value2'];

        // 测试预热方法执行不抛异常
        $cache->warmup($data, 3600);

        // 验证预热的项可以获取
        $this->assertTrue($cache->hasItem('key1'));
        $this->assertTrue($cache->hasItem('key2'));

        $item1 = $cache->getItem('key1');
        $item2 = $cache->getItem('key2');

        $this->assertTrue($item1->isHit());
        $this->assertTrue($item2->isHit());
        $this->assertSame('value1', $item1->get());
        $this->assertSame('value2', $item2->get());
    }

    public function testSetLevelConfig(): void
    {
        // 从容器获取 MultiLevelCache 服务
        $cache = self::getService(MultiLevelCache::class);

        $config = ['enabled' => false, 'default_lifetime' => 1800];

        // 测试设置级别配置执行不抛异常
        $cache->setLevelConfig('memory', $config);

        // 验证缓存实例仍然可用
        $this->assertInstanceOf(MultiLevelCache::class, $cache);

        // 验证缓存功能仍然正常工作
        $testKey = 'test_key_after_config';
        $item = $cache->getItem($testKey);
        $this->assertInstanceOf(CacheItemInterface::class, $item);

        // 测试不存在的级别不会导致错误
        $cache->setLevelConfig('non_existent', $config);

        // 再次验证缓存仍然正常工作
        $anotherItem = $cache->getItem('another_test_key');
        $this->assertInstanceOf(CacheItemInterface::class, $anotherItem);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 集成测试不需要额外的设置，服务将从容器获取
    }
}
